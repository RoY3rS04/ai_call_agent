<?php

namespace App\Filament\Resources\Calls\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CallInfoList
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Call Details')
                    ->schema([
                        TextEntry::make('id'),
                        TextEntry::make('twilio_call_sid'),
                        TextEntry::make('status'),
                        TextEntry::make('start_time')->dateTime(),
                        TextEntry::make('end_time')->dateTime(),
                        TextEntry::make('duration'),
                    ])
                ->columnSpanFull()
                ->columns(3),
                Section::make('Call Messages')
                    ->schema([
                        ViewEntry::make('callMessages')
                            ->view('filament.call-message')
                    ])
                    ->columnSpanFull()
            ]);
    }
}
