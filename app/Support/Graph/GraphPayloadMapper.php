<?php

namespace App\Support\Graph;

use App\Models\Server\PostRelation;
use App\Models\Server\SpaceRelation;
use App\Models\Server\ThreadRelation;
use App\Models\Server\User;
use Illuminate\Database\Eloquent\Model;

class GraphPayloadMapper
{
    public function __construct(protected GraphNodeService $graphNodes) {}

    /** @return array<string, mixed> */
    public function node(Model $node, User $actor): array
    {
        return $this->graphNodes->map($node, $actor);
    }

    /**
     * @param  array<string, mixed>  $edge
     * @return array<string, mixed>
     */
    public function edge(array $edge, User $actor): array
    {
        /** @var SpaceRelation|ThreadRelation|PostRelation $relation */
        $relation = $edge['relation'];
        /** @var Model $source */
        $source = $edge['source'];
        /** @var Model $target */
        $target = $edge['target'];

        return [
            'id' => $relation->ulid,
            'direction' => (string) $edge['direction'],
            'depth' => (int) $edge['depth'],
            'type' => $relation instanceof PostRelation ? $relation->role : $relation->type,
            'purpose' => $relation instanceof PostRelation ? null : $relation->purpose,
            'source' => $this->node($source, $actor),
            'target' => $this->node($target, $actor),
            'created_at' => optional($relation->created_at)?->toIso8601String(),
        ];
    }
}
