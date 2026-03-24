<?php

namespace App\Features\Actions\Auth;

use App\Contracts\Users\UserRepository;
use App\Models\Server\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MergeWidgetUserIntoWidget
{
    public function __construct(protected UserRepository $userRepository) {}

    public function execute(?User $sourceWidgetUser, User $targetWidgetUser): void
    {
        if (! $sourceWidgetUser) {
            return;
        }

        if ($sourceWidgetUser->is($targetWidgetUser)) {
            return;
        }

        if (! $sourceWidgetUser->isWidget() || ! $targetWidgetUser->isWidget()) {
            return;
        }

        DB::transaction(function () use ($sourceWidgetUser, $targetWidgetUser): void {
            $this->migrateRequestActors($sourceWidgetUser, $targetWidgetUser);
            $this->migrateChannelActorStates($sourceWidgetUser, $targetWidgetUser);
            $this->migrateThreadActorSessions($sourceWidgetUser, $targetWidgetUser);
            $this->migrateAgentConversations($sourceWidgetUser, $targetWidgetUser);
            $this->migrateAgentConversationMessages($sourceWidgetUser, $targetWidgetUser);
            $this->migratePasskeys($sourceWidgetUser, $targetWidgetUser);
            $this->migrateUserAgents($sourceWidgetUser, $targetWidgetUser);

            $sourceWidgetUser->forceFill([
                'status' => 'merged',
            ]);
            $this->userRepository->save($sourceWidgetUser);

            $this->userRepository->deleteAuthTokens($sourceWidgetUser);
        });
    }

    protected function migrateRequestActors(User $sourceWidgetUser, User $targetWidgetUser): void
    {
        if (! Schema::hasTable('request_actors')) {
            return;
        }

        $rows = DB::table('request_actors')
            ->where('actor_type', $sourceWidgetUser->getMorphClass())
            ->where('actor_id', $sourceWidgetUser->id)
            ->get();

        foreach ($rows as $row) {
            $alreadyExists = DB::table('request_actors')
                ->where('request_id', $row->request_id)
                ->where('actor_type', $targetWidgetUser->getMorphClass())
                ->where('actor_id', $targetWidgetUser->id)
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
                    'actor_type' => $targetWidgetUser->getMorphClass(),
                    'actor_id' => $targetWidgetUser->id,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function migrateChannelActorStates(User $sourceWidgetUser, User $targetWidgetUser): void
    {
        if (! Schema::hasTable('actor_states')) {
            return;
        }

        $rows = DB::table('actor_states')
            ->where('actorable_type', $sourceWidgetUser->getMorphClass())
            ->where('actorable_id', $sourceWidgetUser->id)
            ->get();

        foreach ($rows as $row) {
            $alreadyExists = DB::table('actor_states')
                ->where('channel_id', $row->channel_id)
                ->where('actorable_type', $targetWidgetUser->getMorphClass())
                ->where('actorable_id', $targetWidgetUser->id)
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
                    'actorable_type' => $targetWidgetUser->getMorphClass(),
                    'actorable_id' => $targetWidgetUser->id,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function migrateThreadActorSessions(User $sourceWidgetUser, User $targetWidgetUser): void
    {
        if (! Schema::hasTable('thread_actor_sessions')) {
            return;
        }

        $rows = DB::table('thread_actor_sessions')
            ->where('user_id', $sourceWidgetUser->id)
            ->get();

        foreach ($rows as $row) {
            $existing = DB::table('thread_actor_sessions')
                ->where('thread_actor_id', $row->thread_actor_id)
                ->where('user_id', $targetWidgetUser->id)
                ->where('provider', $row->provider)
                ->where('model', $row->model)
                ->first();

            if (! $existing) {
                DB::table('thread_actor_sessions')
                    ->where('id', $row->id)
                    ->update([
                        'user_id' => $targetWidgetUser->id,
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

    protected function migrateAgentConversations(User $sourceWidgetUser, User $targetWidgetUser): void
    {
        if (! Schema::hasTable('agent_conversations')) {
            return;
        }

        DB::table('agent_conversations')
            ->where('user_id', $sourceWidgetUser->id)
            ->update([
                'user_id' => $targetWidgetUser->id,
                'updated_at' => now(),
            ]);
    }

    protected function migrateAgentConversationMessages(User $sourceWidgetUser, User $targetWidgetUser): void
    {
        if (! Schema::hasTable('agent_conversation_messages')) {
            return;
        }

        DB::table('agent_conversation_messages')
            ->where('user_id', $sourceWidgetUser->id)
            ->update([
                'user_id' => $targetWidgetUser->id,
                'updated_at' => now(),
            ]);
    }

    protected function migratePasskeys(User $sourceWidgetUser, User $targetWidgetUser): void
    {
        if (! Schema::hasTable('passkeys')) {
            return;
        }

        $rows = DB::table('passkeys')
            ->where('authenticatable_id', $sourceWidgetUser->id)
            ->get();

        foreach ($rows as $row) {
            $credentialId = is_string($row->credential_id ?? null) ? trim((string) $row->credential_id) : '';

            if ($credentialId === '') {
                DB::table('passkeys')
                    ->where('id', $row->id)
                    ->update([
                        'authenticatable_id' => $targetWidgetUser->id,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $existing = DB::table('passkeys')
                ->where('authenticatable_id', $targetWidgetUser->id)
                ->where('credential_id', $credentialId)
                ->first();

            if (! $existing) {
                DB::table('passkeys')
                    ->where('id', $row->id)
                    ->update([
                        'authenticatable_id' => $targetWidgetUser->id,
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

    protected function migrateUserAgents(User $sourceWidgetUser, User $targetWidgetUser): void
    {
        if (! Schema::hasTable('user_agents')) {
            return;
        }

        $rows = DB::table('user_agents')
            ->where('user_id', $sourceWidgetUser->id)
            ->get();

        foreach ($rows as $row) {
            $deviceIdentifier = is_string($row->device_identifier ?? null)
                ? trim((string) $row->device_identifier)
                : '';

            $existing = $deviceIdentifier === ''
                ? null
                : DB::table('user_agents')
                    ->where('user_id', $targetWidgetUser->id)
                    ->where('device_identifier', $deviceIdentifier)
                    ->first();

            if (! $existing) {
                DB::table('user_agents')
                    ->where('id', $row->id)
                    ->update([
                        'user_id' => $targetWidgetUser->id,
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
