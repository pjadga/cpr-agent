<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentInstallTest extends TestCase
{
    use RefreshDatabase;

    private string $envDir;

    private string $envFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Redirect the env path to a throwaway file so the command never
        // touches the developer's real .env.
        $this->envDir = sys_get_temp_dir().'/agent-install-'.uniqid();
        mkdir($this->envDir);
        $this->envFile = $this->envDir.'/.env.test';
        file_put_contents($this->envFile, "APP_NAME=Test\nAPP_KEY=\nREVERB_APP_KEY=\nREVERB_APP_SECRET=\n");

        $this->app->useEnvironmentPath($this->envDir);
        $this->app->loadEnvironmentFrom('.env.test');
    }

    protected function tearDown(): void
    {
        @unlink($this->envFile);
        @rmdir($this->envDir);
        parent::tearDown();
    }

    public function test_it_generates_missing_secrets_and_writes_them_to_the_env_file(): void
    {
        config(['app.key' => null]);
        config(['reverb.apps.apps.0.key' => null]);
        config(['reverb.apps.apps.0.secret' => null]);

        $this->artisan('agent:install')->assertSuccessful();

        $contents = file_get_contents($this->envFile);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]{40,}$/m', $contents);
        $this->assertMatchesRegularExpression('/^REVERB_APP_KEY=[0-9a-f]{32}$/m', $contents);
        $this->assertMatchesRegularExpression('/^REVERB_APP_SECRET=[0-9a-f]{32}$/m', $contents);
    }

    public function test_it_is_idempotent_and_leaves_existing_secrets_untouched(): void
    {
        // Set every secret the command looks at. Don't rely on ambient config:
        // phpunit.xml only pins APP_KEY, so the Reverb keys are blank on any
        // checkout without a real .env (fresh clone, CI, the standalone build),
        // and the command would correctly generate them — failing this test for
        // the wrong reason.
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
        config(['reverb.apps.apps.0.key' => str_repeat('a', 32)]);
        config(['reverb.apps.apps.0.secret' => str_repeat('b', 32)]);

        $before = file_get_contents($this->envFile);

        $this->artisan('agent:install')->assertSuccessful();

        // Blank env-file lines stay blank because config already had values,
        // so the command had nothing to generate.
        $this->assertSame($before, file_get_contents($this->envFile));
    }

    public function test_force_regenerates_even_when_secrets_are_present(): void
    {
        $this->artisan('agent:install', ['--force' => true])->assertSuccessful();

        $contents = file_get_contents($this->envFile);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:/m', $contents);
    }
}
