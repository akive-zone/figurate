<?php

namespace App\View\Pages\Server;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class Passkeys extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|UnitEnum|null $navigationGroup = 'Account';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Passkeys';

    protected static ?string $title = 'Passkeys';

    protected static ?string $slug = 'passkeys';

    protected string $view = 'view.pages.server.passkeys';
}
