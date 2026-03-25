<?php

namespace App\Features\Actions\Conversation;

use App\Models\Server\Message;

class ApplyReceivedMessageA2uiMetadata
{
    /**
     * @param  array<int, array<string, mixed>>  $a2uiActions
     * @param  array<int, array<string, mixed>>  $a2uiErrors
     * @param  array<string, mixed>|null  $a2uiClientCapabilities
     */
    public function execute(
        Message $message,
        array $a2uiActions,
        array $a2uiErrors,
        ?string $a2uiClientDataModel,
        ?array $a2uiClientCapabilities,
    ): void {
        if ($a2uiActions === [] && $a2uiErrors === [] && $a2uiClientDataModel === null && $a2uiClientCapabilities === null) {
            return;
        }

        $meta = is_array($message->meta) ? $message->meta : [];

        if ($a2uiClientDataModel !== null) {
            $meta['a2ui_client_data_model'] = $a2uiClientDataModel;
        }
        if (is_array($a2uiClientCapabilities)) {
            $meta['a2ui_client_capabilities'] = $a2uiClientCapabilities;
        }
        $meta['a2ui_actions_received_at'] = now()->toIso8601String();

        $message->forceFill([
            'actions' => $a2uiActions !== [] ? $a2uiActions : $message->actions,
            'errors' => $a2uiErrors !== [] ? $a2uiErrors : $message->errors,
            'meta' => $meta,
        ])->save();
    }
}
