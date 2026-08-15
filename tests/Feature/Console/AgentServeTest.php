<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class AgentServeTest extends TestCase
{
    public function test_dry_run_lists_reverb_and_queue_without_migrating_or_spawning(): void
    {
        $this->artisan('agent:serve', ['--dry-run' => true])
            ->expectsOutputToContain('reverb:start')
            ->expectsOutputToContain('queue:work')
            ->doesntExpectOutputToContain('Running migrations')
            ->assertSuccessful();
    }

    public function test_agent_php_args_injects_an_interpreter_subcommand(): void
    {
        // Simulate the FrankenPHP binary case: `frankenphp php-cli artisan ...`
        $_ENV['AGENT_PHP_ARGS'] = 'php-cli';
        $_SERVER['AGENT_PHP_ARGS'] = 'php-cli';

        try {
            $this->artisan('agent:serve', ['--dry-run' => true])
                ->expectsOutputToContain('php-cli')
                ->assertSuccessful();
        } finally {
            unset($_ENV['AGENT_PHP_ARGS'], $_SERVER['AGENT_PHP_ARGS']);
        }
    }

    public function test_web_command_survives_quoted_paths_containing_spaces(): void
    {
        // The launcher has to quote both paths when the install directory has a
        // space in it. env() strips matching outer quotes from a value, so a
        // command that both starts and ends with one loses its first and last
        // character, and every argument after that is wrong. Reading the real
        // environment variable avoids that.
        // Paths without spaces on purpose. The command is still quoted, which is
        // what triggers the stripping, but the arguments then render unquoted,
        // so a surviving quote is unambiguous evidence of the bug. (With spaces
        // in the paths the renderer re-quotes them and the two cases look the
        // same on screen.)
        putenv('AGENT_WEB_COMMAND="C:\CPRAgent\frankenphp.exe" run --config "C:\CPRAgent\Caddyfile"');

        try {
            $this->artisan('agent:serve', ['--with-web' => true, '--dry-run' => true])
                ->expectsOutputToContain('C:\CPRAgent\frankenphp.exe run --config C:\CPRAgent\Caddyfile')
                ->doesntExpectOutputToContain('exe"')
                ->assertSuccessful();
        } finally {
            putenv('AGENT_WEB_COMMAND');
        }
    }

    public function test_it_reaps_a_stray_child_left_by_a_hard_killed_run(): void
    {
        // A hard-killed supervisor leaves its children running; they keep
        // holding Reverb's port, so the next launch must clear them out first.
        // The marker is unique to this test run, so the match can only ever hit
        // the process started below.
        // A shell sleep rather than PHP_BINARY: under the FrankenPHP binary
        // PHP_BINARY is frankenphp.exe, which rejects `php -r` style arguments,
        // so the "stray" would die on spawn and prove nothing.
        $marker = 'agent-serve-stray-'.bin2hex(random_bytes(8));
        $command = windows_os()
            ? ['powershell', '-NoProfile', '-NonInteractive', '-Command', "Start-Sleep -Seconds 30 # {$marker}"]
            : ['sh', '-c', "sleep 30 # {$marker}"];

        config(['agent.serve.processes' => ['stray' => $command]]);

        $stray = Process::start($command);

        try {
            // Give it a moment to appear in the OS process table.
            usleep(500_000);

            $this->artisan('agent:serve', ['--no-migrate' => true, '--max-ticks' => 1])
                ->expectsOutputToContain('stray from a previous run')
                ->assertSuccessful();

            $this->assertFalse($stray->running(), 'The stray process should have been killed.');
        } finally {
            if ($stray->running()) {
                $stray->stop();
            }
        }
    }

    public function test_no_reap_skips_the_stray_check(): void
    {
        config(['agent.serve.processes' => [
            'tick' => [PHP_BINARY, '-r', 'usleep(10000);'],
        ]]);

        $this->artisan('agent:serve', [
            '--no-migrate' => true,
            '--no-reap' => true,
            '--max-ticks' => 2,
        ])
            ->doesntExpectOutputToContain('stray from a previous run')
            ->assertSuccessful();
    }

    public function test_it_restarts_a_child_that_exits(): void
    {
        // A harmless, very short-lived process so the supervisor observes it
        // die and restart it within the bounded tick budget.
        config(['agent.serve.processes' => [
            'tick' => [PHP_BINARY, '-r', 'usleep(10000);'],
        ]]);

        $this->artisan('agent:serve', ['--no-migrate' => true, '--max-ticks' => 4])
            ->expectsOutputToContain('restarting')
            ->assertSuccessful();
    }
}
