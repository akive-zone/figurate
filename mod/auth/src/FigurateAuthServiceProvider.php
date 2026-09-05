<?php

namespace Figurate\Auth;

use Figurate\Auth\Support\RobotUsers;
use Illuminate\Support\ServiceProvider;

class FigurateAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/figurate-auth.php', 'figurate-auth');

        config()->set('auth.interactive_user_types', array_values(array_unique([
            ...config('auth.interactive_user_types', []),
            RobotUsers::Robot,
        ])));
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(dirname(__DIR__).'/routes/api.php');
    }
}
