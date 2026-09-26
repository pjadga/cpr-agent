<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\InvokedProcess;
use Illuminate\Support\Facades\Process;

/**
 * One-command launch + supervisor for a self-hosted install.
 *
 * Starts the background daemons the app needs (Reverb WebSocket server + a
 * queue worker) as child processes, restarts any that die, and shuts them all
 * down cleanly on Ctrl-C. Runs pending migrations first by default, so an
 * update is just "drop in the new build, run agent:serve".
 *
 * Deliberately built on the Process facade (symfony/process) rather than
 * pcntl fork, so the same command works on Windows, pcntl is POSIX-only.
 *
 * The web server itself (FrankenPHP/Caddy) is a separate foreground process;
 * pass --with-web to also supervise it via the AGENT_WEB_COMMAND env var once
 * the single-binary invocation is confirmed on the target platform.
 *
 * ORPHANED CHILDREN. A graceful exit stops every child (Symfony shells out to
 * `taskkill /F /T` on Windows, so the whole tree goes). A hard kill does not:
 * nothing ties a child's lifetime to this process, so force-killing the
 * supervisor, or, on Windows, closing the console window, which delivers
 * CTRL_CLOSE_EVENT that PHP cannot trap, leaves Reverb holding its port and
 * the next launch fails to bind. Verified on Windows 2026-08-14.
 *
 * Two defences, because neither alone is enough:
 *  1. Ctrl-C/Ctrl-Break are trapped on Windows too (sapi_windows_set_ctrl_handler),
 *     not just on POSIX, so the common interactive quit takes the clean path.
 *  2. Startup reaps strays left by a previous run, matched on this install's
 *     own artisan path, so a second checkout on the same machine is untouched.
 *     This is what makes "close the window, double-click again" work.
 */
class AgentServe extends Command
{
    protected $signature = 'agent:serve
        {--no-migrate : Skip running migrations before starting}
        {--no-reap : Skip killing strays left behind by a previous hard-killed run}
        {--with-web : Also supervise the web server (needs AGENT_WEB_COMMAND)}
        {--dry-run : Print the child commands that would run, then exit}
        {--max-ticks=0 : Stop after N supervise ticks (testing only; 0 = run forever)}';

    protected $description = 'Run the self-hosted app: supervise Reverb + queue (+ optional web) with restart-on-exit';

    private bool $shouldStop = false;

    public function handle(): int
    {
        $processes = $this->processes();

        if ($this->option('dry-run')) {
            $this->info('agent:serve would supervise:');
            foreach ($processes as $name => $cmd) {
                $this->line("  <comment>{$name}</comment>: ".$this->renderCommand($cmd));
            }

            return self::SUCCESS;
        }

        if (! $this->option('no-migrate')) {
            $this->components->info('Running migrations');
            if ($this->call('migrate', ['--force' => true]) !== 0) {
                $this->error('Migrations failed, aborting start.');

                return self::FAILURE;
            }
        }

        // Graceful shutdown on signals where pcntl is available (Linux/macOS).
        if (extension_loaded('pcntl')) {
            $this->trap([SIGINT, SIGTERM], function () {
                $this->shouldStop = true;
            });
        }

        // Windows has no pcntl, but the CLI SAPI can still trap Ctrl-C and
        // Ctrl-Break. Without this, Ctrl-C kills the supervisor outright and
        // strands the children. (Window-close cannot be trapped, that case is
        // covered by reapStrays() on the next launch.)
        if (function_exists('sapi_windows_set_ctrl_handler')) {
            sapi_windows_set_ctrl_handler(function () {
                $this->shouldStop = true;
            });
        }

        if (! $this->option('no-reap')) {
            $this->reapStrays($processes);
        }

        $running = [];
        foreach ($processes as $name => $cmd) {
            $running[$name] = $this->spawn($name, $cmd);
        }

        $maxTicks = (int) $this->option('max-ticks');
        $ticks = 0;

        while (! $this->shouldStop) {
            foreach ($processes as $name => $cmd) {
                if (! $running[$name]->running()) {
                    $this->warn("[{$name}] exited, restarting");
                    $running[$name] = $this->spawn($name, $cmd);
                }
            }

            if ($maxTicks > 0 && ++$ticks >= $maxTicks) {
                break;
            }

            usleep(500_000);

            if (extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }
        }

        $this->newLine();
        $this->components->info('Shutting down child processes');
        foreach ($running as $name => $proc) {
            $proc->stop();
        }

        return self::SUCCESS;
    }

    /**
     * Kill child processes left running by a previous supervisor that was hard
     * killed (or whose console window was closed).
     *
     * Matching is by command line, not by a recorded PID: on Windows the PID we
     * hold for a child can belong to an intermediate shell that has already
     * exited, and PIDs get recycled. A process is only killed when every
     * argument of one of our child commands appears in its command line, that
     * includes this install's absolute artisan path, so a second checkout, a
     * DDEV container, or an unrelated PHP process is never touched.
     *
     * @param  array<string, array<int, string>>  $processes
     */
    private function reapStrays(array $processes): void
    {
        $reaped = 0;

        foreach ($this->runningProcesses($processes) as $pid => $commandLine) {
            if ($pid === getmypid()) {
                continue;
            }

            foreach ($processes as $name => $command) {
                if (! $this->commandMatches($commandLine, $command)) {
                    continue;
                }

                $this->warn("[{$name}] stray from a previous run (pid {$pid}), killing");
                $this->kill($pid);
                $reaped++;
                break;
            }
        }

        if ($reaped > 0) {
            $this->components->info("Reaped {$reaped} stray process(es) before starting.");
            // Give the OS a moment to release the listening sockets, or Reverb
            // races the dying process for its port and fails to bind.
            usleep(750_000);
        }
    }

    /**
     * Every argument after the interpreter must be present for a match, so
     * "reverb:start" alone can never take out an unrelated process.
     *
     * @param  array<int, string>  $command
     */
    private function commandMatches(string $commandLine, array $command): bool
    {
        $args = array_slice($command, 1);

        if ($args === []) {
            return false;
        }

        foreach ($args as $arg) {
            if (! str_contains($commandLine, $arg)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Currently running processes as [pid => command line], narrowed to the
     * executables our own children run under.
     *
     * @param  array<string, array<int, string>>  $processes
     * @return array<int, string>
     */
    private function runningProcesses(array $processes): array
    {
        $binaries = array_unique(array_map(
            fn (array $command) => basename($command[0]),
            $processes
        ));

        $result = windows_os()
            ? $this->runningProcessesWindows($binaries)
            : Process::run(['ps', '-eo', 'pid=,args=']);

        if (! $result->successful()) {
            $this->warn('Could not list running processes; skipping stray check.');

            return [];
        }

        $found = [];

        foreach (preg_split('/\R/', trim($result->output())) as $line) {
            if (preg_match('/^\s*(\d+)[\s\t]+(.+)$/', $line, $m)) {
                $found[(int) $m[1]] = $m[2];
            }
        }

        return $found;
    }

    /**
     * @param  array<int, string>  $binaries
     */
    private function runningProcessesWindows(array $binaries): ProcessResult
    {
        // CIM rather than tasklist: only CIM exposes the full command line, and
        // the name filter keeps this to a few rows instead of every process.
        // Win32_Process.Name always carries the extension, but a command may be
        // written without it ("powershell", "frankenphp"), so match both forms.
        $names = [];

        foreach ($binaries as $binary) {
            $names[] = $binary;

            if (! str_ends_with(strtolower($binary), '.exe')) {
                $names[] = $binary.'.exe';
            }
        }

        $filter = implode(' or ', array_map(
            fn (string $name) => "Name='".str_replace("'", "''", $name)."'",
            array_unique($names)
        ));

        return Process::run([
            'powershell', '-NoProfile', '-NonInteractive', '-Command',
            "Get-CimInstance Win32_Process -Filter \"{$filter}\" | ".
            'ForEach-Object { "$($_.ProcessId)`t$($_.CommandLine)" }',
        ]);
    }

    /**
     * Kill a process and its descendants.
     */
    private function kill(int $pid): void
    {
        if (windows_os()) {
            Process::run(['taskkill', '/F', '/T', '/PID', (string) $pid]);

            return;
        }

        Process::run(['kill', '-TERM', (string) $pid]);
    }

    /**
     * Start one child process, streaming its output with a name prefix.
     */
    private function spawn(string $name, array $command): InvokedProcess
    {
        $this->components->task("Start {$name}");

        return Process::forever()->start($command, function (string $type, string $buffer) use ($name) {
            $this->output->write("[{$name}] {$buffer}");
        });
    }

    /**
     * The child processes to supervise. Overridable via config for testing.
     *
     * @return array<string, array<int, string>>
     */
    private function processes(): array
    {
        if ($custom = config('agent.serve.processes')) {
            return $custom;
        }

        $base = $this->artisanBase();

        $processes = [
            'reverb' => [...$base, 'reverb:start'],
            'queue' => [...$base, 'queue:work', '--tries=1', '--rest=1'],
        ];

        if ($this->option('with-web')) {
            $web = $this->webCommand();
            if ($web === '') {
                $this->warn('--with-web set but AGENT_WEB_COMMAND is empty; skipping web process.');
            } else {
                $processes['web'] = $this->splitCommand($web);
            }
        }

        return $processes;
    }

    /**
     * The web server command line, read verbatim when it comes from a real
     * environment variable.
     *
     * env() strips matching outer quotes from a value, which quietly destroys a
     * command line whose first argument is a quoted path and whose last one is
     * too: `"C:\...\frankenphp.exe" run --config "C:\...\Caddyfile"` loses its
     * first and last character and every argument after that is wrong. Quoting
     * both is exactly what a launcher has to do when the install path contains
     * a space, so this is the normal case, not an edge case.
     *
     * getenv() returns the untouched value, so a launcher gets what it set. A
     * value defined only in a .env file falls through to env() and keeps the
     * usual dotenv semantics, which is what someone editing that file expects.
     */
    private function webCommand(): string
    {
        $raw = getenv('AGENT_WEB_COMMAND');

        if (is_string($raw) && $raw !== '') {
            return $raw;
        }

        return (string) env('AGENT_WEB_COMMAND', '');
    }

    /**
     * Base argv for invoking artisan. AGENT_PHP_ARGS injects any interpreter
     * subcommand the runtime needs, e.g. "php-cli" under the FrankenPHP binary
     * (`frankenphp php-cli artisan ...`), without hard-coding the runtime.
     *
     * @return array<int, string>
     */
    private function artisanBase(): array
    {
        $phpArgs = array_values(array_filter(explode(' ', (string) env('AGENT_PHP_ARGS', ''))));

        return [PHP_BINARY, ...$phpArgs, base_path('artisan')];
    }

    /**
     * @param  array<int, string>  $command
     */
    private function renderCommand(array $command): string
    {
        return implode(' ', array_map(fn ($p) => str_contains($p, ' ') ? '"'.$p.'"' : $p, $command));
    }

    /**
     * @return array<int, string>
     */
    private function splitCommand(string $command): array
    {
        // Double quotes are respected because AGENT_WEB_COMMAND carries absolute
        // paths, and on Windows those routinely contain spaces. Splitting on
        // every space would turn one path into several broken arguments.
        preg_match_all('/"([^"]*)"|(\S+)/', $command, $matches, PREG_SET_ORDER);

        $parts = [];

        foreach ($matches as $match) {
            $parts[] = ($match[2] ?? '') !== '' ? $match[2] : $match[1];
        }

        return array_values(array_filter($parts, fn (string $part) => $part !== ''));
    }
}
