<?php

namespace App\Http\Controllers\Api;

use App\Features\Operations\Chat\SubmitChatMessageOperation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Server\Form\FormRequest;
use App\Models\Server\User;
use Illuminate\Http\JsonResponse;

class FormController extends Controller
{
    public function store(
        FormRequest $request,
        SubmitChatMessageOperation $submitChatMessageOperation,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();
        $validated = $request->validated();
        $attachments = $request->file('content.attachments', []);

        $result = $submitChatMessageOperation->run([
            'actor' => $actor,
            'space' => $validated['space'] ?? null,
            'thread' => $validated['thread'] ?? null,
            'content' => is_array($validated['content'] ?? null) ? $validated['content'] : [],
            'extra' => is_array($validated['extra'] ?? null) ? $validated['extra'] : [],
            'attachments' => is_array($attachments) ? $attachments : [$attachments],
            'idempotency_key' => $request->header('X-Idempotency-Key'),
        ]);

        return response()->json($result['body'], $result['status']);
    }
}
