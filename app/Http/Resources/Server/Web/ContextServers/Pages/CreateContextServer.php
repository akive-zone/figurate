<?php

namespace App\Http\Resources\Server\Web\ContextServers\Pages;

use App\Http\Resources\Server\Web\ContextServers\ContextServerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContextServer extends CreateRecord
{
    protected static string $resource = ContextServerResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        ContextServerResource::ensureContextSelectionAllowed(
            $data['contextable_type'] ?? null,
            $data['contextable_id'] ?? null,
        );

        return $data;
    }
}
