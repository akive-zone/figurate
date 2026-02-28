<?php

namespace App\Http\Resources\Native\Web\ContextServers\Pages;

use App\Http\Resources\Native\Web\ContextServers\ContextServerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditContextServer extends EditRecord
{
    protected static string $resource = ContextServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        ContextServerResource::ensureContextSelectionAllowed(
            $data['contextable_type'] ?? null,
            $data['contextable_id'] ?? null,
        );

        return $data;
    }
}
