<?php

namespace App\Http\Controllers\Signal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Signal\StoreConversationMessageRequest;
use App\Models\Server\Conversation;
use App\Models\Server\ConversationMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ConversationMessageController extends Controller
{
    public function store(StoreConversationMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('view', $conversation);
        Gate::authorize('create', ConversationMessage::class);

        ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'type' => 'text',
            'body' => $request->validated('body'),
            'attachments' => null,
            'meta' => null,
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
        ])->save();

        return redirect()
            ->route('signal.chat.show', $conversation)
            ->with('success', 'Message sent.');
    }
}
