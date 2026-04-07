<?php

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Livewire\Component;

new class extends Component {

    public array $events = [];
    public array $eventsByDate = [];
    public ?array $selectedEvent = null;
    public bool $isEventModalOpen = false;
    public \Carbon\CarbonInterface $activePeriodDate;
    public \Carbon\CarbonInterface $navigationStartDate;
    public int $monthsPastCurrent = 1;

    public array $dayPositions = [
        'Sunday' => 0,
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6
    ];

    public function mount()
    {
        $this->activePeriodDate = now()->startOfMonth();
        $this->navigationStartDate = $this->activePeriodDate->copy();

        $user = auth()->user();

        if (! $user || blank($user->google_refresh_token) || blank($user->google_calendar_id)) {
            return;
        }

        $response = \Http::withToken($user->getValidGoogleAccessToken())
            ->get("https://www.googleapis.com/calendar/v3/calendars/{$user->google_calendar_id}/events", [
                'timeMin' => now()->startOfMonth()->toIso8601String(),
                'timeMax' => now()->addMonths($this->monthsPastCurrent)->endOfMonth()->toIso8601String(),
                'singleEvents' => 'true',
                'orderBy' => 'startTime',
            ]);

        $items = $response->json('items', []);

        $this->events = array_map(fn(array $event): array => $this->normalizeEvent($event), $items);
        $this->eventsByDate = $this->mapEventsByDate($this->events);
    }

    public function changeDisplayMonth(string $date): void
    {
        $this->activePeriodDate = Carbon::parse($date)->startOfMonth();
    }

    public function openEventModal(string $eventId): void
    {
        foreach ($this->events as $event) {
            if ($event['id'] !== $eventId) {
                continue;
            }

            $this->selectedEvent = $event;
            $this->isEventModalOpen = true;

            return;
        }
    }

    public function closeEventModal(): void
    {
        $this->isEventModalOpen = false;
        $this->selectedEvent = null;
    }

    public function getEventsForDate(Carbon $date): array
    {
        return $this->eventsByDate[$date->toDateString()] ?? [];
    }

    public function getPeriodMonth(Carbon $date): CarbonPeriod
    {
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        return CarbonPeriod::create($start, $end);
    }

    protected function normalizeEvent(array $event): array
    {
        $isAllDay = blank(data_get($event, 'start.dateTime'));
        $start = $this->getEventBoundary($event, 'start');
        $end = $this->getEventBoundary($event, 'end');
        $displayEnd = $isAllDay ? $end->copy()->subDay() : $end->copy();

        if ($displayEnd->lt($start)) {
            $displayEnd = $start->copy();
        }

        return [
            'id' => $event['id'] ?? md5(json_encode($event)),
            'summary' => $event['summary'] ?? 'Untitled event',
            'description' => $event['description'] ?? 'No description',
            'htmlLink' => $event['htmlLink'] ?? null,
            'isAllDay' => $isAllDay,
            'startIso' => $start->toIso8601String(),
            'endIso' => $end->toIso8601String(),
            'startLabel' => $this->formatEventDateLabel($start, $isAllDay),
            'endLabel' => $this->formatEventDateLabel($displayEnd, $isAllDay),
            'chipLabel' => $isAllDay ? 'All day' : $start->format('g:i A'),
            'sortOrder' => $isAllDay ? 0 : $start->getTimestamp(),
        ];
    }

    protected function mapEventsByDate(array $events): array
    {
        $mappedEvents = [];

        foreach ($events as $event) {
            $cursor = Carbon::parse($event['startIso'])->startOfDay();
            $lastDay = Carbon::parse($event['endIso']);

            if ($event['isAllDay']) {
                $lastDay = $lastDay->subDay();
            }

            $lastDay = $lastDay->startOfDay();

            if ($lastDay->lt($cursor)) {
                $lastDay = $cursor->copy();
            }

            while ($cursor->lte($lastDay)) {
                $mappedEvents[$cursor->toDateString()][] = $event;
                $cursor = $cursor->copy()->addDay();
            }
        }

        foreach ($mappedEvents as $dateKey => $dateEvents) {
            usort($dateEvents, fn(array $left, array $right): int => $left['sortOrder'] <=> $right['sortOrder']);
            $mappedEvents[$dateKey] = $dateEvents;
        }

        return $mappedEvents;
    }

    protected function getEventBoundary(array $event, string $boundary): Carbon
    {
        $dateTime = data_get($event, "{$boundary}.dateTime");
        $date = data_get($event, "{$boundary}.date");

        return Carbon::parse($dateTime ?: $date);
    }

    protected function formatEventDateLabel(Carbon $date, bool $isAllDay): string
    {
        return $isAllDay
            ? $date->format('M j, Y')
            : $date->format('M j, Y g:i A');
    }
}
?>

<div class="flex flex-col gap-y-5 text-gray-950 dark:text-gray-100">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-1">
            <h1 class="fi-header-heading">
                Calendar
            </h1>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Viewing {{ $this->activePeriodDate->format('F Y') }}
            </p>
        </div>

        <nav class="flex justify-start sm:justify-end">
            <div
                class="inline-flex rounded-2xl border border-gray-200/80 bg-white/90 p-1 shadow-sm shadow-gray-200/70 backdrop-blur-sm dark:border-white/10 dark:bg-gray-950/80 dark:shadow-black/30">
                @for($i = 0; $i <= $this->monthsPastCurrent; $i++)
                    @php($monthDate = $this->navigationStartDate->copy()->addMonths($i))
                    @php($monthKey = $monthDate->format('Y-m'))

                    <button
                        wire:click="changeDisplayMonth('{{ $monthDate->toDateString() }}')"
                        @class([
                            'rounded-xl px-4 py-2 text-sm font-medium transition',
                            'bg-amber-600 text-white shadow-sm shadow-amber-600/25 dark:bg-amber-500 dark:text-gray-950 dark:shadow-amber-950/40' => $this->activePeriodDate->format('Y-m') === $monthKey,
                            'text-gray-600 hover:bg-gray-100 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-white/8 dark:hover:text-white' => $this->activePeriodDate->format('Y-m') !== $monthKey,
                        ])
                    >
                        {{ $monthDate->monthName }}
                    </button>
                @endfor
            </div>
        </nav>
    </div>

    <div class="grid grid-cols-7 gap-3">
        @foreach(Carbon::getDays() as $day)
            <div
                class="rounded-xl border border-transparent bg-gray-50 px-3 py-2 text-center text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:border-white/5 dark:bg-white/[0.04] dark:text-gray-400">
                {{ $day }}
            </div>
        @endforeach
    </div>
    <section class="grid grid-cols-7 gap-3">
        @foreach($this->getPeriodMonth($this->activePeriodDate) as $idx => $date)
            @if($idx === 0 && ($pos = $this->dayPositions[$date->dayName]) !== 0)
                @for($i = 0; $i < $pos; $i++)
                    <div
                        class="h-[125px] rounded-2xl border border-dashed border-gray-200 bg-gray-50/60 dark:border-white/10 dark:bg-white/[0.02]"></div>
                @endfor
            @endif
            @php($dayEvents = $this->getEventsForDate($date))
            @php($isToday = $date->isToday())
            <div @class([
                    'flex h-[125px] flex-col rounded-2xl border p-4 shadow-sm transition',
                    'border-amber-300 bg-amber-50/80 shadow-amber-100/70 dark:border-amber-500/50 dark:bg-amber-500/10 dark:shadow-amber-950/20' => $isToday,
                    'border-gray-200 bg-white shadow-gray-200/70 dark:border-white/10 dark:bg-gray-900 dark:shadow-black/20' => ! $isToday,
                ])>
                <div class="flex items-start justify-between shrink-0">
                    <div @class([
                            'text-sm font-semibold',
                            'text-amber-900 dark:text-amber-200' => $isToday,
                            'text-gray-900 dark:text-white' => ! $isToday,
                        ])>
                        {{ $date->day }}
                    </div>

                    <div @class([
                            'text-xs',
                            'text-amber-700/80 dark:text-amber-200/70' => $isToday,
                            'text-gray-400 dark:text-gray-500' => ! $isToday,
                        ])>
                        {{ $date->format('D') }}
                    </div>
                </div>

                <div class="mt-2 min-h-0 flex-1 space-y-1.5 overflow-y-auto pr-1">
                    @foreach($dayEvents as $event)
                        <button
                            type="button"
                            wire:click="openEventModal('{{ $event['id'] }}')"
                            class="flex w-full items-center justify-between rounded-xl bg-amber-600 px-3 py-1 text-left text-xs font-semibold text-white shadow-sm shadow-amber-600/20 transition hover:bg-amber-500 dark:bg-amber-500 dark:text-gray-950 dark:shadow-amber-950/30 dark:hover:bg-amber-400"
                        >
                            <div class="truncate">{{ $event['summary'] }}</div>
                            <div class="ml-2 shrink-0 text-[11px] text-white/80 dark:text-gray-950/75">
                                {{ $event['chipLabel'] }}
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>

        @endforeach
    </section>

    @if($this->isEventModalOpen && $this->selectedEvent)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <button
                type="button"
                wire:click="closeEventModal"
                class="absolute inset-0 bg-gray-950/50 backdrop-blur-sm dark:bg-black/70"
                aria-label="Close event modal"
            ></button>

            <div
                class="relative z-10 w-full max-w-xl rounded-[2rem] border border-gray-200 bg-white p-6 shadow-2xl shadow-gray-300/40 dark:border-white/10 dark:bg-gray-900 dark:shadow-black/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[#1696d8]">
                            Calendar Event
                        </p>
                        <h3 class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">
                            {{ $this->selectedEvent['summary'] }}
                        </h3>
                    </div>

                    <button
                        type="button"
                        wire:click="closeEventModal"
                        class="rounded-full border border-gray-200 px-3 py-1.5 text-sm font-medium text-gray-600 transition hover:border-gray-300 hover:text-gray-950 dark:border-white/10 dark:text-gray-300 dark:hover:border-white/20 dark:hover:text-white"
                    >
                        Close
                    </button>
                </div>

                <div class="mt-6 space-y-4">
                    <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-white/[0.03]">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                            Summary
                        </div>
                        <div
                            class="mt-2 text-sm text-gray-900 dark:text-white">{{ $this->selectedEvent['summary'] }}</div>
                    </div>

                    <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-white/[0.03]">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                            Description
                        </div>
                        <div
                            class="mt-2 text-sm text-gray-900 dark:text-white">{{ $this->selectedEvent['description'] }}</div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-white/[0.03]">
                            <div
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                                Start
                            </div>
                            <div
                                class="mt-2 text-sm text-gray-900 dark:text-white">{{ $this->selectedEvent['startLabel'] }}</div>
                        </div>

                        <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-white/[0.03]">
                            <div
                                class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                                End
                            </div>
                            <div
                                class="mt-2 text-sm text-gray-900 dark:text-white">{{ $this->selectedEvent['endLabel'] }}</div>
                        </div>
                    </div>

                    <div class="rounded-2xl bg-gray-50 px-4 py-3 dark:bg-white/[0.03]">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                            Google Calendar Link
                        </div>
                        @if($this->selectedEvent['htmlLink'])
                            <a
                                href="{{ $this->selectedEvent['htmlLink'] }}"
                                target="_blank"
                                rel="noreferrer"
                                class="mt-2 inline-flex text-sm font-medium text-[#1696d8] transition hover:text-[#0d82be]"
                            >
                                Open event in Google Calendar
                            </a>
                        @else
                            <div class="mt-2 text-sm text-gray-900 dark:text-white">No link available</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
