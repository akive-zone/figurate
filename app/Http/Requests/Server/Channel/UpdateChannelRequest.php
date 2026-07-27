<?php

namespace App\Http\Requests\Server\Channel;

use App\Models\Server\Channel;
use App\Support\Security\UrlTrustPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateChannelRequest extends FormRequest
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
            'protocol' => ['sometimes', 'string', 'max:40'],
            'name' => ['sometimes', 'string', 'max:120'],
            'label' => ['sometimes', 'nullable', 'string', 'max:160'],
            'enabled' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'transport' => ['sometimes', 'string', 'max:40'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', [Channel::StatusActive, Channel::StatusPaused, Channel::StatusDisabled])],
            'direction' => ['sometimes', 'string', 'in:'.implode(',', [Channel::DirectionInbound, Channel::DirectionOutbound, Channel::DirectionBidirectional])],
            'endpoint_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'handler' => ['sometimes', 'nullable', 'string', 'max:255'],
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
            'config' => ['sometimes', 'nullable', 'array'],
            'meta' => ['sometimes', 'nullable', 'array'],
            'data' => ['sometimes', 'nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name', $this->input('server'));
        $protocol = $this->input('protocol', $this->input('driver', $this->input('system')));
        $transport = $this->input('transport');

        if ($name !== null) {
            $this->merge(['name' => $name]);
        }

        if (is_string($protocol) && trim($protocol) !== '') {
            $protocol = strtolower(trim($protocol));

            $this->merge([
                'protocol' => $protocol,
                'driver' => $protocol,
                'system' => $protocol,
                'transport' => $transport,
            ]);
        }
    }

    /**
     * @return array<int, \Closure(Validator): void>
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
                    $this->trustPolicyConfig(),
                );

                if (! ($trust['allowed'] ?? false)) {
                    $validator->errors()->add('endpoint_url', (string) ($trust['reason'] ?? 'Remote endpoint URL is not allowed by policy.'));
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function trustPolicyConfig(): array
    {
        $channelTrust = config('services.channels.trust');

        if (is_array($channelTrust)) {
            return $channelTrust;
        }

        $mcpTrust = config('services.mcp.trust');

        return is_array($mcpTrust) ? $mcpTrust : [];
    }
}
