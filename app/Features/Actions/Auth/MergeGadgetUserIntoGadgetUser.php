<?php

namespace App\Features\Actions\Auth;

use App\Contracts\Users\UserRepository;
use App\Models\Server\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MergeGadgetUserIntoGadgetUser
{
    public function __construct(protected UserRepository $userRepository) {}

    public function execute(?User $sourceGadgetUser, User $targetGadgetUser): void
    {
        if (! $sourceGadgetUser) {
            return;
        }

        if ($sourceGadgetUser->is($targetGadgetUser)) {
            return;
        }

        if (! $sourceGadgetUser->isGadget() || ! $targetGadgetUser->isGadget()) {
            return;
        }

        DB::transaction(function () use ($sourceGadgetUser, $targetGadgetUser): void {
            $this->migrateRequestActors($sourceGadgetUser, $targetGadgetUser);
            $this->migrateChannelActorStates($sourceGadgetUser, $targetGadgetUser);
            $this->migrateThreadActorSessions($sourceGadgetUser, $targetGadgetUser);
            $this->migrateAgentConversations($sourceGadgetUser, $targetGadgetUser);
            $this->migrateAgentConversationMessages($sourceGadgetUser, $targetGadgetUser);
            $this->migratePasskeys($sourceGadgetUser, $targetGadgetUser);
            $this->migrateUserAgents($sourceGadgetUser, $targetGadgetUser);

            $sourceGadgetUser->forceFill([
                'status' => 'merged',
            ]);
            $this->userRepository->save($sourceGadgetUser);

            $this->userRepository->deleteAuthTokens($sourceGadgetUser);
        });
    }

    protected function migrateRequestActors(User $sourceGadgetUser, User $targetGadgetUser): void
    {
        if (! Schema::hasTable('request_actors')) {
            return;
        }

        $rows = DB::table('request_actors')
            ->where('actor_type', $sourceGadgetUser->getMorphClass())
            ->where('actor_id', $sourceGadgetUser->id)
            ->get();

        foreach ($rows as $row) {
            $alreadyExists = DB::table('request_actors')
                ->where('request_id', $row->request_id)
                ->where('actor_type', $targetGadgetUser->getMorphClass())
                ->where('actor_id', $targetGadgetUser->id)
                ->where('action', $row->action)
                ->exists();

            if ($alreadyExists) {
                DB::table('request_actors')
                    ->where('id', $row->id)
                    ->delete();

                continue;
            }

            DB::table('request_actors')
                ->where('id', $row->id)
                ->update([
                    'actor_type' => $targetGadgetUser->getMorphClass(),
                    'actor_id' => $targetGadgetUser->id,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function migrateChannelActorStates(User $sourceGadgetUser, User $targetGadgetUser): void
    {
        if (! Schema::hasTable('actor_states')) {
            return;
        }

        $rows = DB::table('actor_states')
            ->where('actorable_type', $sourceGadgetUser->getMorphClass())
            ->where('actorable_id', $sourceGadgetUser->id)
            ->get();

        foreach ($rows as $row) {
            $alreadyExists = DB::table('actor_states')
                ->where('channel_id', $row->channel_id)
                ->where('actorable_type', $targetGadgetUser->getMorphClass())
                ->where('actorable_id', $targetGadgetUser->id)
                ->exists();

            if ($alreadyExists) {
                DB::table('actor_states')
                    ->where('id', $row->id)
                    ->delete();

                continue;
            }

            DB::table('actor_states')
                ->where('id', $row->id)
                ->update([
                    'actorable_type' => $targetGadgetUser->getMorphClass(),
                    'actorable_id' => $targetGadgetUser->id,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function migrateThreadActorSessions(User $sourceGadgetUser, User $targetGadgetUser): void
    {
        if (! Schema::hasTable('thread_actor_sessions')) {
            return;
        }

        $rows = DB::table('thread_actor_sessions')
            ->where('user_id', $sourceGadgetUser->id)
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('thread_actor_sessions')
                ->where('thread_actor_id', $row->thread_actor_id)
                ->where('user_id', $targetGadgetUser->id)
                ->where('provider', $row->provider)
                ->where('model', $row->model)
                ->first();

            if (! $existing) {
                DB::table('thread_actor_sessions')
                    ->where('id', $row->id)
                    ->update([
                        'user_id' => $targetGadgetUser->id,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $sourceLastUsed = $row->last_used_at ? strtotime((string) $row->last_used_at) : null;
            $targetLastUsed = $existing->last_used_at ? strtotime((string) $existing->last_used_at) : null;

            if ($sourceLastUsed !== null && ($targetLastUsed === null || $sourceLastUsed > $targetLastUsed)) {
                DB::table('thread_actor_sessions')
                    ->where('id', $existing->id)
                    ->update([
                        'conversation_id' => $row->conversation_id,
                        'state' => $row->state,
                        'last_used_at' => $row->last_used_at,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('thread_actor_sessions')
                ->where('id', $row->id)
                ->delete();
        }
    }

    protected function migrateAgentConversations(User $sourceGadgetUser, User $targetGadgetUser): void
    {
        if (! Schema::hasTable('agent_conversations')) {
            return;
        }

        DB::table('agent_conversations')
            ->where('user_id', $sourceGadgetUser->id)
            ->update([
                'user_id' => $targetGadgetUser->id,
                'updated_at' => now(),
            ]);
    }

    protected function migrateAgentConversationMessages(User $sourceGadgetUser, User $targetGadgetUser): void
    {
        if (! Schema::hasTable('agent_conversation_messages')) {
            return;
        }

        DB::table('agent_conversation_messages')
            ->where('user_id', $sourceGadgetUser->id)
            ->update([
                'user_id' => $targetGadgetUser->id,
                'updated_at' => now(),
            ]);
    }

    protected function migratePasskeys(User $sourceGadgetUser, User $targetGadgetUser): void
    {
        if (! Schema::hasTable('passkeys')) {
            return;
        }

        $rows = DB::table('passkeys')
            ->where('authenticatable_id', $sourceGadgetUser->id)
            ->get();

        foreach ($rows as $row) {
            $credentialId = is_string($row->credential_id ?? null) ? trim((string) $row->credential_id) : '';

            if ($credentialId === '') {
                DB::table('passkeys')
                    ->where('id', $row->id)
                    ->update([
                        'authenticatable_id' => $targetGadgetUser->id,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $existing = DB::table('passkeys')
                ->where('authenticatable_id', $targetGadgetUser->id)
                ->where('credential_id', $credentialId)
                ->first();

            if (! $existing) {
                DB::table('passkeys')
                    ->where('id', $row->id)
                    ->update([
                        'authenticatable_id' => $targetGadgetUser->id,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $sourceLastUsed = $row->last_used_at ? strtotime((string) $row->last_used_at) : null;
            $targetLastUsed = $existing->last_used_at ? strtotime((string) $existing->last_used_at) : null;

            if ($sourceLastUsed !== null && ($targetLastUsed === null || $sourceLastUsed > $targetLastUsed)) {
                DB::table('passkeys')
                    ->where('id', $existing->id)
                    ->update([
                        'last_used_at' => $row->last_used_at,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('passkeys')
                ->where('id', $row->id)
                ->delete();
        }
    }

    protected function migrateUserAgents(User $sourceGadgetUser, User $targetGadgetUser): void
    {
        if (! Schema::hasTable('user_agents')) {
            return;
        }

        $rows = DB::table('user_agents')
            ->where('user_id', $sourceGadgetUser->id)
            ->get();

        foreach ($rows as $row) {
            $deviceIdentifier = is_string($row->device_identifier ?? null)
                ? trim((string) $row->device_identifier)
                : '';

            $existing = $deviceIdentifier === ''
                ? null
                : DB::table('user_agents')
                    ->where('user_id', $targetGadgetUser->id)
                    ->where('device_identifier', $deviceIdentifier)
                    ->first();

            if (! $existing) {
                DB::table('user_agents')
                    ->where('id', $row->id)
                    ->update([
                        'user_id' => $targetGadgetUser->id,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $sourceLastSeen = $row->last_seen_at ? strtotime((string) $row->last_seen_at) : null;
            $targetLastSeen = $existing->last_seen_at ? strtotime((string) $existing->last_seen_at) : null;

            if ($sourceLastSeen !== null && ($targetLastSeen === null || $sourceLastSeen > $targetLastSeen)) {
                DB::table('user_agents')
                    ->where('id', $existing->id)
                    ->update([
                        'kind' => $row->kind,
                        'user_agent' => $row->user_agent,
                        'ip_address' => $row->ip_address,
                        'app_version' => $row->app_version,
                        'platform' => $row->platform,
                        'data' => $row->data,
                        'metadata' => $row->metadata,
                        'last_seen_at' => $row->last_seen_at,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('user_agents')
                ->where('id', $row->id)
                ->delete();
        }
    }
}
