<?php

namespace App\Http\Controllers\Signal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ChannelController extends Controller
{
    public function create(Request $request): Response
    {
        try {
            $profiles = $this->fetchProfiles($request);
        } catch (\Throwable) {
            $profiles = [];
        }

        return Inertia::render('Signal/Requests/Create', [
            'profiles' => $profiles,
            'server_base_url' => $this->clientServerBaseUrl($request),
        ]);
    }

    public function index(Request $request): Response
    {
        try {
            $channels = $this->fetchChannels($request);
        } catch (\Throwable) {
            $channels = [];
        }

        return Inertia::render('Signal/Channels/Index', [
            'channels' => $channels,
            'server_base_url' => $this->clientServerBaseUrl($request),
        ]);
    }

    public function show(Request $request, string $channel): Response
    {
        try {
            $channelPayload = $this->fetchChannel((int) $channel, $request);
        } catch (\Throwable) {
            $channelPayload = null;
        }

        return Inertia::render('Signal/Channels/Show', [
            'channel' => $channelPayload,
            'server_base_url' => $this->clientServerBaseUrl($request),
        ]);
    }

    protected function isNativeRuntime(): bool
    {
        return \app_is_native_runtime();
    }

    protected function clientServerBaseUrl(Request $request): string
    {
        if (! $this->isNativeRuntime()) {
            return '';
        }

        return rtrim((string) config('services.server.base_url'), '/');
    }

    protected function signalApiBaseUrl(Request $request): string
    {
        $configured = rtrim((string) config('services.server.base_url'), '/');

        if ($this->isNativeRuntime()) {
            return $configured;
        }

        if ($configured !== '') {
            return $configured;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '') {
            return $appUrl;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    protected function apiClient(Request $request): PendingRequest
    {
        $baseUrl = $this->signalApiBaseUrl($request);

        if ($baseUrl === '') {
            throw new RuntimeException('SERVER_BASE_URL is required for NativePHP runtime.');
        }

        $headers = [
            'Accept' => 'application/ld+json',
        ];

        if ($request->hasHeader('Cookie')) {
            $headers['Cookie'] = (string) $request->header('Cookie');
        }

        if ($request->hasHeader('Authorization')) {
            $headers['Authorization'] = (string) $request->header('Authorization');
        }

        if ($request->hasHeader('X-Device-Id')) {
            $headers['X-Device-Id'] = (string) $request->header('X-Device-Id');
        }

        return Http::baseUrl($baseUrl)->withHeaders($headers);
    }

    protected function fetchCollection(Request $request, string $path, array $query = []): array
    {
        $payload = $this->apiClient($request)->get($path, $query)->throw()->json();

        if (is_array($payload)) {
            if (isset($payload['member']) && is_array($payload['member'])) {
                return $payload['member'];
            }

            if (isset($payload['hydra:member']) && is_array($payload['hydra:member'])) {
                return $payload['hydra:member'];
            }
        }

        return [];
    }

    protected function fetchItem(Request $request, string $path): ?array
    {
        $payload = $this->apiClient($request)->get($path)->throw()->json();

        return is_array($payload) ? $payload : null;
    }

    protected function pick(array $value, array $keys, mixed $fallback = null): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $value) && $value[$key] !== null) {
                return $value[$key];
            }
        }

        return $fallback;
    }

    protected function parseResourceId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            if (preg_match('/\/(\d+)$/', $value, $matches) === 1) {
                return (int) $matches[1];
            }

            if (ctype_digit($value)) {
                return (int) $value;
            }
        }

        if (is_array($value)) {
            return $this->parseResourceId($value['id'] ?? $value['@id'] ?? null);
        }

        return null;
    }

    protected function fetchProfiles(Request $request): array
    {
        $profiles = $this->fetchCollection($request, '/api/signal/profiles', [
            'status' => 'approved',
            'order[created_at]' => 'desc',
        ]);

        return array_values(array_map(function (array $profile): array {
            return [
                'id' => $this->pick($profile, ['id']),
                'display_name' => $this->pick($profile, ['displayName', 'display_name']),
                'location' => $this->pick($profile, ['location']),
            ];
        }, $profiles));
    }

    protected function fetchChannels(Request $request): array
    {
        $channels = $this->fetchCollection($request, '/api/signal/channels', [
            'order[last_message_at]' => 'desc',
        ]);

        return array_values(array_map(function (array $channel) use ($request): array {
            $requestRef = $this->pick($channel, ['request', 'requests', 'request_id', 'requestId']);
            $requestId = is_array($requestRef)
                ? $this->parseResourceId($requestRef[0] ?? null)
                : $this->parseResourceId($requestRef);
            $profileId = $this->parseResourceId($this->pick($channel, ['profile', 'profile_id', 'profileId']));

            $requestItem = $requestId ? $this->fetchItem($request, "/api/signal/requests/{$requestId}") : null;
            $profileItem = $profileId ? $this->fetchItem($request, "/api/signal/profiles/{$profileId}") : null;

            $latestMessage = null;

            if ($requestId) {
                $messages = $this->fetchCollection($request, '/api/signal/messages', [
                    'messageable_type' => 'App\Models\Server\Request',
                    'messageable_id' => $requestId,
                    'order[created_at]' => 'desc',
                    'itemsPerPage' => 1,
                ]);

                $latestRawMessage = $messages[0] ?? null;

                if (is_array($latestRawMessage)) {
                    $latestMessage = [
                        'id' => $this->pick($latestRawMessage, ['id']),
                        'body' => $this->pick($latestRawMessage, ['body']),
                        'created_at' => $this->pick($latestRawMessage, ['createdAt', 'created_at']),
                        'sender_name' => null,
                    ];
                }
            }

            return [
                'id' => $this->pick($channel, ['id']),
                'status' => $this->pick($channel, ['status'], 'open'),
                'last_message_at' => $this->pick($channel, ['lastMessageAt', 'last_message_at']),
                'request' => $requestItem ? [
                    'id' => $this->pick($requestItem, ['id']),
                    'title' => $this->pick($requestItem, ['title']),
                    'status' => $this->pick($requestItem, ['status']),
                ] : null,
                'profile' => $profileItem ? [
                    'id' => $this->pick($profileItem, ['id']),
                    'display_name' => $this->pick($profileItem, ['displayName', 'display_name']),
                ] : null,
                'latest_message' => $latestMessage,
            ];
        }, $channels));
    }

    protected function fetchChannel(int $channelId, Request $request): ?array
    {
        $channel = $this->fetchItem($request, "/api/signal/channels/{$channelId}");

        if (! $channel) {
            return null;
        }

        $requestRef = $this->pick($channel, ['request', 'requests', 'request_id', 'requestId']);
        $requestId = is_array($requestRef)
            ? $this->parseResourceId($requestRef[0] ?? null)
            : $this->parseResourceId($requestRef);
        $profileId = $this->parseResourceId($this->pick($channel, ['profile', 'profile_id', 'profileId']));

        $requestItem = $requestId ? $this->fetchItem($request, "/api/signal/requests/{$requestId}") : null;
        $profileItem = $profileId ? $this->fetchItem($request, "/api/signal/profiles/{$profileId}") : null;

        $threadMessages = [];

        if ($requestId) {
            $messages = $this->fetchCollection($request, '/api/signal/messages', [
                'messageable_type' => 'App\Models\Server\Request',
                'messageable_id' => $requestId,
                'order[created_at]' => 'asc',
                'itemsPerPage' => 100,
            ]);

            $threadMessages = array_values(array_map(function (array $message): array {
                return [
                    'id' => $this->pick($message, ['id']),
                    'sender_name' => null,
                    'content' => $this->pick($message, ['body']),
                    'attachments' => $this->pick($message, ['attachments'], []),
                    'created_at' => $this->pick($message, ['createdAt', 'created_at']),
                ];
            }, $messages));
        }

        return [
            'id' => $this->pick($channel, ['id']),
            'status' => $this->pick($channel, ['status'], 'open'),
            'profile' => $profileItem ? [
                'id' => $this->pick($profileItem, ['id']),
                'display_name' => $this->pick($profileItem, ['displayName', 'display_name']),
            ] : null,
            'request' => $requestItem ? [
                'id' => $this->pick($requestItem, ['id']),
                'title' => $this->pick($requestItem, ['title']),
                'description' => $this->pick($requestItem, ['description']),
                'status' => $this->pick($requestItem, ['status']),
                'quotes' => [],
            ] : null,
            'threads' => [],
            'active_thread' => $request->integer('thread') ?: null,
            'agent_messages' => [],
            'thread_messages' => $threadMessages,
            'actions' => [
                'can_create_thread' => false,
                'can_prompt_agent' => true,
                'can_accept_quote' => false,
            ],
        ];
    }
}
