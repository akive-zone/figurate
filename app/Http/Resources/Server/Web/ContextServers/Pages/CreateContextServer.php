<?php

namespace App\Http\Resources\Server\Web\ContextServers\Pages;

use App\Contracts\Users\UserRepository;
use App\Http\Resources\Server\Web\ContextServers\ContextServerResource;
use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateContextServer extends CreateRecord
{
    protected static string $resource = ContextServerResource::class;

    protected ?string $selectedContextType = null;

    protected ?int $selectedContextId = null;

    public function mount(): void
    {
        parent::mount();

        $this->prefillContextFromQuery();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        ContextServerResource::ensureContextSelectionAllowed(
            $data['channelable_type'] ?? null,
            $data['channelable_id'] ?? null,
        );

        $this->selectedContextType = is_string($data['channelable_type'] ?? null) ? $data['channelable_type'] : null;
        $this->selectedContextId = is_numeric($data['channelable_id'] ?? null) ? (int) $data['channelable_id'] : null;

        unset($data['channelable_type'], $data['channelable_id']);
        $data['driver'] = Channel::ProtocolMcp;
        $data['name'] = ($data['label'] ?? null) ?: ($data['server'] ?? null);

        return $data;
    }

    protected function afterCreate(): void
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

    protected function prefillContextFromQuery(): void
    {
        $contextType = $this->resolveContextType(request()->query('context_type'));
        $rawContextId = request()->query('context_id');

        if (! is_string($contextType) || ! is_string($rawContextId) || trim($rawContextId) === '') {
            return;
        }

        $contextId = $this->resolveContextId($contextType, trim($rawContextId));
        if (! is_int($contextId) || $contextId <= 0) {
            return;
        }

        $allowedContextIds = array_keys(ContextServerResource::contextIdOptions($contextType));
        if (! in_array($contextId, $allowedContextIds, true)) {
            return;
        }

        $this->form->fill([
            'channelable_type' => $contextType,
            'channelable_id' => $contextId,
        ]);
    }

    protected function resolveContextType(mixed $rawType): ?string
    {
        if (! is_string($rawType) || trim($rawType) === '') {
            return null;
        }

        $value = strtolower(trim($rawType));
        $userMorphClass = (new User)->getMorphClass();
        $spaceMorphClass = (new Space)->getMorphClass();
        $threadMorphClass = (new Thread)->getMorphClass();

        return match ($value) {
            'user', strtolower($userMorphClass) => $userMorphClass,
            'space', strtolower($spaceMorphClass) => $spaceMorphClass,
            'thread', strtolower($threadMorphClass) => $threadMorphClass,
            default => in_array(trim($rawType), [$userMorphClass, $spaceMorphClass, $threadMorphClass], true)
                ? trim($rawType)
                : null,
        };
    }

    protected function resolveContextId(string $contextType, string $rawContextId): ?int
    {
        if (ctype_digit($rawContextId)) {
            return (int) $rawContextId;
        }

        if ($contextType === (new User)->getMorphClass()) {
            return app(UserRepository::class)->findIdByUuid($rawContextId);
        }

        if ($contextType === (new Space)->getMorphClass()) {
            return Space::query()->where('uuid', $rawContextId)->value('id');
        }

        if ($contextType === (new Thread)->getMorphClass()) {
            return Thread::query()->where('uuid', $rawContextId)->value('id');
        }

        return null;
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
