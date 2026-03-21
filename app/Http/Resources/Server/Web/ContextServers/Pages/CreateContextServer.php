<?php

namespace App\Http\Resources\Server\Web\ContextServers\Pages;

use App\Contracts\Users\UserRepository;
use App\Http\Resources\Server\Web\ContextServers\ContextServerResource;
use App\Models\Server\Channel;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Filament\Resources\Pages\CreateRecord;

class CreateContextServer extends CreateRecord
{
    protected static string $resource = ContextServerResource::class;

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
            $data['contextable_type'] ?? null,
            $data['contextable_id'] ?? null,
        );

        return $data;
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
            'contextable_type' => $contextType,
            'contextable_id' => $contextId,
        ]);
    }

    protected function resolveContextType(mixed $rawType): ?string
    {
        if (! is_string($rawType) || trim($rawType) === '') {
            return null;
        }

        $value = strtolower(trim($rawType));
        $userMorphClass = (new User)->getMorphClass();
        $channelMorphClass = (new Channel)->getMorphClass();
        $threadMorphClass = (new Thread)->getMorphClass();

        return match ($value) {
            'user', strtolower($userMorphClass) => $userMorphClass,
            'channel', strtolower($channelMorphClass) => $channelMorphClass,
            'thread', strtolower($threadMorphClass) => $threadMorphClass,
            default => in_array(trim($rawType), [$userMorphClass, $channelMorphClass, $threadMorphClass], true)
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

        if ($contextType === (new Channel)->getMorphClass()) {
            return Channel::query()->where('uuid', $rawContextId)->value('id');
        }

        if ($contextType === (new Thread)->getMorphClass()) {
            return Thread::query()->where('uuid', $rawContextId)->value('id');
        }

        return null;
    }
}
