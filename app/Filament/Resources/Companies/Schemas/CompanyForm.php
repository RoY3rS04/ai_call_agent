<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Nakanakaii\FilamentCountries\Forms\Components\CountrySelect;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                CountrySelect::make('country')
                    ->displayFlags()
                    ->required()
            ]);
    }
}
