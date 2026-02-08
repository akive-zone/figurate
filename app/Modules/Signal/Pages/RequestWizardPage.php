<?php

namespace App\Modules\Signal\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class RequestWizardPage extends Page
{
    protected static ?string $slug = 'requests/new';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'New Request';

    protected static ?string $title = 'Request Wizard';

    protected static string|\UnitEnum|null $navigationGroup = 'Signal';

    protected string $view = 'modules.signal.pages.request-wizard-page';
}
