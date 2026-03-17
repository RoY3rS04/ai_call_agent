<?php

namespace App\Enums;

use App\Enums\Traits\UseValueAsLabel;
use BackedEnum;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum LeadSource: string implements HasLabel, HasIcon, HasColor
{
    use UseValueAsLabel;

    case LARAVEL_PARTNER = 'Laravel Partner';
    case LINKEDIN = 'LinkedIn';
    case WEBSITE = 'Website';
    case FACEBOOK = 'Facebook';
    case INSTAGRAM = 'Instagram';

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::LARAVEL_PARTNER => Color::Red,
            self::LINKEDIN => Color::Sky,
            self::WEBSITE => Color::Gray,
            self::FACEBOOK => Color::Blue,
            self::INSTAGRAM => Color::Purple,
        };
    }

    public function getIcon(): string|BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::LARAVEL_PARTNER => Heroicon::OutlinedCommandLine,
            self::LINKEDIN => Heroicon::OutlinedLink,
            self::WEBSITE => Heroicon::OutlinedWindow,
            self::FACEBOOK => Heroicon::OutlinedFaceSmile,
            self::INSTAGRAM => Heroicon::OutlinedCamera
        };
    }
}
