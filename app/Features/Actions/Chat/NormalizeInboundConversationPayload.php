<?php

namespace App\Features\Actions\Chat;

class NormalizeInboundConversationPayload
{
    /**
     * @param  array<string, mixed>  $contentPayload
     * @param  array<string, mixed>  $extraPayload
     * @return array{
     *     text: ?string,
     *     actions: array<int, array<string, mixed>>,
     *     errors: array<int, array<string, mixed>>,
     * }
     */
    public function execute(array $contentPayload): array
    {
        $actions = collect($contentPayload['actions'] ?? [])
            ->filter(fn (mixed $action): bool => is_array($action))
            ->values()
            ->all();
        $errors = collect($contentPayload['errors'] ?? [])
            ->filter(fn (mixed $error): bool => is_array($error))
            ->values()
            ->all();
        $text = $this->trimmedString($contentPayload['text'] ?? null);

        return [
            'text' => $text ?? $this->composeFallbackBody($actions, $errors),
            'actions' => $actions,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int, array<string, mixed>>  $errors
     */
    protected function composeFallbackBody(array $actions, array $errors): ?string
    {
        if ($actions !== []) {
            $firstAction = collect($actions)->first(fn (mixed $action): bool => is_array($action));

            if (! is_array($firstAction)) {
                return 'Message actions submitted.';
            }

            $actionName = $this->trimmedString($firstAction['name'] ?? null);

            if ($actionName === null) {
                return 'Message actions submitted.';
            }

            return "Message actions submitted: {$actionName}";
        }

        if ($errors !== []) {
            $firstError = collect($errors)->first(fn (mixed $error): bool => is_array($error));

            if (! is_array($firstError)) {
                return 'Message errors reported.';
            }

            $errorMessage = $this->trimmedString($firstError['message'] ?? null);
            $errorCode = $this->trimmedString($firstError['code'] ?? null);

            if ($errorMessage !== null) {
                return "Message error: {$errorMessage}";
            }

            if ($errorCode !== null) {
                return "Message error code: {$errorCode}";
            }

            return 'Message errors reported.';
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
