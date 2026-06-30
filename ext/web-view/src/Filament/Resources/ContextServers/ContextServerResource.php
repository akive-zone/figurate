<?php

namespace Figurate\WebView\Filament\Resources\ContextServers;

use App\Models\Server\Channel;
use App\Models\Server\ChannelRelation;
use App\Models\Server\Space;
use App\Models\Server\Thread;
use App\Models\Server\User;
use BackedEnum;
use Figurate\WebView\Filament\Resources\ContextServers\Pages\CreateContextServer;
use Figurate\WebView\Filament\Resources\ContextServers\Pages\EditContextServer;
use Figurate\WebView\Filament\Resources\ContextServers\Pages\ListContextServers;
use Figurate\WebView\Filament\Resources\ContextServers\Schemas\ContextServerForm;
use Figurate\WebView\Filament\Resources\ContextServers\Tables\ContextServersTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use UnitEnum;

class ContextServerResource extends Resource
{
    protected static ?string $model = Channel::class;

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
            ->where(function (Builder $query): void {
                $query
                    ->where('channels.driver', Channel::ProtocolMcp)
                    ->orWhere('channels.config->protocol', Channel::ProtocolMcp)
                    ->orWhereHas('relations', function (Builder $relationQuery): void {
                        $relationQuery->where('channel_relations.config->protocol', Channel::ProtocolMcp);
                    });
            })
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->where(function (Builder $builder): void {
                $builder
                    ->where('channels.driver', Channel::ProtocolMcp)
                    ->orWhere('channels.config->protocol', Channel::ProtocolMcp)
                    ->orWhereHas('relations', function (Builder $relationQuery): void {
                        $relationQuery->where('channel_relations.config->protocol', Channel::ProtocolMcp);
                    });
            })
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $actor = self::currentActor();
        if (! $actor instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        $actorMorphClass = $actor->getMorphClass();
        $spaceMorphClass = (new Space)->getMorphClass();
        $threadMorphClass = (new Thread)->getMorphClass();

        $spaceIds = self::accessibleSpaceIds($actor);
        $threadIds = self::accessibleThreadIds($actor, $spaceIds);

        return $query->where(function (Builder $scopedQuery) use ($actor, $actorMorphClass, $spaceMorphClass, $threadMorphClass, $spaceIds, $threadIds): void {
            $scopedQuery->orWhere(function (Builder $userQuery) use ($actorMorphClass, $actor): void {
                $userQuery->whereHas('relations', function (Builder $relationQuery) use ($actorMorphClass, $actor): void {
                    $relationQuery
                        ->where('kind', ChannelRelation::KindLink)
                        ->where('relationable_type', $actorMorphClass)
                        ->where('relationable_id', $actor->getKey());
                });
            });

            if ($spaceIds !== []) {
                $scopedQuery->orWhere(function (Builder $channelQuery) use ($spaceMorphClass, $spaceIds): void {
                    $channelQuery->whereHas('relations', function (Builder $relationQuery) use ($spaceMorphClass, $spaceIds): void {
                        $relationQuery
                            ->where('kind', ChannelRelation::KindLink)
                            ->where('relationable_type', $spaceMorphClass)
                            ->whereIn('relationable_id', $spaceIds);
                    });
                });
            }

            if ($threadIds !== []) {
                $scopedQuery->orWhere(function (Builder $threadQuery) use ($threadMorphClass, $threadIds): void {
                    $threadQuery->whereHas('relations', function (Builder $relationQuery) use ($threadMorphClass, $threadIds): void {
                        $relationQuery
                            ->where('kind', ChannelRelation::KindLink)
                            ->where('relationable_type', $threadMorphClass)
                            ->whereIn('relationable_id', $threadIds);
                    });
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
            (new Space)->getMorphClass() => 'Space',
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
        $spaceMorphClass = (new Space)->getMorphClass();
        $threadMorphClass = (new Thread)->getMorphClass();

        if ($contextType === $userMorphClass) {
            return [
                $actor->id => "{$actor->name} (#{$actor->id})",
            ];
        }

        if ($contextType === $spaceMorphClass) {
            return self::accessibleSpaces($actor)
                ->pluck('id')
                ->mapWithKeys(fn (int $id): array => [$id => "Space #{$id}"])
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
     * @return Collection<int, Space>
     */
    protected static function accessibleSpaces(User $actor): Collection
    {
        return Space::query()
            ->whereHas('actorStates', function (Builder $query) use ($actor): void {
                $query
                    ->where('actorable_type', $actor->getMorphClass())
                    ->where('actorable_id', $actor->getKey());
            })
            ->get(['id']);
    }

    /**
     * @param  list<int>  $spaceIds
     * @return list<int>
     */
    protected static function accessibleSpaceIds(User $actor): array
    {
        return self::accessibleSpaces($actor)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Thread>
     */
    protected static function accessibleThreads(User $actor): Collection
    {
        $spaceIds = self::accessibleSpaceIds($actor);
        $actorMorphClass = $actor->getMorphClass();
        $userMorphClass = (new User)->getMorphClass();
        $spaceMorphClass = (new Space)->getMorphClass();

        return Thread::query()
            ->where(function (Builder $query) use ($actorMorphClass, $actor, $userMorphClass, $spaceMorphClass, $spaceIds): void {
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

                if ($spaceIds !== []) {
                    $query->orWhere(function (Builder $threadableSpaceQuery) use ($spaceMorphClass, $spaceIds): void {
                        $threadableSpaceQuery
                            ->where('threadable_type', $spaceMorphClass)
                            ->whereIn('threadable_id', $spaceIds);
                    });
                }
            })
            ->get(['id', 'title']);
    }

    /**
     * @param  list<int>  $spaceIds
     * @return list<int>
     */
    protected static function accessibleThreadIds(User $actor, array $spaceIds = []): array
    {
        $actorMorphClass = $actor->getMorphClass();
        $userMorphClass = (new User)->getMorphClass();
        $spaceMorphClass = (new Space)->getMorphClass();

        return Thread::query()
            ->where(function (Builder $query) use ($actorMorphClass, $actor, $userMorphClass, $spaceMorphClass, $spaceIds): void {
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

                if ($spaceIds !== []) {
                    $query->orWhere(function (Builder $threadableSpaceQuery) use ($spaceMorphClass, $spaceIds): void {
                        $threadableSpaceQuery
                            ->where('threadable_type', $spaceMorphClass)
                            ->whereIn('threadable_id', $spaceIds);
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
