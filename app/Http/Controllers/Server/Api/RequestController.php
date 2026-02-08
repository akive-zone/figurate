<?php

namespace App\Http\Controllers\Server\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Signal\StoreSignalRequestChannelRequest;
use App\Models\Server\Channel;
use App\Models\Server\Message;
use App\Models\Server\Request as ServiceRequest;
use App\Models\Server\Thread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
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

        $channel = DB::transaction(function () use ($payload, $request, $user): Channel {
            $serviceRequest = ServiceRequest::query()->create([
                'flow_type' => $payload['flow_type'],
                'title' => $payload['title'],
                'description' => $payload['description'],
                'status' => 'open',
            ]);

            $serviceRequest->users()->attach($user->id, [
                'action' => ServiceRequest::ActionAsker,
                'status' => 'active',
            ]);

            if (! empty($payload['profile_id'])) {
                $serviceRequest->profiles()->attach($payload['profile_id'], [
                    'action' => ServiceRequest::ActionTargetProfile,
                    'status' => 'active',
                ]);
            }

            $channel = Channel::query()->create([
                'requester_id' => $user->id,
                'profile_id' => $payload['profile_id'] ?? null,
                'status' => 'open',
            ]);

            $channel->requests()->attach($serviceRequest->id);

            $mainThread = $serviceRequest->threads()->create([
                'created_by' => $user->id,
                'title' => 'Request Intake',
                'phase' => 'request_intake',
                'agent_key' => Thread::AgentRequest,
                'status' => 'open',
            ]);

            $attachments = collect($request->file('contents', []))
                ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
                ->map(function (UploadedFile $file) use ($channel): array {
                    $storedPath = $file->store("channels/{$channel->id}/contents");

                    return [
                        'path' => $storedPath,
                        'name' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ];
                })
                ->values()
                ->all();

            if (! empty($payload['initial_message']) || ! empty($attachments)) {
                $mainThread->messages()->create([
                    'sender_id' => $user->id,
                    'type' => 'text',
                    'body' => $payload['initial_message'] ?? 'Request files uploaded for reference.',
                    'attachments' => ! empty($attachments) ? $attachments : null,
                    'meta' => ['source' => 'request_open'],
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
