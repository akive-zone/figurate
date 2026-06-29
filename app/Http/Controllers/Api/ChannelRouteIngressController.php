<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server\ChannelRoute;
use App\Support\Channels\ChannelRouteIngress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelRouteIngressController extends Controller
{
    public function __invoke(Request $request, int $route, ChannelRouteIngress $channelRouteIngress): JsonResponse
    {
        $channelRoute = ChannelRoute::query()->with('channel')->findOrFail($route);
        $result = $channelRouteIngress->receive($channelRoute, $request);
        $post = $result['post'];
        $thread = $result['thread'];
        $address = $result['address'];

        return response()->json([
            'data' => [
                'status' => 'accepted',
                'route_id' => $channelRoute->id,
                'address_id' => $address->id,
                'thread_id' => $thread->uuid,
                'post_id' => $post->id,
                'post_ulid' => $post->ulid,
            ],
        ], 202);
    }
}
