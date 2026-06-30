<?php

namespace Figurate\WebView\Filament\Resources\ContextServers\Schemas;

use Figurate\WebView\Filament\Resources\ContextServers\ContextServerResource;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ContextServerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Context')
                    ->schema([
                        Select::make('channelable_type')
                            ->label('Context Type')
                            ->options(ContextServerResource::contextTypeOptions())
                            ->default(array_key_first(ContextServerResource::contextTypeOptions()))
                            ->live(),
                        Select::make('channelable_id')
                            ->label('Context ID')
                            ->options(fn (Get $get): array => ContextServerResource::contextIdOptions($get('channelable_type')))
                            ->searchable()
                            ->preload()
                            ->helperText('Only contexts available to your current user are selectable.'),
                    ])
                    ->columns(2),
                Section::make('Server')
                    ->schema([
                        TextInput::make('server')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('label')
                            ->maxLength(255),
                        Toggle::make('enabled')
                            ->default(true),
                        TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Select::make('transport')
                            ->options([
                                'remote' => 'Remote',
                                'local' => 'Local',
                            ])
                            ->required()
                            ->default('remote')
                            ->live(),
                        TextInput::make('endpoint_url')
                            ->label('Endpoint URL')
                            ->url()
                            ->maxLength(2048)
                            ->visible(fn (Get $get): bool => $get('transport') === 'remote'),
                        TextInput::make('handler')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('transport') === 'local'),
                        TagsInput::make('allowed_tools')
                            ->placeholder('search')
                            ->helperText('Set tool names, or use * for all tools.'),
                    ])
                    ->columns(2),
                Section::make('Auth and Metadata')
                    ->schema([
                        Select::make('auth_type')
                            ->options([
                                'bearer' => 'Bearer',
                                'basic' => 'Basic',
                                'header' => 'Header',
                            ]),
                        KeyValue::make('credentials')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->addActionLabel('Add credential'),
                        KeyValue::make('meta')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->addActionLabel('Add meta'),
                    ])
                    ->columns(1),
            ]);
    }
}
