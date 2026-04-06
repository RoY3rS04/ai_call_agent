<?php

namespace App\Filament\Resources\Meetings\Schemas;

use App\Enums\MeetingStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Tapp\FilamentTimezoneField\Forms\Components\TimezoneSelect;

class MeetingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Meeting Details')
                    ->columns(2)
                    ->schema([
                        Select::make('customer_id')
                            ->relationship(name: 'customer', titleAttribute: 'first_name')
                            ->getOptionLabelFromRecordUsing(fn ($record): string => trim($record->first_name.' '.$record->last_name))
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('company_id')
                            ->relationship(name: 'company', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('call_id')
                            ->relationship(name: 'call', titleAttribute: 'twilio_call_sid')
                            ->searchable()
                            ->preload(),
                        Select::make('marketing_user_id')
                            ->label('Marketing user')
                            ->relationship(name: 'marketingUser', titleAttribute: 'name')
                            ->searchable()
                            ->preload(),
                        DateTimePicker::make('start_time')
                            ->seconds(false)
                            ->required(),
                        DateTimePicker::make('end_time')
                            ->seconds(false)
                            ->required(),
                        TimezoneSelect::make('timezone')
                            ->searchable()
                            ->required(),
                        Select::make('status')
                            ->options(MeetingStatus::class)
                            ->enum(MeetingStatus::class)
                            ->default(MeetingStatus::PENDING)
                            ->required(),
                        TextInput::make('source')
                            ->default('ai_call')
                            ->required(),
                        DateTimePicker::make('confirmed_at')
                            ->seconds(false),
                        TextInput::make('google_calendar_event_id')
                            ->label('Google Calendar event ID'),
                    ]),
                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
