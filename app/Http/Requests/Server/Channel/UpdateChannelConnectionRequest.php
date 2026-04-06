<?php

namespace App\Http\Requests\Server\Channel;

use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Support\Channels\ChannelDriverRegistry;
use App\Support\Security\UrlTrustPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateChannelConnectionRequest extends FormRequest
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
            'kind' => ['sometimes', 'string', 'in:'.implode(',', [ChannelRelation::KindLink, ChannelRelation::KindBind])],
            'status' => ['sometimes', 'string', 'in:'.implode(',', [Channel::StatusActive, Channel::StatusPaused, Channel::StatusDisabled])],
            'direction' => ['sometimes', 'string', 'in:'.implode(',', [Channel::DirectionInbound, Channel::DirectionOutbound, Channel::DirectionBidirectional])],
            'protocol' => ['sometimes', 'nullable', 'string', 'max:40'],
            'config' => ['sometimes', 'nullable', 'array'],
            'config.protocol' => ['sometimes', 'nullable', 'string', 'max:40'],
            'config.transport' => ['sometimes', 'nullable', 'string', 'max:40'],
            'config.mode' => ['sometimes', 'nullable', 'string', 'max:40'],
            'config.endpoint_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'config.handler' => ['sometimes', 'nullable', 'string', 'max:255'],
            'config.auth_type' => ['sometimes', 'nullable', 'string', 'in:bearer,basic,header'],
            'config.credentials' => ['sometimes', 'nullable', 'array'],
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
            'meta' => ['sometimes', 'nullable', 'array'],
            'data' => ['sometimes', 'nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $transport = $this->input('transport');
        $mode = $this->input('mode');
        $config = (array) $this->input('config', []);

        if (is_string($transport) && in_array(strtolower(trim($transport)), ['remote', 'local'], true) && ! is_string($mode)) {
            $mode = $transport;
            $transport = null;
        }

        if (is_string($transport)) {
            $config['transport'] = trim($transport);
        }

        if (is_string($mode)) {
            $config['mode'] = trim($mode);
        }

        if ($this->has('protocol')) {
            $config['protocol'] = $this->input('protocol');
        }

        if ($this->has('endpoint_url')) {
            $config['endpoint_url'] = $this->input('endpoint_url');
        }

        if ($this->has('handler')) {
            $config['handler'] = $this->input('handler');
        }

        if ($this->has('auth_type')) {
            $config['auth_type'] = $this->input('auth_type');
        }

        if ($this->has('credentials')) {
            $config['credentials'] = $this->input('credentials');
        }

        $this->merge([
            'config' => array_filter($config, fn (mixed $value): bool => $value !== null),
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
                $protocol = strtolower(trim((string) $this->input('config.protocol', '')));
                $endpointUrl = $this->input('config.endpoint_url');
                $handler = $this->input('config.handler');
                $command = $this->input('config.command');

                if ($transport !== '' && in_array($transport, ['http', 'webhook', 'websocket', 'webrtc', 'relay'], true)) {
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

                if ($mode === 'local' && $transport !== 'stdio' && ($this->has('mode') || $this->has('config.mode')) && (! is_string($handler) || trim($handler) === '')) {
                    $validator->errors()->add('config.handler', 'A local handler is required when the connection mode is local.');
                }

                $channelId = $this->route('channel');
                $channel = is_numeric($channelId) ? Channel::query()->find((int) $channelId) : null;

                if ($channel instanceof Channel && $transport !== '') {
                    $driver = app(ChannelDriverRegistry::class)->resolveByChannel($channel);
                    $supportedTransports = $driver->supportedTransports();

                    if (! in_array($transport, $supportedTransports, true)) {
                        $validator->errors()->add('config.transport', "The selected transport is not supported for the [{$channel->protocolKey()}] channel protocol.");
                    }
                }

                if ($channel instanceof Channel && $protocol !== '') {
                    $supportedProtocols = app(ChannelDriverRegistry::class)
                        ->resolveByChannel($channel)
                        ->supportedProtocols();

                    if ($supportedProtocols !== [] && ! in_array($protocol, $supportedProtocols, true)) {
                        $validator->errors()->add('config.protocol', "The selected protocol is not supported for the [{$channel->protocolKey()}] channel protocol.");
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
