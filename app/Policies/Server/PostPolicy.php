<?php

namespace App\Policies\Server;

use App\Models\Server\Post;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use Illuminate\Support\Facades\Gate;

class PostPolicy
{
    protected function isSender(User $user, Post $post): bool
    {
        return $post->relations()
            ->where('role', Post::RelationRoleSender)
            ->where('relationable_id', $user->getKey())
            ->whereIn('relationable_type', [
                User::class,
                $user::class,
                $user->getMorphClass(),
                'user',
            ])
            ->exists();
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canUseInteractiveTransport();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Post $post): bool
    {
        return $this->canView($user, $post);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Post $post): bool
    {
        return $this->isSender($user, $post);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Post $post): bool
    {
        return $this->isSender($user, $post);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Post $post): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Post $post): bool
    {
        return false;
    }

    /**
     * @param  array<int, bool>  $visitedPostIds
     */
    protected function canView(User $user, Post $post, array &$visitedPostIds = []): bool
    {
        $postId = (int) $post->getKey();

        if ($postId <= 0 || isset($visitedPostIds[$postId])) {
            return false;
        }

        $visitedPostIds[$postId] = true;

        if ($this->isSender($user, $post)) {
            return true;
        }

        $postable = $post->postable;

        if ($postable instanceof Space) {
            return $postable->hasActor($user);
        }

        if ($postable instanceof Thread) {
            return Gate::forUser($user)->allows('view', $postable);
        }

        if ($postable instanceof Post) {
            return $this->canView($user, $postable, $visitedPostIds);
        }

        return false;
    }
}
