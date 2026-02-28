<?php

namespace App\Http\Resources\Server\Web\ContextServers\Pages;

use App\Http\Resources\Server\Web\ContextServers\ContextServerResource;
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
