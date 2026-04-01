<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Role Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Role Information')
                            ->schema([
                                Hidden::make('guard_name')
                                    ->default(config('auth.defaults.guard')),
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                            ]),
                        Tabs\Tab::make('Permissions')
                            ->schema([
                                CheckboxList::make('permissions')
                                    ->relationship(
                                        name: 'permissions',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                                    )
                                    ->columns(2)
                                    ->bulkToggleable()
                                    ->searchable()
                                    ->helperText('Permissions assigned here are granted to every user with this role.'),
                            ]),
                    ]),
            ]);
    }
}
