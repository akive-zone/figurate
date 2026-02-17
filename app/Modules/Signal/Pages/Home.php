<?php

namespace App\Modules\Signal\Pages;

use Filament\Pages\Page;

class Home extends Page
{
    protected static ?string $slug = '/';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'modules.signal.pages.home';
}
