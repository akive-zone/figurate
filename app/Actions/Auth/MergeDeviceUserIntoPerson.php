<?php

namespace App\Actions\Auth;

use App\Models\Server\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MergeDeviceUserIntoPerson
{
    public function __invoke(?User $deviceUser, User $personUser): void
    {
        if (! $deviceUser) {
            return;
        }

        if ($deviceUser->is($personUser)) {
            return;
        }

        if ($deviceUser->type !== 'device' || $personUser->type !== 'person') {
            return;
        }

        DB::transaction(function () use ($deviceUser, $personUser): void {
            $this->migrateRequestActors($deviceUser, $personUser);
            $this->migrateChannelActorStates($deviceUser, $personUser);

            if ($personUser->device_identifier === null && $deviceUser->device_identifier !== null) {
                $personUser->forceFill([
                    'device_identifier' => $deviceUser->device_identifier,
                ])->save();
            }

            $deviceUser->forceFill([
                'status' => 'merged',
                'device_identifier' => null,
            ])->save();

            $deviceUser->tokens()->delete();
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
        if (! Schema::hasTable('channel_actor_states')) {
            return;
        }

        $rows = DB::table('channel_actor_states')
            ->where('actor_type', $deviceUser->getMorphClass())
            ->where('actor_id', $deviceUser->id)
            ->get();

        foreach ($rows as $row) {
            $alreadyExists = DB::table('channel_actor_states')
                ->where('channel_id', $row->channel_id)
                ->where('actor_type', $personUser->getMorphClass())
                ->where('actor_id', $personUser->id)
                ->exists();

            if ($alreadyExists) {
                DB::table('channel_actor_states')
                    ->where('id', $row->id)
                    ->delete();

                continue;
            }

            DB::table('channel_actor_states')
                ->where('id', $row->id)
                ->update([
                    'actor_type' => $personUser->getMorphClass(),
                    'actor_id' => $personUser->id,
                    'updated_at' => now(),
                ]);
        }
    }
}
