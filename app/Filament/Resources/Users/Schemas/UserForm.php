<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('User Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('User Information')
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->label('Email address')
                                    ->email()
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                TextInput::make('password')
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->rule(Password::default())
                                    ->dehydrated(fn (?string $state): bool => filled($state))
                                    ->helperText('Leave blank to keep the current password.'),
                            ]),
                        Tabs\Tab::make('Access Control')
                            ->columns(1)
                            ->schema([
                                CheckboxList::make('roles')
                                    ->relationship(
                                        name: 'roles',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                                    )
                                    ->columns(2)
                                    ->bulkToggleable()
                                    ->searchable(),
                                CheckboxList::make('permissions')
                                    ->relationship(
                                        name: 'permissions',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('name'),
                                    )
                                    ->columns(2)
                                    ->bulkToggleable()
                                    ->searchable()
                                    ->helperText('Direct permissions are added on top of any permissions inherited from roles.'),
                            ]),
                    ]),
            ]);
    }
}
