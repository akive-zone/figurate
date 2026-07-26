<?php

namespace App\Features\Actions\Chat;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class ResolveConversationAttachments
{
    /**
     * @param  array<int, mixed>  $attachments
     * @return Collection<int, array{path: string, original_name: string}>
     */
    public function execute(array $attachments): Collection
    {
        return collect($attachments)
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file): array => [
                'path' => (string) $file->getRealPath(),
                'original_name' => $file->getClientOriginalName(),
            ])
            ->filter(fn (array $attachment): bool => $attachment['path'] !== '' && $attachment['original_name'] !== '')
            ->values();
    }
}
