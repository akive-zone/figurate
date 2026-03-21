<?php

namespace Figurate\WebView\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Server\Channel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Channels/Index');
    }

    public function show(Request $request, string $channel): Response
    {
        return $this->renderChannelRoute($request, $channel, null);
    }

    public function showThread(Request $request, string $channel, string $thread): Response
    {
        return $this->renderChannelRoute($request, $channel, $thread);
    }

    protected function renderChannelRoute(Request $request, string $channelUuid, ?string $threadUuid): Response
    {
        $channel = Channel::query()
            ->where('uuid', $channelUuid)
            ->first();

        if (! $channel instanceof Channel) {
            throw (new ModelNotFoundException)->setModel(Channel::class, [$channelUuid]);
        }

        Gate::forUser($request->user())->authorize('view', $channel);

        return Inertia::render('Channels/Show', [
            'channelId' => $channel->uuid,
            'threadId' => $threadUuid,
        ]);
    }
}
