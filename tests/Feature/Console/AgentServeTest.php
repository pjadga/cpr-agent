<?php

namespace Tests\Feature\Console;

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
