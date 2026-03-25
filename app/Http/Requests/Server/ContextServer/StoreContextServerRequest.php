<?php

namespace App\Http\Requests\Server\ContextServer;

use App\Support\Security\UrlTrustPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'context_type' => ['required', 'string', 'in:user,space,thread'],
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

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $transport = strtolower((string) $this->input('transport', 'remote'));

                if ($transport !== 'remote') {
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
