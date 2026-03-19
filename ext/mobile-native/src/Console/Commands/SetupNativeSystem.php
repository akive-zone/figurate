<?php

namespace Figurate\MobileNative\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupNativeSystem extends Command
{
    protected $signature = 'mobile:native';

    protected $description = 'Copy .env.example and append NativePHP + API env variables';

    public function handle(): int
    {
        $this->info('Setting up native environment...');

        if (! File::exists(base_path('.env'))) {
            File::copy(base_path('.env.example'), base_path('.env'));
            $this->info('Copied .env.example to .env');
        }

        $nativeEnvPath = $this->nativeEnvironmentPath();

        if (! File::exists($nativeEnvPath)) {
            $this->warn('Native environment template was not found. Nothing to merge.');

            return self::SUCCESS;
        }

        $envPath = base_path('.env');
        $envContent = File::get($envPath);
        $nativeLines = file($nativeEnvPath, FILE_IGNORE_NEW_LINES);

        foreach ($nativeLines as $line) {
            $trimmed = trim((string) $line);

            if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($trimmed, '=')) {
                continue;
            }

            [$key] = explode('=', $trimmed, 2);
            $key = trim($key);

            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $envContent) === 1) {
                $envContent = preg_replace($pattern, $trimmed, $envContent) ?? $envContent;
            } else {
                $envContent .= PHP_EOL.$trimmed;
            }
        }

        File::put($envPath, rtrim($envContent).PHP_EOL);

        $this->info('Merged native environment values into .env successfully.');

        return self::SUCCESS;
    }

    protected function nativeEnvironmentPath(): string
    {
        $overridePath = base_path('.env.native');

        if (File::exists($overridePath)) {
            return $overridePath;
        }

        return dirname(__DIR__, 3).'/resources/stubs/env.native';
    }
}
