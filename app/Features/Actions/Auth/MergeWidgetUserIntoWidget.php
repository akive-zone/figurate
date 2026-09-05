<?php

namespace App\Features\Actions\Auth;

use App\Contracts\Users\UserRepository;
use App\Events\Server\Auth\WidgetUsersMerging;
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
            $this->migratePostRelations($sourceWidgetUser, $targetWidgetUser);
            $this->migrateSpaceActorStates($sourceWidgetUser, $targetWidgetUser);
            WidgetUsersMerging::dispatch($sourceWidgetUser, $targetWidgetUser);
            $this->migratePasskeys($sourceWidgetUser, $targetWidgetUser);
            $this->migrateUserClients($sourceWidgetUser, $targetWidgetUser);

            $sourceWidgetUser->forceFill([
                'status' => 'merged',
            ]);
            $this->userRepository->save($sourceWidgetUser);

            $this->userRepository->deleteAuthTokens($sourceWidgetUser);
        });
    }

    protected function migratePostRelations(User $sourceWidgetUser, User $targetWidgetUser): void
    {
        if (! Schema::hasTable('post_relations')) {
            return;
        }

        $rows = DB::table('post_relations')
            ->where('relationable_type', $sourceWidgetUser->getMorphClass())
            ->where('relationable_id', $sourceWidgetUser->id)
            ->get();

        foreach ($rows as $row) {
            $alreadyExists = DB::table('post_relations')
                ->where('post_id', $row->post_id)
                ->where('relationable_type', $targetWidgetUser->getMorphClass())
                ->where('relationable_id', $targetWidgetUser->id)
                ->where('role', $row->role)
                ->exists();

            if ($alreadyExists) {
                DB::table('post_relations')
                    ->where('id', $row->id)
                    ->delete();

                continue;
            }

            DB::table('post_relations')
                ->where('id', $row->id)
                ->update([
                    'relationable_type' => $targetWidgetUser->getMorphClass(),
                    'relationable_id' => $targetWidgetUser->id,
                    'updated_at' => now(),
                ]);
        }
    }

    protected function migrateSpaceActorStates(User $sourceWidgetUser, User $targetWidgetUser): void
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
                ->where('space_id', $row->space_id)
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

    protected function migrateUserClients(User $sourceWidgetUser, User $targetWidgetUser): void
    {
        if (! Schema::hasTable('user_clients')) {
            return;
        }

        $rows = DB::table('user_clients')
            ->where('user_id', $sourceWidgetUser->id)
            ->get();

        foreach ($rows as $row) {
            $deviceIdentifier = is_string($row->device_identifier ?? null)
                ? trim((string) $row->device_identifier)
                : '';

            $existing = $deviceIdentifier === ''
                ? null
                : DB::table('user_clients')
                    ->where('user_id', $targetWidgetUser->id)
                    ->where('device_identifier', $deviceIdentifier)
                    ->first();

            if (! $existing) {
                DB::table('user_clients')
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
                DB::table('user_clients')
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

            DB::table('user_clients')
                ->where('id', $row->id)
                ->delete();
        }
    }
}
