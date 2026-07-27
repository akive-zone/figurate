<?php

namespace App\Http\Controllers\Api;

use App\Features\Operations\Chat\SubmitChatMessageOperation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Form\FormRequest;
use App\Models\Server\Post;
use App\Models\Server\User;
use App\Support\Graph\GraphNodeService;
use App\Support\Graph\NodeFormer;
use Illuminate\Http\JsonResponse;

class FormController extends Controller
{
    public function store(
        FormRequest $request,
        SubmitChatMessageOperation $submitChatMessageOperation,
        NodeFormer $nodeFormer,
        GraphNodeService $graphNodes,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $body = $validated['body'];
        $attributes = is_array($body['attributes'] ?? null) ? $body['attributes'] : [];

        if ($this->formsThreadMessage($body, $attributes)) {
            $attachments = $request->file('body.attributes.attachments', []);
            $result = $submitChatMessageOperation->run([
                'actor' => $actor,
                'thread' => data_get($body, 'parent.id'),
                'content' => [
                    'text' => $attributes['text'] ?? null,
                    'actions' => is_array($attributes['actions'] ?? null) ? $attributes['actions'] : [],
                    'errors' => is_array($attributes['errors'] ?? null) ? $attributes['errors'] : [],
                ],
                'extra' => is_array($attributes['extra'] ?? null) ? $attributes['extra'] : [],
                'attachments' => is_array($attachments) ? $attachments : [$attachments],
                'idempotency_key' => $request->header('Idempotency-Key')
                    ?? $request->header('X-Idempotency-Key'),
            ]);
            $relations = is_array($body['relations'] ?? null) ? $body['relations'] : [];

            if ($relations !== [] && is_string($result['body']['post_id'] ?? null)) {
                $post = $graphNodes->resolve($actor, 'post', $result['body']['post_id'], true);
                $result['body']['relations'] = $nodeFormer->formRelations($actor, $post, $relations);
            }

            return response()->json($result['body'], $result['status']);
        }

        $result = $nodeFormer->form($actor, $body);

        return response()->json([
            'data' => $graphNodes->map($result['node'], $actor),
            'relations' => $result['relations'],
            'formed' => true,
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $attributes
     */
    protected function formsThreadMessage(array $body, array $attributes): bool
    {
        return ($body['type'] ?? null) === 'post'
            && ! is_string($body['id'] ?? null)
            && data_get($body, 'parent.type') === 'thread'
            && ($attributes['post_type'] ?? Post::TypeMessage) === Post::TypeMessage;
    }
}
