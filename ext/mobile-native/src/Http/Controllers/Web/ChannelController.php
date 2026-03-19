<?php

namespace Figurate\MobileNative\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ChannelController extends Controller
{
    public function index(Request $request): Response
    {
        try {
            $channels = $this->fetchChannels($request);
        } catch (\Throwable) {
            $channels = [];
        }

        return Inertia::render('Channels/Index', [
            'channels' => $channels,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->index($request);
    }

    public function show(Request $request, string $channel): Response
    {
        return $this->renderChannel($request, $channel, is_string($request->query('thread')) ? (string) $request->query('thread') : null);
    }

    public function showThread(Request $request, string $channel, string $thread): Response
    {
        return $this->renderChannel($request, $channel, $thread);
    }

    protected function renderChannel(Request $request, string $channel, ?string $thread): Response
    {
        if ($thread !== null && $thread !== '') {
            $request->query->set('thread', $thread);
        }

        try {
            $channelPayload = $this->fetchChannel($channel, $request);
        } catch (RequestException $exception) {
            if ($exception->response?->status() === 404) {
                abort(404);
            }

            throw $exception;
        }

        if ($channelPayload === null) {
            abort(404);
        }

        try {
            $channels = $this->fetchChannels($request);
        } catch (\Throwable) {
            $channels = [];
        }

        return Inertia::render('Channels/Show', [
            'channels' => $channels,
            'channel' => $channelPayload,
        ]);
    }

    protected function chatApiBaseUrl(): string
    {
        return rtrim((string) config('app.server.base_url'), '/');
    }

    protected function apiClient(Request $request): PendingRequest
    {
        $baseUrl = $this->chatApiBaseUrl();

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

    protected function fetchChannels(Request $request): array
    {
        $channels = $this->fetchCollection($request, '/api/platform/channels', [
            'order[created_at]' => 'desc',
        ]);

        return array_values(array_map(function (array $channel) use ($request): array {
            $requestRef = $this->pick($channel, ['request', 'requests', 'request_id', 'requestId']);
            $requestId = is_array($requestRef)
                ? $this->parseResourceId($requestRef[0] ?? null)
                : $this->parseResourceId($requestRef);

            $requestItem = $requestId ? $this->fetchItem($request, "/api/platform/requests/{$requestId}") : null;

            $latestMessage = null;

            if ($requestId) {
                $messages = $this->fetchCollection($request, '/api/platform/messages', [
                    'messageable_type' => 'Figurate\FulfillmentManager\Models\Request',
                    'messageable_id' => $requestId,
                    'order[created_at]' => 'desc',
                    'itemsPerPage' => 1,
                ]);

                $latestRawMessage = $messages[0] ?? null;

                if (is_array($latestRawMessage)) {
                    $latestMessage = [
                        'id' => $this->pick($latestRawMessage, ['id']),
                        'text' => $this->pick($latestRawMessage, ['text']),
                        'created_at' => $this->pick($latestRawMessage, ['createdAt', 'created_at']),
                        'sender_name' => null,
                    ];
                }
            }

            return [
                'id' => $this->pick($channel, ['uuid']),
                'status' => $this->pick($channel, ['status'], 'open'),
                'last_message_at' => $this->pick($latestMessage ?? [], ['created_at'], $this->pick($channel, ['createdAt', 'created_at'])),
                'threads' => [],
                'request' => $requestItem ? [
                    'id' => $this->pick($requestItem, ['id']),
                    'title' => $this->pick($requestItem, ['title']),
                    'status' => $this->pick($requestItem, ['status']),
                ] : null,
                'latest_message' => $latestMessage,
            ];
        }, $channels));
    }

    protected function fetchChannel(string $channelUuid, Request $request): ?array
    {
        $channels = $this->fetchCollection($request, '/api/platform/channels', [
            'uuid' => $channelUuid,
            'itemsPerPage' => 1,
        ]);

        if ($channels === []) {
            $channels = $this->fetchCollection($request, '/api/platform/channels', [
                'itemsPerPage' => 100,
            ]);
        }

        $channel = collect($channels)->first(function (array $item) use ($channelUuid): bool {
            return ($item['uuid'] ?? null) === $channelUuid;
        });

        if (! $channel) {
            return null;
        }

        $requestRef = $this->pick($channel, ['request', 'requests', 'request_id', 'requestId']);
        $requestId = is_array($requestRef)
            ? $this->parseResourceId($requestRef[0] ?? null)
            : $this->parseResourceId($requestRef);

        $requestItem = $requestId ? $this->fetchItem($request, "/api/platform/requests/{$requestId}") : null;

        $threadMessages = [];

        if ($requestId) {
            $messages = $this->fetchCollection($request, '/api/platform/messages', [
                'messageable_type' => 'Figurate\FulfillmentManager\Models\Request',
                'messageable_id' => $requestId,
                'order[created_at]' => 'asc',
                'itemsPerPage' => 100,
            ]);

            $threadMessages = array_values(array_map(function (array $message): array {
                return [
                    'id' => $this->pick($message, ['id']),
                    'sender_name' => null,
                    'content' => $this->pick($message, ['text']),
                    'attachments' => $this->pick($message, ['attachments'], []),
                    'created_at' => $this->pick($message, ['createdAt', 'created_at']),
                ];
            }, $messages));
        }

        return [
            'id' => $this->pick($channel, ['uuid']),
            'status' => $this->pick($channel, ['status'], 'open'),
            'request' => $requestItem ? [
                'id' => $this->pick($requestItem, ['id']),
                'title' => $this->pick($requestItem, ['title']),
                'description' => $this->pick($requestItem, ['description']),
                'status' => $this->pick($requestItem, ['status']),
                'quotes' => [],
            ] : null,
            'threads' => [],
            'active_thread' => is_string($request->query('thread')) ? (string) $request->query('thread') : null,
            'channel_feed' => $threadMessages,
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
