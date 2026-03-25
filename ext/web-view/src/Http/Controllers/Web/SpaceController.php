<?php

namespace Figurate\WebView\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Server\Space;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SpaceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Channels/Index');
    }

    public function show(Request $request, string $space): Response
    {
        return $this->renderSpaceRoute($request, $space, null);
    }

    public function showThread(Request $request, string $space, string $thread): Response
    {
        return $this->renderSpaceRoute($request, $space, $thread);
    }

    protected function renderSpaceRoute(Request $request, string $spaceUuid, ?string $threadUuid): Response
    {
        $space = Space::query()
            ->where('uuid', $spaceUuid)
            ->first();

        if (! $space instanceof Space) {
            throw (new ModelNotFoundException)->setModel(Space::class, [$spaceUuid]);
        }

        Gate::forUser($request->user())->authorize('view', $space);

        return Inertia::render('Channels/Show', [
            'spaceId' => $space->uuid,
            'threadId' => $threadUuid,
        ]);
    }
}
