<?php

namespace App\Http\Requests\Server\Channel;

use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Support\Security\UrlTrustPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreChannelRequest extends FormRequest
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
            'owner_type' => ['required', 'string', 'in:user,space,thread'],
            'owner_id' => ['nullable', 'string'],
            'driver' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'label' => ['nullable', 'string', 'max:160'],
            'kind' => ['nullable', 'string', 'in:'.implode(',', [ChannelRelation::KindLink, ChannelRelation::KindBind])],
            'enabled' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'transport' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string', 'in:'.implode(',', [Channel::StatusActive, Channel::StatusPaused, Channel::StatusDisabled])],
            'direction' => ['nullable', 'string', 'in:'.implode(',', [Channel::DirectionInbound, Channel::DirectionOutbound, Channel::DirectionBidirectional])],
            'endpoint_url' => ['nullable', 'url'],
            'handler' => ['nullable', 'string', 'max:255'],
            'allowed_tools' => ['nullable', 'array'],
            'allowed_tools.*' => ['string', 'max:120'],
            'auth_type' => ['nullable', 'string', 'in:bearer,basic,header'],
            'credentials' => ['nullable', 'array'],
            'credentials.token' => ['nullable', 'string'],
            'credentials.username' => ['nullable', 'string'],
            'credentials.password' => ['nullable', 'string'],
            'credentials.header_name' => ['nullable', 'string'],
            'credentials.header_value' => ['nullable', 'string'],
            'credentials.headers' => ['nullable', 'array'],
            'credentials.headers.*' => ['string'],
            'config' => ['nullable', 'array'],
            'meta' => ['nullable', 'array'],
            'data' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $ownerType = $this->input('owner_type', $this->input('context_type'));
        $ownerId = $this->input('owner_id', $this->input('context_id'));
        $name = $this->input('name', $this->input('server'));
        $driver = $this->input('driver');

        if (! is_string($driver) || trim($driver) === '') {
            $driver = $this->has('server') ? Channel::DriverMcp : $driver;
        }

        $this->merge([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'name' => $name,
            'driver' => $driver,
        ]);
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $transport = strtolower((string) $this->input('transport', 'remote'));
                $endpointUrl = $this->input('endpoint_url');
                $handler = $this->input('handler');

                if (in_array($transport, ['remote', 'webhook', 'websocket', 'relay', 'http'], true)) {
                    if (! is_string($endpointUrl) || trim($endpointUrl) === '') {
                        $validator->errors()->add('endpoint_url', 'An endpoint URL is required for the selected transport.');

                        return;
                    }

                    $trust = app(UrlTrustPolicy::class)->authorize(
                        $endpointUrl,
                        $this->trustPolicyConfig(),
                    );

                    if (! ($trust['allowed'] ?? false)) {
                        $validator->errors()->add('endpoint_url', (string) ($trust['reason'] ?? 'Remote endpoint URL is not allowed by policy.'));
                    }
                }

                if ($transport === 'local' && (! is_string($handler) || trim($handler) === '')) {
                    $validator->errors()->add('handler', 'A local handler is required for the selected transport.');
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
