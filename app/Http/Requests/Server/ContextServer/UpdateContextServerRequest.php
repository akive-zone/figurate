<?php

namespace App\Http\Requests\Server\ContextServer;

use App\Support\Security\UrlTrustPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateContextServerRequest extends FormRequest
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
            'label' => ['sometimes', 'nullable', 'string', 'max:160'],
            'enabled' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'transport' => ['sometimes', 'string', 'in:remote,local'],
            'endpoint_url' => ['sometimes', 'nullable', 'url'],
            'handler' => ['sometimes', 'nullable', 'string'],
            'allowed_tools' => ['sometimes', 'nullable', 'array'],
            'allowed_tools.*' => ['string', 'max:120'],
            'auth_type' => ['sometimes', 'nullable', 'string', 'in:bearer,basic,header'],
            'credentials' => ['sometimes', 'nullable', 'array'],
            'credentials.token' => ['nullable', 'string'],
            'credentials.username' => ['nullable', 'string'],
            'credentials.password' => ['nullable', 'string'],
            'credentials.header_name' => ['nullable', 'string'],
            'credentials.header_value' => ['nullable', 'string'],
            'credentials.headers' => ['nullable', 'array'],
            'credentials.headers.*' => ['string'],
        ];
    }

    /**
     * @return array<int, \Closure(\Illuminate\Validation\Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->has('endpoint_url')) {
                    return;
                }

                $endpointUrl = $this->input('endpoint_url');

                if (! is_string($endpointUrl) || trim($endpointUrl) === '') {
                    return;
                }

                $trust = app(UrlTrustPolicy::class)->authorize(
                    $endpointUrl,
                    is_array(config('services.mcp.trust')) ? config('services.mcp.trust') : [],
                );

                if (! ($trust['allowed'] ?? false)) {
                    $validator->errors()->add('endpoint_url', (string) ($trust['reason'] ?? 'Remote endpoint URL is not allowed by policy.'));
                }
            },
        ];
    }
}
