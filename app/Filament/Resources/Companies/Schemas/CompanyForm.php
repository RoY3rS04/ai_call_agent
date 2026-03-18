<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Nakanakaii\FilamentCountries\Forms\Components\CountrySelect;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company Information')
                    ->columnSpanFull()
                ->schema([
                    TextInput::make('name')
                        ->required(),
                    CountrySelect::make('country')
                        ->displayFlags()
                        ->required()
                ])
            ]);
    }
}
