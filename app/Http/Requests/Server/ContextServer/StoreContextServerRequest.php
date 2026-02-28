<?php

namespace App\Http\Requests\Server\ContextServer;

use Illuminate\Foundation\Http\FormRequest;

class StoreContextServerRequest extends FormRequest
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
            'context_type' => ['required', 'string', 'in:user,channel,thread'],
            'context_id' => ['nullable', 'string'],
            'server' => ['required', 'string', 'max:120'],
            'label' => ['nullable', 'string', 'max:160'],
            'enabled' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'transport' => ['nullable', 'string', 'in:remote,local'],
            'endpoint_url' => ['nullable', 'url', 'required_if:transport,remote'],
            'handler' => ['nullable', 'string', 'required_if:transport,local'],
            'allowed_tools' => ['nullable', 'array'],
            'allowed_tools.*' => ['string', 'max:120'],
            'auth_type' => ['nullable', 'string', 'in:bearer,basic,header'],
            'credentials' => ['nullable', 'array'],
            'credentials.token' => ['nullable', 'string', 'required_if:auth_type,bearer'],
            'credentials.username' => ['nullable', 'string', 'required_if:auth_type,basic'],
            'credentials.password' => ['nullable', 'string', 'required_if:auth_type,basic'],
            'credentials.header_name' => ['nullable', 'string', 'required_if:auth_type,header'],
            'credentials.header_value' => ['nullable', 'string', 'required_if:auth_type,header'],
            'credentials.headers' => ['nullable', 'array'],
            'credentials.headers.*' => ['string'],
        ];
    }
}
