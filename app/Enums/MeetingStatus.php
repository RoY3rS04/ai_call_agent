<?php

namespace App\Enums;

use App\Enums\Traits\UseValueAsLabel;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MeetingStatus: string implements HasLabel, HasColor
{
    use UseValueAsLabel;

    case PENDING = 'Pending';
    case CONFIRMED = 'Confirmed';
    case COMPLETED = 'Completed';
    case CANCELLED = 'Cancelled';
    case NO_SHOW = 'No Show';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => Color::Amber,
            self::CONFIRMED => Color::Sky,
            self::COMPLETED => Color::Green,
            self::CANCELLED => Color::Gray,
            self::NO_SHOW => Color::Red,
        };
    }
}
