<?php

namespace App\Http\Requests\Signal;

use Illuminate\Foundation\Http\FormRequest;

class StoreChannelThreadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', 'max:40', 'in:main,planning,execution,billing,dispute,support,system'],
            'phase' => ['required', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:120'],
            'handler_actor' => ['nullable', 'string', 'max:80'],
        ];
    }
}
