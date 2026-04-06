<?php

namespace App\Filament\Pages;

use Illuminate\Contracts\Support\Htmlable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Calendar extends Page
{
    protected string $view = 'filament.pages.calendar';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCalendar;

    public array $calendarDays = [];

    public function mount(): void {

//        $user = auth()->user();
//
//        $response = \Http::withToken($user->google_access_token)
//            ->get("https://www.googleapis.com/calendar/v3/calendars/{$user->google_calendar_id}/events", [
//                'timeMin' => now()->startOfMonth()->toIso8601String(),
//                'timeMax' => now()->addMonth()->endOfMonth()->toIso8601String(),
//                'singleEvents' => 'true',
//                'orderBy' => 'startTime',
//            ]);
//
//        dd($response->body());
    }

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }
}
