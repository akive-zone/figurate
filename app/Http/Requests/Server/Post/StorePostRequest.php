<?php

namespace App\Http\Requests\Server\Post;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'text' => ['nullable', 'string'],
            'payload' => ['nullable'],
            'meta' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    public function postPayload(): array
    {
        $payload = array_key_exists('payload', $this->all())
            ? $this->input('payload')
            : $this->except(['type', 'tag', 'status', 'text', 'meta', 'occurred_at']);

        $payload = $this->normalizePayload($payload);
        $rawContent = trim($this->getContent());

        if ($payload === [] && $rawContent !== '') {
            $payload = [
                'value' => $rawContent,
            ];
        }

        $text = $this->input('text');

        if (is_string($text)) {
            $payload['text'] = $text;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function postMeta(): array
    {
        $meta = $this->input('meta');

        return is_array($meta) ? $meta : [];
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function normalizePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if ($payload === null) {
            return [];
        }

        return [
            'value' => $payload,
        ];
    }
}
