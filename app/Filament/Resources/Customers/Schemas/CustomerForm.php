<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\LeadSource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Nakanakaii\FilamentCountries\Forms\Components\CountrySelect;
use Tapp\FilamentTimezoneField\Forms\Components\TimezoneSelect;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {

        return $schema
            ->components([
                Tabs::make('Customer Tabs')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Company Information')
                            ->columns(2)
                            ->schema([
                                Select::make('company_id')
                                    ->relationship(name: 'company', titleAttribute: 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required(),
                                        CountrySelect::make('country')
                                            ->displayFlags()
                                            ->required()
                                    ])
                                    ->required(),
                                Select::make('lead_source')
                                    ->options(LeadSource::class)
                                    ->enum(LeadSource::class)
                                    ->searchable()
                                    ->default(LeadSource::WEBSITE)
                                    ->required(),
                            ]),
                        Tabs\Tab::make('Customer Information')
                            ->columns(2)
                        ->schema([
                            TextInput::make('first_name')
                                ->required(),
                            TextInput::make('last_name')
                                ->required(),
                            TextInput::make('email')
                                ->label('Email address')
                                ->email()
                                ->required(),
                            TextInput::make('phone')
                                ->tel()
                                ->required(),
                            TimezoneSelect::make('timezone')
                                ->searchable()
                                ->required(),
                        ])
                    ])
            ]);
    }
}
