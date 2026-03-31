<?php

namespace App\Filament\Resources\Calls\Pages;

use App\Filament\Resources\Calls\CallResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class ListCalls extends ListRecords
{
    protected static string $resource = CallResource::class;

    protected function getHeaderActions(): array
    {
        return [
        ];
    }

    public function getFooter(): ?View
    {
        return \view('filament.realtime-context', [
            'page' => 'calls-list',
            'channels' => [
                'calls'
            ]
        ]);
    }

    #[On('calls-table-status-updated')]
    public function refreshTableForStatusChange(string $callSid): void {

        $records = $this->getVisibleTableRecords();

        if (!$records->contains(fn ($record) => $record->callSid === $callSid)) {
            return;
        }

        $this->flushCachedTableRecords();
    }

    #[On('calls-table-call-started')]
    public function refreshTableForNewCall(): void {
        $this->flushCachedTableRecords();
    }

    protected function getVisibleTableRecords(): Collection
    {
        $records = $this->getTableRecords();

        if ($records instanceof Paginator || $records instanceof CursorPaginator) {
            return collect($records->items());
        }

        return collect($records->all());
    }
}

