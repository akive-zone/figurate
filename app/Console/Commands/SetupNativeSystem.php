<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SetupNativeSystem extends Command
{
    protected $signature = 'setup:native';

    protected $description = 'Copy .env.example and append NativePHP + API env variables';

    public function handle(): int
    {
        $this->info('Setting up native environment...');

        if (! File::exists(base_path('.env'))) {
            File::copy(base_path('.env.example'), base_path('.env'));
            $this->info('Copied .env.example to .env');
        }

        if (! File::exists(base_path('.env.native'))) {
            $this->warn('.env.native was not found. Nothing to merge.');

            return Command::SUCCESS;
        }

        $envPath = base_path('.env');
        $envContent = File::get($envPath);
        $nativeLines = file(base_path('.env.native'), FILE_IGNORE_NEW_LINES);

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

        $this->info('Merged .env.native into .env successfully.');

        return Command::SUCCESS;
    }
}
