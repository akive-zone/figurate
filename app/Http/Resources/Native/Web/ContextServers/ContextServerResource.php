<?php

namespace App\Http\Resources\Native\Web\ContextServers;

use App\Http\Resources\Native\Web\ContextServers\Pages\CreateContextServer;
use App\Http\Resources\Native\Web\ContextServers\Pages\EditContextServer;
use App\Http\Resources\Native\Web\ContextServers\Pages\ListContextServers;
use App\Http\Resources\Native\Web\ContextServers\Schemas\ContextServerForm;
use App\Http\Resources\Native\Web\ContextServers\Tables\ContextServersTable;
use App\Models\Server\Channel;
use App\Models\Server\ContextServer;
use App\Models\Server\Thread;
use App\Models\Server\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ContextServerResource extends Resource
{
    protected static ?string $model = ContextServer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Context Servers';

    protected static ?string $modelLabel = 'Context Server';

    protected static string|UnitEnum|null $navigationGroup = 'AI';

    protected static ?string $slug = 'context-servers';

    public static function form(Schema $schema): Schema
    {
        return ContextServerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContextServersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContextServers::route('/'),
            'create' => CreateContextServer::route('/create'),
            'edit' => EditContextServer::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $actor = self::currentActor();
        if (! $actor instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        $actorMorphClass = $actor->getMorphClass();
        $channelMorphClass = (new Channel)->getMorphClass();
        $threadMorphClass = (new Thread)->getMorphClass();

        $channelIds = self::accessibleChannelIds($actor);
        $threadIds = self::accessibleThreadIds($actor, $channelIds);

        return $query->where(function (Builder $scopedQuery) use ($actor, $actorMorphClass, $channelMorphClass, $threadMorphClass, $channelIds, $threadIds): void {
            $scopedQuery->orWhere(function (Builder $userQuery) use ($actorMorphClass, $actor): void {
                $userQuery
                    ->where('contextable_type', $actorMorphClass)
                    ->where('contextable_id', $actor->getKey());
            });

            if ($channelIds !== []) {
                $scopedQuery->orWhere(function (Builder $channelQuery) use ($channelMorphClass, $channelIds): void {
                    $channelQuery
                        ->where('contextable_type', $channelMorphClass)
                        ->whereIn('contextable_id', $channelIds);
                });
            }

            if ($threadIds !== []) {
                $scopedQuery->orWhere(function (Builder $threadQuery) use ($threadMorphClass, $threadIds): void {
                    $threadQuery
                        ->where('contextable_type', $threadMorphClass)
                        ->whereIn('contextable_id', $threadIds);
                });
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public static function contextTypeOptions(): array
    {
        return [
            (new User)->getMorphClass() => 'User',
            (new Channel)->getMorphClass() => 'Channel',
            (new Thread)->getMorphClass() => 'Thread',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function contextIdOptions(?string $contextType): array
    {
        $actor = self::currentActor();
        if (! $actor instanceof User || ! is_string($contextType) || $contextType === '') {
            return [];
        }

        $userMorphClass = (new User)->getMorphClass();
        $channelMorphClass = (new Channel)->getMorphClass();
        $threadMorphClass = (new Thread)->getMorphClass();

        if ($contextType === $userMorphClass) {
            return [
                $actor->id => "{$actor->name} (#{$actor->id})",
            ];
        }

        if ($contextType === $channelMorphClass) {
            return self::accessibleChannels($actor)
                ->pluck('id')
                ->mapWithKeys(fn (int $id): array => [$id => "Channel #{$id}"])
                ->all();
        }

        if ($contextType === $threadMorphClass) {
            return self::accessibleThreads($actor)
                ->mapWithKeys(function (Thread $thread): array {
                    $label = $thread->title ?: "Thread #{$thread->id}";

                    return [$thread->id => $label];
                })
                ->all();
        }

        return [];
    }

    public static function ensureContextSelectionAllowed(?string $contextType, mixed $contextId): void
    {
        $allowedContextIds = array_keys(self::contextIdOptions($contextType));
        $contextId = is_numeric($contextId) ? (int) $contextId : null;

        abort_unless($contextId !== null && in_array($contextId, $allowedContextIds, true), 403, 'Not authorized for this context.');
    }

    protected static function currentActor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Channel>
     */
    protected static function accessibleChannels(User $actor): \Illuminate\Support\Collection
    {
        return Channel::query()
            ->whereHas('actorStates', function (Builder $query) use ($actor): void {
                $query
                    ->where('actorable_type', $actor->getMorphClass())
                    ->where('actorable_id', $actor->getKey());
            })
            ->get(['id']);
    }

    /**
     * @param  list<int>  $channelIds
     * @return list<int>
     */
    protected static function accessibleChannelIds(User $actor): array
    {
        return self::accessibleChannels($actor)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Thread>
     */
    protected static function accessibleThreads(User $actor): \Illuminate\Support\Collection
    {
        $channelIds = self::accessibleChannelIds($actor);
        $actorMorphClass = $actor->getMorphClass();
        $userMorphClass = (new User)->getMorphClass();
        $channelMorphClass = (new Channel)->getMorphClass();

        return Thread::query()
            ->where(function (Builder $query) use ($actorMorphClass, $actor, $userMorphClass, $channelMorphClass, $channelIds): void {
                $query->orWhere(function (Builder $threadActorQuery) use ($actorMorphClass, $actor): void {
                    $threadActorQuery->whereHas('actors', function (Builder $actorQuery) use ($actorMorphClass, $actor): void {
                        $actorQuery
                            ->where('actorable_type', $actorMorphClass)
                            ->where('actorable_id', $actor->getKey());
                    });
                });

                $query->orWhere(function (Builder $threadableUserQuery) use ($userMorphClass, $actor): void {
                    $threadableUserQuery
                        ->where('threadable_type', $userMorphClass)
                        ->where('threadable_id', $actor->getKey());
                });

                if ($channelIds !== []) {
                    $query->orWhere(function (Builder $threadableChannelQuery) use ($channelMorphClass, $channelIds): void {
                        $threadableChannelQuery
                            ->where('threadable_type', $channelMorphClass)
                            ->whereIn('threadable_id', $channelIds);
                    });
                }
            })
            ->get(['id', 'title']);
    }

    /**
     * @param  list<int>  $channelIds
     * @return list<int>
     */
    protected static function accessibleThreadIds(User $actor, array $channelIds = []): array
    {
        $actorMorphClass = $actor->getMorphClass();
        $userMorphClass = (new User)->getMorphClass();
        $channelMorphClass = (new Channel)->getMorphClass();

        return Thread::query()
            ->where(function (Builder $query) use ($actorMorphClass, $actor, $userMorphClass, $channelMorphClass, $channelIds): void {
                $query->orWhere(function (Builder $threadActorQuery) use ($actorMorphClass, $actor): void {
                    $threadActorQuery->whereHas('actors', function (Builder $actorQuery) use ($actorMorphClass, $actor): void {
                        $actorQuery
                            ->where('actorable_type', $actorMorphClass)
                            ->where('actorable_id', $actor->getKey());
                    });
                });

                $query->orWhere(function (Builder $threadableUserQuery) use ($userMorphClass, $actor): void {
                    $threadableUserQuery
                        ->where('threadable_type', $userMorphClass)
                        ->where('threadable_id', $actor->getKey());
                });

                if ($channelIds !== []) {
                    $query->orWhere(function (Builder $threadableChannelQuery) use ($channelMorphClass, $channelIds): void {
                        $threadableChannelQuery
                            ->where('threadable_type', $channelMorphClass)
                            ->whereIn('threadable_id', $channelIds);
                    });
                }
            })
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
