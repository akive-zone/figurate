<?php

namespace App\Http\Requests\Server\Channel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreChannelSkillMediaRequest extends FormRequest
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
            'file' => ['nullable', 'file', 'max:10240'],
            'content' => ['nullable', 'string'],
            'filename' => ['nullable', 'string', 'max:160'],
            'disk' => ['nullable', 'string', 'max:80'],
            'skill_slug' => ['nullable', 'string', 'max:120'],
            'name' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'version' => ['nullable', 'string', 'max:80'],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', 'max:120'],
            'meta' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->hasFile('file') && ! is_string($this->input('content'))) {
                    $validator->errors()->add('file', 'Provide a skill file or skill content.');
                }
            },
        ];
    }
}
