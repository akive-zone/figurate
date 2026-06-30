<?php

namespace Figurate\WebView\Filament\Resources\ContextServers\Pages;

use Figurate\WebView\Filament\Resources\ContextServers\ContextServerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContextServers extends ListRecords
{
    protected static string $resource = ContextServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
