<?php

namespace App\Features\Actions\Chat;

use App\Ai\Support\A2ui\A2uiPayloadContract;

class NormalizeInboundConversationPayload
{
    public function __construct(protected A2uiPayloadContract $a2uiPayloadContract) {}

    /**
     * @param  array<string, mixed>  $contentPayload
     * @param  array<string, mixed>  $extraPayload
     * @return array{
     *     text: ?string,
     *     actions: array<int, array<string, mixed>>,
     *     errors: array<int, array<string, mixed>>,
     *     client_data_model: ?string,
     *     client_capabilities: array<string, mixed>|null
     * }
     */
    public function execute(array $contentPayload, array $extraPayload): array
    {
        $actions = collect($contentPayload['actions'] ?? [])
            ->map(fn (mixed $action): ?array => is_array($action) ? $this->a2uiPayloadContract->normalizeAction($action) : null)
            ->filter(fn (mixed $action): bool => is_array($action))
            ->values()
            ->all();
        $errors = collect($contentPayload['errors'] ?? [])
            ->map(fn (mixed $error): ?array => is_array($error) ? $this->a2uiPayloadContract->normalizeError($error) : null)
            ->filter(fn (mixed $error): bool => is_array($error))
            ->values()
            ->all();
        $text = $this->trimmedString($contentPayload['text'] ?? null);

        return [
            'text' => $text ?? $this->composeA2uiFallbackBody($actions, $errors),
            'actions' => $actions,
            'errors' => $errors,
            'client_data_model' => $this->trimmedString(data_get($extraPayload, 'a2ui.config.a2uiClientDataModel')),
            'client_capabilities' => $this->a2uiPayloadContract->normalizeClientCapabilities(
                is_array(data_get($extraPayload, 'a2ui.config.a2uiClientCapabilities'))
                    ? data_get($extraPayload, 'a2ui.config.a2uiClientCapabilities')
                    : null
            ),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int, array<string, mixed>>  $errors
     */
    protected function composeA2uiFallbackBody(array $actions, array $errors): ?string
    {
        if ($actions !== []) {
            $firstAction = collect($actions)->first(fn (mixed $action): bool => is_array($action));

            if (! is_array($firstAction)) {
                return 'A2UI actions submitted.';
            }

            $actionName = $this->trimmedString($firstAction['name'] ?? null);

            if ($actionName === null) {
                return 'A2UI actions submitted.';
            }

            return "A2UI actions submitted: {$actionName}";
        }

        if ($errors !== []) {
            $firstError = collect($errors)->first(fn (mixed $error): bool => is_array($error));

            if (! is_array($firstError)) {
                return 'A2UI client errors reported.';
            }

            $errorMessage = $this->trimmedString($firstError['message'] ?? null);
            $errorCode = $this->trimmedString($firstError['code'] ?? null);

            if ($errorMessage !== null) {
                return "A2UI client error: {$errorMessage}";
            }

            if ($errorCode !== null) {
                return "A2UI client error code: {$errorCode}";
            }

            return 'A2UI client errors reported.';
        }

        return null;
    }

    protected function trimmedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
