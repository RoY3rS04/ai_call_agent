<?php

namespace App\Filament\Resources\Calls\Pages;

use App\Filament\Resources\Calls\CallResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\View\View;

class ViewCall extends ViewRecord
{
    protected static string $resource = CallResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
        ];
    }

    public function getFooter(): ?View
    {
        return \view('filament.realtime-context', [
            'page' => 'view-call',
            'channels' => [
                'call.' . $this->getRecord()->twilio_call_sid,
            ]
        ]);
    }
}
