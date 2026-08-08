<?php

namespace App\Http\Requests\Server\Post;

use App\Models\Server\Post;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreContextPostRequest extends FormRequest
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
            'type' => ['nullable', 'string', 'max:120'],
            'tag' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'text' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
            'meta' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type');
        if (! is_string($type) || trim($type) === '') {
            $this->merge(['type' => 'context']);
        } else {
            $this->merge(['type' => trim($type)]);
        }

        $status = $this->input('status');
        if (! is_string($status) || trim($status) === '') {
            $this->merge(['status' => Post::StatusActive]);
        } else {
            $this->merge(['status' => trim($status)]);
        }

        if (is_string($this->input('text'))) {
            $this->merge(['text' => trim($this->input('text'))]);
        }
    }
}
