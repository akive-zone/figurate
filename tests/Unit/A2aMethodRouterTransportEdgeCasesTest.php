<?php

namespace Tests\Unit;

use App\Ai\Support\A2a\A2aMethodRouter;
use App\Ai\Support\A2a\TaskPushNotificationDispatcher;
use App\Ai\Support\A2ui\A2uiCatalogRegistry;
use App\Ai\Support\A2ui\A2uiPayloadContract;
use App\Features\Actions\Chat\ResolveActiveThreadPresenters;
use App\Features\Actions\Chat\ResolveChatChannelContext;
use App\Features\Actions\Chat\ResolveChatThreadContext;
use App\Features\Operations\Chat\DispatchPromptOperation;
use App\Features\Operations\Chat\ResolveConversationThreadOperation;
use App\Models\Server\Message;
use App\Support\Orchestrate\AgentTaskService;
use App\Support\Orchestrate\MessageTaskService;
use PHPUnit\Framework\TestCase;

class A2aMethodRouterTransportEdgeCasesTest extends TestCase
{
    public function test_it_accepts_a2ui_mime_type_with_parameters_and_case_variants(): void
    {
        $router = $this->makeRouter();

        $actions = $router->publicResolveA2uiActions([
            'message' => [
                'parts' => [[
                    'kind' => 'data',
                    'metadata' => [
                        'mimeType' => 'Application/Json+A2Ui; charset=utf-8',
                    ],
                    'data' => [
                        'userAction' => [
                            'name' => 'submit_form',
                        ],
                    ],
                ]],
            ],
        ]);

        $this->assertCount(1, $actions);
        $this->assertSame('submit_form', $actions[0]['name']);
    }

    public function test_it_deduplicates_actions_across_content_and_parts(): void
    {
        $router = $this->makeRouter();

        $params = [
            'content' => [
                'actions' => [[
                    'protocol' => 'a2ui',
                    'name' => 'submit_form',
                    'id' => 'act-1',
                    'surfaceId' => 'surface-1',
                    'timestamp' => '2026-03-07T10:00:00Z',
                ]],
            ],
            'message' => [
                'parts' => [[
                    'kind' => 'data',
                    'metadata' => [
                        'mimeType' => 'application/json+a2ui',
                    ],
                    'data' => [
                        'action' => [
                            'protocol' => 'a2ui',
                            'name' => 'submit_form',
                            'id' => 'act-1',
                            'surfaceId' => 'surface-1',
                            'timestamp' => '2026-03-07T10:00:00Z',
                        ],
                    ],
                ]],
            ],
        ];

        $actions = $router->publicResolveA2uiActions($params);

        $this->assertCount(1, $actions);
    }

    public function test_it_limits_actions_to_transport_maximum_of_sixteen(): void
    {
        $router = $this->makeRouter();

        $entries = [];
        for ($index = 1; $index <= 30; $index++) {
            $entries[] = [
                'action' => [
                    'name' => "action_{$index}",
                    'id' => "action-id-{$index}",
                ],
            ];
        }

        $actions = $router->publicResolveA2uiActions([
            'message' => [
                'parts' => [[
                    'kind' => 'data',
                    'metadata' => [
                        'contentType' => 'application/json+a2ui;version=0.8',
                    ],
                    'data' => $entries,
                ]],
            ],
        ]);

        $this->assertCount(16, $actions);
        $this->assertSame('action_1', $actions[0]['name']);
        $this->assertSame('action_16', $actions[15]['name']);
    }

    public function test_it_deduplicates_and_limits_errors_to_transport_maximum(): void
    {
        $router = $this->makeRouter();

        $errorsFromParts = [];
        for ($index = 1; $index <= 20; $index++) {
            $errorsFromParts[] = [
                'error' => [
                    'code' => "ERR-{$index}",
                    'path' => 'form.email',
                    'message' => "Invalid value {$index}",
                ],
            ];
        }

        $errorsFromParts[] = [
            'error' => [
                'code' => 'ERR-1',
                'path' => 'form.email',
                'message' => 'Invalid value 1',
            ],
        ];

        $errors = $router->publicResolveA2uiErrors([
            'content' => [
                'errors' => [[
                    'code' => 'ERR-1',
                    'path' => 'form.email',
                    'message' => 'Invalid value 1',
                ]],
            ],
            'message' => [
                'parts' => [[
                    'kind' => 'data',
                    'metadata' => [
                        'mimeType' => 'application/json+a2ui',
                    ],
                    'data' => $errorsFromParts,
                ]],
            ],
        ]);

        $this->assertCount(16, $errors);
        $this->assertSame('ERR-1', $errors[0]['code']);
        $this->assertSame('ERR-16', $errors[15]['code']);
    }

    public function test_it_emits_data_artifact_when_text_is_empty_and_a2ui_payload_exists(): void
    {
        $router = $this->makeRouter();
        $promptMessage = new Message([
            'meta' => [],
        ]);
        $assistantMessage = new Message([
            'ulid' => '01JVD4H4R8J7E0BV3Q0YQ2WATN',
            'text' => '',
            'meta' => [
                'actor_key' => 'presenter',
                'a2ui' => [
                    'surface' => [
                        'id' => 'surface-1',
                        'title' => 'Verification',
                    ],
                ],
            ],
        ]);

        $artifact = $router->publicToTaskArtifactPayload($assistantMessage, $promptMessage);

        $this->assertSame('data', $artifact['kind']);
        $this->assertArrayNotHasKey('text', $artifact);
        $this->assertSame('data', $artifact['parts'][0]['kind']);
        $this->assertSame('application/json+a2ui', $artifact['parts'][0]['metadata']['mimeType']);
    }

    public function test_it_intersects_catalogs_with_client_supported_catalog_ids(): void
    {
        $registry = new class extends A2uiCatalogRegistry
        {
            public function supportedCatalogIds(): array
            {
                return ['catalog.a', 'catalog.b'];
            }

            public function catalogsByIds(array $catalogIds): array
            {
                return collect($catalogIds)
                    ->map(fn (string $catalogId): array => ['id' => $catalogId])
                    ->values()
                    ->all();
            }
        };

        $payload = [
            'surface' => [
                'components' => [[
                    'catalogId' => 'catalog.a',
                ], [
                    'catalogId' => 'catalog.b',
                ]],
            ],
        ];
        $decorated = $registry->decoratePayload($payload, [
            'acceptsInlineCatalogs' => false,
            'supportedCatalogIds' => ['catalog.a'],
        ]);

        $this->assertSame(['catalog.a'], $decorated['catalogRefs']);
    }

    protected function makeRouter(): A2aMethodRouter
    {
        return new class($this->createMock(ResolveConversationThreadOperation::class), $this->createMock(ResolveChatChannelContext::class), $this->createMock(ResolveChatThreadContext::class), $this->createMock(TaskPushNotificationDispatcher::class), new A2uiPayloadContract, new A2uiCatalogRegistry, $this->createMock(DispatchPromptOperation::class), $this->createMock(ResolveActiveThreadPresenters::class), new AgentTaskService(new MessageTaskService), new MessageTaskService) extends A2aMethodRouter
        {
            /**
             * @param  array<string, mixed>  $params
             * @return array<int, array<string, mixed>>
             */
            public function publicResolveA2uiActions(array $params): array
            {
                return $this->resolveA2uiActions($params);
            }

            /**
             * @param  array<string, mixed>  $params
             * @return array<int, array<string, mixed>>
             */
            public function publicResolveA2uiErrors(array $params): array
            {
                return $this->resolveA2uiErrors($params);
            }

            /**
             * @return array<string, mixed>
             */
            public function publicToTaskArtifactPayload(Message $message, Message $promptMessage): array
            {
                return $this->toTaskArtifactPayload($message, $promptMessage);
            }
        };
    }
}
