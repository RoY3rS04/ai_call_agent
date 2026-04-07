<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Calendar extends Page
{
    protected string $view = 'filament.pages.calendar';

    protected static string | \BackedEnum | null $navigationIcon = Heroicon::OutlinedCalendar;

    public array $calendarDays = [];

    public function mount(): void {
        $user = auth()->user();

        if (! $user || blank($user->google_refresh_token) || blank($user->google_calendar_id)) {
            $this->redirect(CustomerResource::getUrl('index'));
        }
    }

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }
}
