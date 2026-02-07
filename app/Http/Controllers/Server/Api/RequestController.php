<?php

namespace App\Http\Controllers\Server\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Signal\StoreSignalRequestChannelRequest;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RequestController extends Controller
{
    public function store(StoreSignalRequestChannelRequest $request): JsonResponse
    {
        $user = $request->user();
        $payload = $request->validated();

        Gate::authorize('create', ServiceRequest::class);
        Gate::authorize('create', Channel::class);
        Gate::authorize('create', Message::class);

        $channel = DB::transaction(function () use ($payload, $user): Channel {
            $serviceRequest = ServiceRequest::query()->create([
                'requester_id' => $user->id,
                'profile_id' => $payload['profile_id'],
                'title' => $payload['title'],
                'description' => $payload['description'],
                'status' => 'open',
            ]);

            $channel = Channel::query()->create([
                'requester_id' => $user->id,
                'profile_id' => $payload['profile_id'],
                'status' => 'open',
            ]);

            $channel->requests()->attach($serviceRequest->id);

            $serviceRequest->threads()->create([
                'created_by' => $user->id,
                'title' => 'Request Intake',
                'phase' => 'request_intake',
                'agent_key' => Thread::AgentRequest,
                'status' => 'open',
            ]);

            if (! empty($payload['initial_message'])) {
                $serviceRequest->messages()->create([
                    'sender_id' => $user->id,
                    'type' => 'text',
                    'body' => $payload['initial_message'],
                    'attachments' => null,
                    'meta' => null,
                ]);

                $channel->forceFill([
                    'last_message_at' => now(),
                ])->save();
            }

            return $channel;
        });

        $serviceRequest = $channel->requests()->first();
        $thread = $serviceRequest?->threads()->latest('id')->first();

        return response()->json([
            'message' => 'Request created. Channel is now open.',
            'channel_id' => $channel->id,
            'thread_id' => $thread?->id,
        ]);
    }
}
