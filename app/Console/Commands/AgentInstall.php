<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * First-run bootstrap for a self-hosted install.
 *
 * Only fills in what's missing. Generates a per-install APP_KEY
 * and Reverb credentials (so no two installs share secrets), creates the
 * SQLite database and the storage skeleton, then runs migrations. Safe to run
 * repeatedly — an already-installed instance is left untouched.
 */
class AgentInstall extends Command
{
    protected $signature = 'agent:install
        {--show : Print the generated secrets to the console}
        {--force : Regenerate secrets even if they are already set}';

    protected $description = 'Prepare a self-hosted CPR Agent install (keys, database, storage, migrations)';

    public function handle(): int
    {
        $this->info('CPR Agent — first-run install');

        $envPath = app()->environmentFilePath();
        $envWritable = is_file($envPath) && is_writable($envPath);

        if (! $envWritable) {
            $this->warn("Env file not writable: {$envPath}");
            $this->warn('Generated secrets will be printed instead — set them as environment variables before launch.');
        } else {
            $this->line("Env file: {$envPath}");
        }

        $generated = [];

        // APP_KEY — 32 random bytes, base64, matching Laravel's own key format.
        if ($this->option('force') || blank(config('app.key'))) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            config(['app.key' => $key]);
            $generated['APP_KEY'] = $key;
            $this->components->task('Generate APP_KEY');
        } else {
            $this->components->twoColumnDetail('APP_KEY', '<fg=gray>already set</>');
        }

        // Reverb credentials — unique per install so one GM's tokens are not
        // valid against another's server.
        foreach (['REVERB_APP_KEY' => 'reverb.apps.apps.0.key', 'REVERB_APP_SECRET' => 'reverb.apps.apps.0.secret'] as $envKey => $configPath) {
            if ($this->option('force') || blank(config($configPath))) {
                $value = bin2hex(random_bytes(16));
                config([$configPath => $value]);
                $generated[$envKey] = $value;
                $this->components->task("Generate {$envKey}");
            } else {
                $this->components->twoColumnDetail($envKey, '<fg=gray>already set</>');
            }
        }

        // Persist generated secrets to the env file (Mode A / writable installs).
        if ($generated && $envWritable) {
            foreach ($generated as $envKey => $value) {
                $this->setEnvValue($envPath, $envKey, $value);
            }
            $this->components->info('Secrets written to '.basename($envPath));
        }

        if ($generated && ($this->option('show') || ! $envWritable)) {
            $this->newLine();
            $this->line('<comment>Generated secrets — keep these safe:</comment>');
            foreach ($generated as $envKey => $value) {
                $this->line("  {$envKey}={$value}");
            }
            $this->newLine();
        }

        // SQLite database file.
        if (config('database.default') === 'sqlite') {
            $db = config('database.connections.sqlite.database');
            if ($db && $db !== ':memory:') {
                if (! is_dir(dirname($db))) {
                    mkdir(dirname($db), 0755, true);
                }
                if (! file_exists($db)) {
                    touch($db);
                    $this->components->task("Create database {$db}");
                } else {
                    $this->components->twoColumnDetail('Database', '<fg=gray>exists</>');
                }
            }
        }

        // Storage skeleton — honours LARAVEL_STORAGE_PATH, so this also seeds
        // the persistent storage dir for the single-binary build.
        foreach (['framework/cache/data', 'framework/sessions', 'framework/views', 'app', 'logs'] as $dir) {
            $path = storage_path($dir);
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
        $this->components->task('Storage skeleton');

        // Migrations.
        $this->newLine();
        $this->components->info('Running migrations');
        $exit = Artisan::call('migrate', ['--force' => true], $this->getOutput());
        if ($exit !== 0) {
            $this->error('Migrations failed.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Install complete.');

        return self::SUCCESS;
    }

    /**
     * Set or replace a KEY=value line in an env file, preserving the rest.
     */
    protected function setEnvValue(string $path, string $key, string $value): void
    {
        $contents = file_get_contents($path);
        // Only quote when the value would otherwise be mis-parsed by dotenv:
        // whitespace, a comment marker, or embedded quotes. Values that merely
        // contain '=' (e.g. base64 APP_KEY padding) stay unquoted, matching
        // Laravel's own key:generate output.
        $escaped = preg_match('/[\s#"\']/', $value) ? '"'.$value.'"' : $value;
        $line = "{$key}={$escaped}";

        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents)) {
            $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents);
        } else {
            $contents = rtrim($contents, "\n")."\n".$line."\n";
        }

        file_put_contents($path, $contents);
    }
}
