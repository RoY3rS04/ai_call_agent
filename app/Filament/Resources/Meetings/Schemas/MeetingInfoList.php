<?php

namespace App\Filament\Resources\Meetings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MeetingInfoList
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Meeting Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('customer.first_name')
                            ->label('Customer')
                            ->formatStateUsing(function ($state, $record): ?string {
                                $fullName = trim($record->customer?->first_name.' '.$record->customer?->last_name);

                                return $fullName !== '' ? $fullName : null;
                            })
                            ->placeholder('-'),
                        TextEntry::make('company.name')
                            ->label('Company')
                            ->placeholder('-'),
                        TextEntry::make('marketingUser.name')
                            ->label('Marketing user')
                            ->placeholder('-'),
                        TextEntry::make('call.twilio_call_sid')
                            ->label('Call SID')
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->badge(),
                        TextEntry::make('source'),
                        TextEntry::make('start_time')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('end_time')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('timezone')
                            ->placeholder('-'),
                        TextEntry::make('confirmed_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('google_calendar_event_id')
                            ->label('Google Calendar event ID')
                            ->placeholder('-'),
                        TextEntry::make('reason')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ]),
                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->placeholder('-'),
                    ]),
            ]);
    }
}
