<?php

namespace App\Enums;

use App\Enums\Traits\UseValueAsLabel;
use BackedEnum;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;

enum CallStatus: string implements HasLabel, HasColor
{
    use UseValueAsLabel;

    case INITIATED = 'Initiated';
    case RINGING = 'Ringing';
    case IN_PROGRESS = 'In Progress';
    case COMPLETED = 'Completed';
    case NO_ANSWER = 'No Answer';
    case BUSY = 'Busy';
    case FAILED = 'Failed';
    case CANCELLED = 'Cancelled';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::INITIATED => Color::Sky,
            self::RINGING => Color::Fuchsia,
            self::IN_PROGRESS => Color::Amber,
            self::COMPLETED => Color::Green,
            self::NO_ANSWER => Color::Orange,
            self::BUSY => Color::Violet,
            self::FAILED => Color::Red,
            self::CANCELLED => Color::Indigo,
        };
    }
}
