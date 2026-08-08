<?php

namespace App\Http\Requests\Server\Post;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListContextPostsRequest extends FormRequest
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
            'cursor' => ['nullable', 'string', 'max:2048'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
