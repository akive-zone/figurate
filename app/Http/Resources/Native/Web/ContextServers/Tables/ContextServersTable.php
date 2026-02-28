<?php

namespace App\Http\Resources\Native\Web\ContextServers\Tables;

use App\Http\Resources\Native\Web\ContextServers\ContextServerResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ContextServersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),
                TextColumn::make('server')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('contextable_type')
                    ->label('Context')
                    ->formatStateUsing(fn (?string $state): string => self::contextTypeLabel($state))
                    ->badge(),
                TextColumn::make('contextable_id')
                    ->label('Context ID')
                    ->sortable(),
                TextColumn::make('transport')
                    ->badge()
                    ->sortable(),
                IconColumn::make('enabled')
                    ->boolean(),
                TextColumn::make('priority')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('contextable_type')
                    ->label('Context')
                    ->options(ContextServerResource::contextTypeOptions()),
                SelectFilter::make('transport')
                    ->options([
                        'remote' => 'Remote',
                        'local' => 'Local',
                    ]),
                TernaryFilter::make('enabled')
                    ->boolean(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected static function contextTypeOptions(): array
    {
        return ContextServerResource::contextTypeOptions();
    }

    protected static function contextTypeLabel(?string $state): string
    {
        return self::contextTypeOptions()[$state ?? ''] ?? (string) $state;
    }
}
