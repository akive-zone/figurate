<?php

namespace App\Http\Controllers\Signal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Signal\StoreSignalRequestConversationRequest;
use App\Models\Server\Conversation;
use App\Models\Server\ConversationMessage;
use App\Models\Server\Profile;
use App\Models\Server\Request as ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RequestConversationController extends Controller
{
    public function create(): Response
    {
        $profiles = Profile::query()
            ->where('status', 'approved')
            ->select(['id', 'display_name', 'location'])
            ->orderBy('display_name')
            ->limit(100)
            ->get();

        return Inertia::render('Signal/Requests/Create', [
            'profiles' => $profiles,
        ]);
    }

    public function store(StoreSignalRequestConversationRequest $request): RedirectResponse
    {
        Gate::authorize('create', ServiceRequest::class);
        Gate::authorize('create', Conversation::class);
        Gate::authorize('create', ConversationMessage::class);

        $user = $request->user();
        $payload = $request->validated();

        $conversation = DB::transaction(function () use ($payload, $user): Conversation {
            $serviceRequest = ServiceRequest::query()->create([
                'requester_id' => $user->id,
                'profile_id' => $payload['profile_id'],
                'title' => $payload['title'],
                'description' => $payload['description'],
                'status' => 'open',
            ]);

            $conversation = Conversation::query()->create([
                'requester_id' => $user->id,
                'profile_id' => $payload['profile_id'],
                'request_id' => $serviceRequest->id,
                'status' => 'open',
            ]);

            if (! empty($payload['initial_message'])) {
                ConversationMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'sender_id' => $user->id,
                    'type' => 'text',
                    'body' => $payload['initial_message'],
                    'attachments' => null,
                    'meta' => null,
                ]);

                $conversation->forceFill([
                    'last_message_at' => now(),
                ])->save();
            }

            return $conversation;
        });

        return redirect()
            ->route('signal.chat.show', $conversation)
            ->with('success', 'Request created. Conversation is now open.');
    }
}
