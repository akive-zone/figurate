<?php

namespace Figurate\SocialAuth;

use Illuminate\Support\ServiceProvider;

class SocialAuthServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(dirname(__DIR__).'/routes/web.php');
    }
}
