<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Support\Facades\Artisan;

/**
 * First-run bootstrap for an install.
 *
 * Only fills in what's missing. Creates the env file from a template if there
 * isn't one, generates a per-install APP_KEY and Reverb credentials (so no two
 * installs share secrets), creates the SQLite database and the storage
 * skeleton, then runs migrations. Safe to run repeatedly: an already installed
 * instance is left untouched.
 *
 * Not just for the standalone build. On a fresh clone under Herd, DDEV or
 * `artisan serve` this is the one command that turns a checkout into a working
 * local install, which is why the env handling is driven by whichever template
 * matches the target file rather than being hard coded to standalone.
 */
class AgentInstall extends Command
{
    protected $signature = 'agent:install
        {--show : Print the generated secrets to the console}
        {--force : Regenerate secrets even if they are already set}';

    protected $description = 'Prepare a self-hosted CPR Agent install (keys, database, storage, migrations)';

    public function handle(): int
    {
        $this->info('CPR Agent ,  first-run install');

        $envPath = $this->resolveEnvPath();

        if ($this->seedEnvFile($envPath)) {
            // The app booted before this file existed, so nothing in it is in
            // config yet. Reload both, or the migration below would run against
            // whatever the config defaults happen to be rather than against the
            // connection the template just asked for.
            $this->laravel->loadEnvironmentFrom(basename($envPath));
            (new LoadEnvironmentVariables)->bootstrap($this->laravel);
            (new LoadConfiguration)->bootstrap($this->laravel);
        }

        $envWritable = is_file($envPath) && is_writable($envPath);

        if (! $envWritable) {
            $this->warn("Env file not writable: {$envPath}");
            $this->warn('Generated secrets will be printed instead. Set them as environment variables before launch.');
        } else {
            $this->line("Env file: {$envPath}");
        }

        $generated = [];

        // APP_KEY, 32 random bytes, base64, matching Laravel's own key format.
        if ($this->option('force') || blank(config('app.key'))) {
            $key = 'base64:'.base64_encode(random_bytes(32));
            config(['app.key' => $key]);
            $generated['APP_KEY'] = $key;
            $this->components->task('Generate APP_KEY');
        } else {
            $this->components->twoColumnDetail('APP_KEY', '<fg=gray>already set</>');
        }

        // Reverb credentials, unique per install so one GM's tokens are not
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
            $this->line('<comment>Generated secrets ,  keep these safe:</comment>');
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

        // Storage skeleton, honours LARAVEL_STORAGE_PATH, so this also seeds
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
     * Work out which env file this install should be writing to.
     *
     * Laravel only switches to `.env.{APP_ENV}` once that file already exists,
     * so on a genuine first run environmentFilePath() still points at `.env`
     * even when APP_ENV names a specific environment. Where a matching template
     * exists we take that as the intent and target the specific file.
     */
    protected function resolveEnvPath(): string
    {
        $configured = $this->laravel->environmentFilePath();

        if (is_file($configured)) {
            return $configured;
        }

        $specific = $this->laravel->basePath('.env.'.$this->laravel->environment());

        return is_file($specific.'.example') ? $specific : $configured;
    }

    /**
     * Create the env file from a template when it does not exist yet, so a
     * fresh clone (Herd, DDEV, or the standalone build) has somewhere to keep
     * its generated secrets instead of only printing them.
     *
     * Prefers a template matching the target file, so APP_ENV=standalone picks
     * up `.env.standalone.example` rather than the generic local one.
     *
     * @return bool Whether a file was created.
     */
    protected function seedEnvFile(string $path): bool
    {
        if (is_file($path)) {
            return false;
        }

        $templates = [$path.'.example', $this->laravel->basePath('.env.example')];

        foreach ($templates as $template) {
            if (! is_file($template)) {
                continue;
            }

            copy($template, $path);
            $this->components->task('Create '.basename($path).' from '.basename($template));

            return true;
        }

        $this->warn('No env file and no template to create one from.');

        return false;
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
