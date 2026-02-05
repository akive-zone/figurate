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
        $this->info('🔧 Setting up .env file…');

        if (! File::exists(base_path('.env'))) {
            File::copy(base_path('.env.example'), base_path('.env'));
            $this->info('✅ Copied .env.example to .env');
        }

        $filePath = base_path('.env.example');

        // Read the file into an array of lines
        $envVars = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $envContent = File::get(base_path('.env'));
        foreach ($envVars as $line) {
            if (! str_contains($envContent, explode('=', $line)[0])) {
                File::append(base_path('.env'), PHP_EOL.$line);
            }
        }

        $this->info('✅ Environment variables appended successfully.');

        return Command::SUCCESS;
    }
}
