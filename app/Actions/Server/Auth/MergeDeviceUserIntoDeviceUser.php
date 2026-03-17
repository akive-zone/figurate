<?php

namespace App\Actions\Server\Auth;

use App\Models\Server\SanctumUser;
use App\Models\Server\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MergeDeviceUserIntoDeviceUser
{
    public function __invoke(?User $sourceDeviceUser, User $targetDeviceUser): void
    {
        if (! $sourceDeviceUser) {
            return;
        }

        if ($sourceDeviceUser->is($targetDeviceUser)) {
            return;
        }

        if (! $sourceDeviceUser->isGadget() || ! $targetDeviceUser->isGadget()) {
            return;
        }

        DB::transaction(function () use ($sourceDeviceUser, $targetDeviceUser): void {
            $this->migrateRequestActors($sourceDeviceUser, $targetDeviceUser);
            $this->migrateChannelActorStates($sourceDeviceUser, $targetDeviceUser);
            $this->migrateThreadActorSessions($sourceDeviceUser, $targetDeviceUser);
            $this->migrateAgentConversations($sourceDeviceUser, $targetDeviceUser);
            $this->migrateAgentConversationMessages($sourceDeviceUser, $targetDeviceUser);
            $this->migratePasskeys($sourceDeviceUser, $targetDeviceUser);
            $this->migrateUserAgents($sourceDeviceUser, $targetDeviceUser);

            if ($targetDeviceUser->device_identifier === null && $sourceDeviceUser->device_identifier !== null) {
                $targetDeviceUser->forceFill([
                    'device_identifier' => $sourceDeviceUser->device_identifier,
                ])->save();
            }

            $sourceDeviceUser->forceFill([
                'status' => 'merged',
                'device_identifier' => null,
            ])->save();

            SanctumUser::query()->find($sourceDeviceUser->id)?->tokens()->delete();
        });
    }

    protected function migrateRequestActors(User $sourceDeviceUser, User $targetDeviceUser): void
    {
        if (! Schema::hasTable('request_actors')) {
            return;
        }

        $rows = DB::table('request_actors')
            ->where('actor_type', $sourceDeviceUser->getMorphClass())
            ->where('actor_id', $sourceDeviceUser->id)
            ->get();

        foreach ($rows as $row) {
            $alreadyExists = DB::table('request_actors')
                ->where('request_id', $row->request_id)
                ->where('actor_type', $targetDeviceUser->getMorphClass())
                ->where('actor_id', $targetDeviceUser->id)
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
                    'actor_type' => $targetDeviceUser->getMorphClass(),
                    'actor_id' => $targetDeviceUser->id,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function migrateChannelActorStates(User $sourceDeviceUser, User $targetDeviceUser): void
    {
        if (! Schema::hasTable('actor_states')) {
            return;
        }

        $rows = DB::table('actor_states')
            ->where('actorable_type', $sourceDeviceUser->getMorphClass())
            ->where('actorable_id', $sourceDeviceUser->id)
            ->get();

        foreach ($rows as $row) {
            $alreadyExists = DB::table('actor_states')
                ->where('channel_id', $row->channel_id)
                ->where('actorable_type', $targetDeviceUser->getMorphClass())
                ->where('actorable_id', $targetDeviceUser->id)
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
                    'actorable_type' => $targetDeviceUser->getMorphClass(),
                    'actorable_id' => $targetDeviceUser->id,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function migrateThreadActorSessions(User $sourceDeviceUser, User $targetDeviceUser): void
    {
        if (! Schema::hasTable('thread_actor_sessions')) {
            return;
        }

        $rows = DB::table('thread_actor_sessions')
            ->where('user_id', $sourceDeviceUser->id)
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('thread_actor_sessions')
                ->where('thread_actor_id', $row->thread_actor_id)
                ->where('user_id', $targetDeviceUser->id)
                ->where('provider', $row->provider)
                ->where('model', $row->model)
                ->first();

            if (! $existing) {
                DB::table('thread_actor_sessions')
                    ->where('id', $row->id)
                    ->update([
                        'user_id' => $targetDeviceUser->id,
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

    protected function migrateAgentConversations(User $sourceDeviceUser, User $targetDeviceUser): void
    {
        if (! Schema::hasTable('agent_conversations')) {
            return;
        }

        DB::table('agent_conversations')
            ->where('user_id', $sourceDeviceUser->id)
            ->update([
                'user_id' => $targetDeviceUser->id,
                'updated_at' => now(),
            ]);
    }

    protected function migrateAgentConversationMessages(User $sourceDeviceUser, User $targetDeviceUser): void
    {
        if (! Schema::hasTable('agent_conversation_messages')) {
            return;
        }

        DB::table('agent_conversation_messages')
            ->where('user_id', $sourceDeviceUser->id)
            ->update([
                'user_id' => $targetDeviceUser->id,
                'updated_at' => now(),
            ]);
    }

    protected function migratePasskeys(User $sourceDeviceUser, User $targetDeviceUser): void
    {
        if (! Schema::hasTable('passkeys')) {
            return;
        }

        $rows = DB::table('passkeys')
            ->where('authenticatable_id', $sourceDeviceUser->id)
            ->get();

        foreach ($rows as $row) {
            $credentialId = is_string($row->credential_id ?? null) ? trim((string) $row->credential_id) : '';

            if ($credentialId === '') {
                DB::table('passkeys')
                    ->where('id', $row->id)
                    ->update([
                        'authenticatable_id' => $targetDeviceUser->id,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $existing = DB::table('passkeys')
                ->where('authenticatable_id', $targetDeviceUser->id)
                ->where('credential_id', $credentialId)
                ->first();

            if (! $existing) {
                DB::table('passkeys')
                    ->where('id', $row->id)
                    ->update([
                        'authenticatable_id' => $targetDeviceUser->id,
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

    protected function migrateUserAgents(User $sourceDeviceUser, User $targetDeviceUser): void
    {
        if (! Schema::hasTable('user_agents')) {
            return;
        }

        $rows = DB::table('user_agents')
            ->where('user_id', $sourceDeviceUser->id)
            ->get();

        foreach ($rows as $row) {
            $deviceIdentifier = is_string($row->device_identifier ?? null)
                ? trim((string) $row->device_identifier)
                : '';

            $existing = $deviceIdentifier === ''
                ? null
                : DB::table('user_agents')
                    ->where('user_id', $targetDeviceUser->id)
                    ->where('device_identifier', $deviceIdentifier)
                    ->first();

            if (! $existing) {
                DB::table('user_agents')
                    ->where('id', $row->id)
                    ->update([
                        'user_id' => $targetDeviceUser->id,
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
