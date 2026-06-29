<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Channel\StoreChannelSkillMediaRequest;
use App\Models\Server\Channel;
use App\Models\Server\User;
use App\Support\Channels\ChannelAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ChannelSkillMediaController extends Controller
{
    public function __construct(
        protected ChannelAccess $channelAccess,
    ) {}

    public function storeForChannel(StoreChannelSkillMediaRequest $request, int $channel): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to add skills to this channel.');

        return response()->json([
            'data' => $this->mapMedia($this->storeSkillMedia($registeredChannel, $request)),
        ], 201);
    }

    public function storeForRoute(StoreChannelSkillMediaRequest $request, int $channel, int $route): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);
        $registeredRoute = $registeredChannel->routes()->findOrFail($route);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to add skills to this channel route.');

        return response()->json([
            'data' => $this->mapMedia($this->storeSkillMedia($registeredRoute, $request)),
        ], 201);
    }

    public function storeForAddress(StoreChannelSkillMediaRequest $request, int $channel, int $route, int $address): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $registeredChannel = Channel::query()->findOrFail($channel);
        $registeredRoute = $registeredChannel->routes()->findOrFail($route);
        $registeredAddress = $registeredRoute->addresses()->findOrFail($address);
        abort_unless($this->canManageChannel($actor, $registeredChannel), 403, 'Not authorized to add skills to this channel address.');

        return response()->json([
            'data' => $this->mapMedia($this->storeSkillMedia($registeredAddress, $request)),
        ], 201);
    }

    protected function storeSkillMedia(HasMedia $model, StoreChannelSkillMediaRequest $request): Media
    {
        $validated = $request->validated();
        $file = $request->file('file');
        $filename = $this->filename($validated, $file instanceof UploadedFile ? $file->getClientOriginalName() : null);
        $mediaAdder = $file instanceof UploadedFile
            ? $model->addMedia($file)->usingFileName($filename)
            : $model->addMediaFromString((string) $validated['content'])->usingFileName($filename);
        $disk = $this->stringValue($validated['disk'] ?? null);

        $mediaAdder
            ->usingName($this->stringValue($validated['name'] ?? null) ?? pathinfo($filename, PATHINFO_FILENAME))
            ->withCustomProperties($this->customProperties($validated, $filename));

        return $disk !== null
            ? $mediaAdder->toMediaCollection(Channel::SkillCollection, $disk)
            : $mediaAdder->toMediaCollection(Channel::SkillCollection);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function customProperties(array $attributes, string $filename): array
    {
        return [
            'skill_slug' => $this->stringValue($attributes['skill_slug'] ?? null) ?? pathinfo($filename, PATHINFO_FILENAME),
            'name' => $this->stringValue($attributes['name'] ?? null),
            'description' => $this->stringValue($attributes['description'] ?? null),
            'version' => $this->stringValue($attributes['version'] ?? null),
            'capabilities' => is_array($attributes['capabilities'] ?? null) ? array_values($attributes['capabilities']) : [],
            'meta' => is_array($attributes['meta'] ?? null) ? $attributes['meta'] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'uuid' => $media->uuid,
            'collection' => $media->collection_name,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'disk' => $media->disk,
            'size' => $media->size,
            'skill_slug' => $media->getCustomProperty('skill_slug'),
            'description' => $media->getCustomProperty('description'),
            'custom_properties' => $media->custom_properties,
            'created_at' => optional($media->created_at)?->toIso8601String(),
        ];
    }

    protected function canManageChannel(User $actor, Channel $channel): bool
    {
        return $this->channelAccess->canManage($actor, $channel);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function filename(array $attributes, ?string $fallback): string
    {
        $filename = $this->stringValue($attributes['filename'] ?? null)
            ?? $this->stringValue($fallback)
            ?? (($this->stringValue($attributes['skill_slug'] ?? null) ?? 'skill').'.md');

        return str_ends_with($filename, '.md') ? $filename : "{$filename}.md";
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
