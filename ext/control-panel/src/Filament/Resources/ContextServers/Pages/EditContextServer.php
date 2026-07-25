<?php

namespace Figurate\ControlPanel\Filament\Resources\ContextServers\Pages;

use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Figurate\ControlPanel\Filament\Resources\ContextServers\ContextServerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditContextServer extends EditRecord
{
    protected static string $resource = ContextServerResource::class;

    protected ?string $selectedContextType = null;

    protected ?int $selectedContextId = null;

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
        $this->selectedContextType = is_string($data['channelable_type'] ?? null) ? $data['channelable_type'] : null;
        $this->selectedContextId = is_numeric($data['channelable_id'] ?? null) ? (int) $data['channelable_id'] : null;

        unset($data['channelable_type'], $data['channelable_id']);
        $data['driver'] = Channel::ProtocolMcp;
        $data['name'] = ($data['label'] ?? null) ?: ($data['server'] ?? null);

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        if (! $record instanceof Channel) {
            return;
        }

        $context = $this->resolveContextModel($this->selectedContextType, $this->selectedContextId);
        if (! $context instanceof Model || ! method_exists($context, 'channelRelations')) {
            return;
        }

        $context->channelRelations()->updateOrCreate(
            [
                'channel_id' => $record->id,
                'kind' => ChannelRelation::KindLink,
            ],
            [
                'status' => Channel::StatusActive,
                'direction' => Channel::DirectionBidirectional,
                'data' => [],
                'meta' => [],
            ],
        );
    }

    protected function resolveContextModel(?string $contextType, ?int $contextId): ?Model
    {
        if (! is_string($contextType) || ! is_int($contextId) || $contextId <= 0) {
            return null;
        }

        if ($contextType === (new User)->getMorphClass()) {
            return User::query()->find($contextId);
        }

        if ($contextType === (new Space)->getMorphClass()) {
            return Space::query()->find($contextId);
        }

        if ($contextType === (new Thread)->getMorphClass()) {
            return Thread::query()->find($contextId);
        }

        return null;
    }
}
