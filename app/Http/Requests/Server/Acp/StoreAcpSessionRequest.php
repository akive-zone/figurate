<?php

namespace App\Http\Requests\Server\Acp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcpSessionRequest extends FormRequest
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
            'title' => $this->trimmedString($this->input('title') ?? $this->input('name')),
            'purpose' => $this->trimmedString($this->input('purpose')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'channel_uuid' => ['required', 'uuid', 'exists:channels,uuid'],
            'title' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', Rule::in([
                'main',
                'planning',
                'execution',
                'billing',
                'dispute',
                'support',
                'system',
            ])],
        ];
    }

    public function messages(): array
    {
        return [
            'channel_uuid.required' => 'A channel is required to create an ACP session.',
            'channel_uuid.exists' => 'The selected channel was not found.',
            'purpose.in' => 'The selected ACP session purpose is invalid.',
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
