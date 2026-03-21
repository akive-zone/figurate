<?php

namespace App\Http\Requests\Server\Passkey;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePasskeyRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
            'passkey' => ['required', 'json'],
            'ceremony_id' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'passkey.required' => 'A passkey response is required.',
            'passkey.json' => 'The passkey response must be valid JSON.',
            'ceremony_id.required' => 'A passkey ceremony is required.',
        ];
    }
}
