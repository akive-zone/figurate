<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server\ChannelRoute;
use App\Support\Channels\ChannelRouteIngress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelRouteIngressController extends Controller
{
    public function __invoke(
        Request $request,
        string $route,
        ChannelRouteIngress $channelRouteIngress,
    ): JsonResponse {
        $channelRoute = ChannelRoute::query()
            ->where(function ($query) use ($route): void {
                $query->where('ulid', $route);

                if (ctype_digit($route)) {
                    $query->orWhere('channel_routes.id', (int) $route);
                }
            })
            ->firstOrFail();
        $result = $channelRouteIngress->receive($channelRoute, $request);
        $post = $result['post'];
        $thread = $result['thread'];
        $address = $result['address'];

        return response()->json([
            'data' => [
                'status' => 'accepted',
                'route_id' => $channelRoute->ulid,
                'address_id' => $address->ulid,
                'thread_id' => $thread->uuid,
                'post_id' => $post->ulid,
            ],
        ], 202);
    }
}
