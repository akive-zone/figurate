<?php

namespace App\Http\Requests\Server\Acp;

use Illuminate\Foundation\Http\FormRequest;

class PromptAcpSessionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'channel_uuid' => $this->trimmedString(
                $this->input('channel_uuid')
                ?? $this->input('channel_id')
                ?? $this->input('channelId')
                ?? $this->input('workspaceId')
            ),
            'text' => $this->trimmedString(
                $this->input('text')
                ?? $this->input('prompt')
                ?? $this->input('input')
            ),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'channel_uuid' => ['nullable', 'uuid', 'exists:channels,uuid'],
            'text' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel_uuid.exists' => 'The selected channel was not found.',
            'text.required' => 'A text prompt is required.',
        ];
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
