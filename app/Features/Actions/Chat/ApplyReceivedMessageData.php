<?php

namespace App\Features\Actions\Chat;

use App\Models\Server\Post;

class ApplyReceivedMessageData
{
    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int, array<string, mixed>>  $errors
     */
    public function execute(
        Post $message,
        array $actions,
        array $errors,
    ): void {
        if ($actions === [] && $errors === []) {
            return;
        }

        $meta = is_array($message->meta) ? $message->meta : [];
        $meta['message_actions_received_at'] = now()->toIso8601String();

        $data = is_array($message->data) ? $message->data : [];

        if ($actions !== []) {
            $data['actions'] = $actions;
        }

        if ($errors !== []) {
            $data['errors'] = $errors;
        }

        $message->forceFill([
            'data' => $data !== [] ? $data : null,
            'meta' => $meta,
        ])->save();
    }
}
