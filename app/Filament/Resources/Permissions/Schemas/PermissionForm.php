<?php

namespace App\Filament\Resources\Permissions\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class PermissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Permission Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Permission Information')
                            ->schema([
                                Hidden::make('guard_name')
                                    ->default(config('auth.defaults.guard')),
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                            ]),
                        Tabs\Tab::make('Roles')
                            ->schema([
                                CheckboxList::make('roles')
                                    ->relationship(
                                        name: 'roles',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                                    )
                                    ->columns(2)
                                    ->bulkToggleable()
                                    ->searchable()
                                    ->helperText('Assign this permission to roles so users inherit it automatically.'),
                            ]),
                    ]),
            ]);
    }
}
