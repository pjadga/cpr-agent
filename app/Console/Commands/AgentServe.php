<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
 * pcntl fork, so the same command works on Windows — pcntl is POSIX-only.
 *
 * The web server itself (FrankenPHP/Caddy) is a separate foreground process;
 * pass --with-web to also supervise it via the AGENT_WEB_COMMAND env var once
 * the single-binary invocation is confirmed on the target platform.
 */
class AgentServe extends Command
{
    protected $signature = 'agent:serve
        {--no-migrate : Skip running migrations before starting}
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
                $this->error('Migrations failed — aborting start.');

                return self::FAILURE;
            }
        }

        // Graceful shutdown on signals where pcntl is available (Linux/macOS).
        // Windows lacks pcntl; the child processes are terminated when the
        // parent exits, and --max-ticks bounds the loop for tests.
        if (extension_loaded('pcntl')) {
            $this->trap([SIGINT, SIGTERM], function () {
                $this->shouldStop = true;
            });
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
                    $this->warn("[{$name}] exited — restarting");
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
            $web = (string) env('AGENT_WEB_COMMAND', '');
            if ($web === '') {
                $this->warn('--with-web set but AGENT_WEB_COMMAND is empty; skipping web process.');
            } else {
                $processes['web'] = $this->splitCommand($web);
            }
        }

        return $processes;
    }

    /**
     * Base argv for invoking artisan. AGENT_PHP_ARGS injects any interpreter
     * subcommand the runtime needs — e.g. "php-cli" under the FrankenPHP binary
     * (`frankenphp php-cli artisan ...`) — without hard-coding the runtime.
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
        return array_values(array_filter(explode(' ', $command)));
    }
}
