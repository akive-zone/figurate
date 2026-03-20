<?php

namespace App\Features\Actions\Auth;

use App\Models\Server\SanctumUser;
use App\Models\Server\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MergeDeviceUserIntoPerson
{
    public function execute(?User $deviceUser, User $personUser): void
    {
        if (! $deviceUser) {
            return;
        }

        if ($deviceUser->is($personUser)) {
            return;
        }

        if (! $deviceUser->isGadget() || ! $personUser->isSubject()) {
            return;
        }

        DB::transaction(function () use ($deviceUser, $personUser): void {
            $this->migrateRequestActors($deviceUser, $personUser);
            $this->migrateChannelActorStates($deviceUser, $personUser);
            $this->migratePasskeys($deviceUser, $personUser);
            $this->migrateUserAgents($deviceUser, $personUser);

            if ($personUser->device_identifier === null && $deviceUser->device_identifier !== null) {
                $personUser->forceFill([
                    'device_identifier' => $deviceUser->device_identifier,
                ])->save();
            }

            $deviceUser->forceFill([
                'status' => 'merged',
                'device_identifier' => null,
            ])->save();

            SanctumUser::query()->find($deviceUser->id)?->tokens()->delete();
        });
    }

    protected function migrateRequestActors(User $deviceUser, User $personUser): void
    {
        $rows = DB::table('request_actors')
            ->where('actor_type', $deviceUser->getMorphClass())
            ->where('actor_id', $deviceUser->id)
            ->get();

        foreach ($rows as $row) {
            $alreadyExists = DB::table('request_actors')
                ->where('request_id', $row->request_id)
                ->where('actor_type', $personUser->getMorphClass())
                ->where('actor_id', $personUser->id)
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
                    'actor_type' => $personUser->getMorphClass(),
                    'actor_id' => $personUser->id,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function migrateChannelActorStates(User $deviceUser, User $personUser): void
    {
        if (! Schema::hasTable('actor_states')) {
            return;
        }

        $rows = DB::table('actor_states')
            ->where('actorable_type', $deviceUser->getMorphClass())
            ->where('actorable_id', $deviceUser->id)
            ->get();

        foreach ($rows as $row) {
            $alreadyExists = DB::table('actor_states')
                ->where('channel_id', $row->channel_id)
                ->where('actorable_type', $personUser->getMorphClass())
                ->where('actorable_id', $personUser->id)
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
                    'actorable_type' => $personUser->getMorphClass(),
                    'actorable_id' => $personUser->id,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function migratePasskeys(User $deviceUser, User $personUser): void
    {
        if (! Schema::hasTable('passkeys')) {
            return;
        }

        $rows = DB::table('passkeys')
            ->where('authenticatable_id', $deviceUser->id)
            ->get();

        foreach ($rows as $row) {
            $credentialId = is_string($row->credential_id ?? null) ? trim((string) $row->credential_id) : '';

            if ($credentialId === '') {
                DB::table('passkeys')
                    ->where('id', $row->id)
                    ->update([
                        'authenticatable_id' => $personUser->id,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $existing = DB::table('passkeys')
                ->where('authenticatable_id', $personUser->id)
                ->where('credential_id', $credentialId)
                ->first();

            if (! $existing) {
                DB::table('passkeys')
                    ->where('id', $row->id)
                    ->update([
                        'authenticatable_id' => $personUser->id,
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

    protected function migrateUserAgents(User $deviceUser, User $personUser): void
    {
        if (! Schema::hasTable('user_agents')) {
            return;
        }

        $rows = DB::table('user_agents')
            ->where('user_id', $deviceUser->id)
            ->get();

        foreach ($rows as $row) {
            $deviceIdentifier = is_string($row->device_identifier ?? null)
                ? trim((string) $row->device_identifier)
                : '';

            $existing = $deviceIdentifier === ''
                ? null
                : DB::table('user_agents')
                    ->where('user_id', $personUser->id)
                    ->where('device_identifier', $deviceIdentifier)
                    ->first();

            if (! $existing) {
                DB::table('user_agents')
                    ->where('id', $row->id)
                    ->update([
                        'user_id' => $personUser->id,
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
