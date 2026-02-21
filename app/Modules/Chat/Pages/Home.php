<?php

namespace App\Modules\Chat\Pages;

use Filament\Pages\Page;

class Home extends Page
{
    protected static ?string $slug = '/';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'pages.home';
}
