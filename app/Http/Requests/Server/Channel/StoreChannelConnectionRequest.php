<?php

namespace App\Http\Requests\Server\Channel;

use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Support\Channels\ChannelDriverRegistry;
use App\Support\Security\UrlTrustPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreChannelConnectionRequest extends FormRequest
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
            'kind' => ['nullable', 'string', 'in:'.implode(',', [ChannelRelation::KindLink, ChannelRelation::KindBind])],
            'status' => ['nullable', 'string', 'in:'.implode(',', [Channel::StatusActive, Channel::StatusPaused, Channel::StatusDisabled])],
            'direction' => ['nullable', 'string', 'in:'.implode(',', [Channel::DirectionInbound, Channel::DirectionOutbound, Channel::DirectionBidirectional])],
            'config' => ['nullable', 'array'],
            'config.transport' => ['nullable', 'string', 'max:40'],
            'config.mode' => ['nullable', 'string', 'max:40'],
            'config.endpoint_url' => ['nullable', 'url'],
            'config.handler' => ['nullable', 'string', 'max:255'],
            'config.auth_type' => ['nullable', 'string', 'in:bearer,basic,header'],
            'config.credentials' => ['nullable', 'array'],
            'config.credentials.token' => ['nullable', 'string'],
            'config.credentials.username' => ['nullable', 'string'],
            'config.credentials.password' => ['nullable', 'string'],
            'config.credentials.header_name' => ['nullable', 'string'],
            'config.credentials.header_value' => ['nullable', 'string'],
            'config.credentials.headers' => ['nullable', 'array'],
            'config.credentials.headers.*' => ['string'],
            'config.command' => ['nullable', 'string', 'max:255'],
            'config.args' => ['nullable', 'array'],
            'config.args.*' => ['string'],
            'config.env' => ['nullable', 'array'],
            'config.env.*' => ['string'],
            'config.cwd' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
            'data' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $ownerType = $this->input('owner_type', $this->input('context_type'));
        $ownerId = $this->input('owner_id', $this->input('context_id'));
        $transport = $this->input('transport');
        $mode = $this->input('mode');

        if (is_string($transport) && in_array(strtolower(trim($transport)), ['remote', 'local'], true) && ! is_string($mode)) {
            $mode = $transport;
            $transport = null;
        }

        $this->merge([
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'config' => array_filter([
                ...((array) $this->input('config', [])),
                'transport' => is_string($transport) ? trim($transport) : null,
                'mode' => is_string($mode) ? trim($mode) : null,
                'endpoint_url' => $this->input('endpoint_url'),
                'handler' => $this->input('handler'),
                'auth_type' => $this->input('auth_type'),
                'credentials' => $this->input('credentials'),
            ], fn (mixed $value): bool => $value !== null),
        ]);
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $transport = strtolower(trim((string) $this->input('config.transport', '')));
                $mode = strtolower(trim((string) $this->input('config.mode', '')));
                $endpointUrl = $this->input('config.endpoint_url');
                $handler = $this->input('config.handler');
                $command = $this->input('config.command');

                if (in_array($transport, ['http', 'webhook', 'websocket', 'webrtc', 'relay'], true)) {
                    if (! is_string($endpointUrl) || trim($endpointUrl) === '') {
                        $validator->errors()->add('config.endpoint_url', 'An endpoint URL is required for the selected transport.');
                    } else {
                        $trust = app(UrlTrustPolicy::class)->authorize(
                            $endpointUrl,
                            $this->trustPolicyConfig(),
                        );

                        if (! ($trust['allowed'] ?? false)) {
                            $validator->errors()->add('config.endpoint_url', (string) ($trust['reason'] ?? 'Remote endpoint URL is not allowed by policy.'));
                        }
                    }
                }

                if ($transport === 'stdio' && (! is_string($command) || trim($command) === '')) {
                    $validator->errors()->add('config.command', 'A launcher command is required for stdio transport.');
                }

                if ($mode === 'local' && $transport !== 'stdio' && (! is_string($handler) || trim($handler) === '')) {
                    $validator->errors()->add('config.handler', 'A local handler is required when the connection mode is local.');
                }

                $channelId = $this->route('channel');
                $channel = is_numeric($channelId) ? Channel::query()->find((int) $channelId) : null;

                if ($channel instanceof Channel && $transport !== '') {
                    $supportedTransports = app(ChannelDriverRegistry::class)
                        ->resolveByChannel($channel)
                        ->supportedTransports();

                    if (! in_array($transport, $supportedTransports, true)) {
                        $validator->errors()->add('config.transport', "The selected transport is not supported for the [{$channel->driver}] channel driver.");
                    }
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
