<?php

namespace App\Ai\Tools;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CheckMarketingCalendar implements Tool
{
    protected const DEFAULT_DURATION_MINUTES = 30;

    protected const SLOT_INTERVAL_MINUTES = 30;

    protected const ALTERNATIVE_LIMIT = 3;

    protected const BUSINESS_TIMEZONE = 'America/Managua';

    protected const BUSINESS_START_HOUR = 9;

    protected const BUSINESS_END_HOUR = 17;

    protected const LUNCH_START_HOUR = 12;

    protected const LUNCH_END_HOUR = 13;

    protected const BUSINESS_WEEKDAYS = [Carbon::MONDAY, Carbon::TUESDAY, Carbon::WEDNESDAY, Carbon::THURSDAY, Carbon::FRIDAY];

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'This tool\'s goal is to check availability of marketing calendar in order to book meetings';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $requestedStart = Carbon::parse($request->string('book_date_time'), $request->string('timezone'));
        $durationMinutes = max($request->integer('duration_minutes', self::DEFAULT_DURATION_MINUTES), 1);
        $requestedEnd = $requestedStart->copy()->addMinutes($durationMinutes);
        $searchEnd = $requestedStart->copy()->addWeek();
        $maxBusinessDurationMinutes = max(
            (self::LUNCH_START_HOUR - self::BUSINESS_START_HOUR) * 60,
            (self::BUSINESS_END_HOUR - self::LUNCH_END_HOUR) * 60,
        );

        if ($durationMinutes > $maxBusinessDurationMinutes) {
            return json_encode([
                'available' => false,
                'alternatives_found' => false,
                'reason' => 'The requested meeting duration is longer than the available continuous business window.',
                'requested_start' => $requestedStart->toIso8601String(),
                'requested_end' => $requestedEnd->toIso8601String(),
                'timezone' => $request->string('timezone'),
                'duration_minutes' => $durationMinutes,
                'business_timezone' => self::BUSINESS_TIMEZONE,
            ], JSON_PRETTY_PRINT);
        }

        $marketingUsers = User::where(function (Builder $query): void {
            $query
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'marketing');
                })
                ->orWhereHas('permissions', function ($query) {
                    $query->where('name', 'check-calendar');
                });
        })
            ->where(function (Builder $query): void {
                $query
                    ->whereNotNull('google_access_token')
                    ->orWhereNotNull('google_refresh_token');
            })
            ->get();

        if ($marketingUsers->isEmpty()) {
            return json_encode([
                'available' => false,
                'reason' => 'No marketing users with Google Calendar access were found.',
                'requested_start' => $requestedStart->toIso8601String(),
                'requested_end' => $requestedEnd->toIso8601String(),
                'timezone' => $request->string('timezone'),
            ], JSON_PRETTY_PRINT);
        }

        $errors = [];
        $alternatives = [];

        foreach ($marketingUsers as $marketingUser) {
            $calendarId = $marketingUser->google_calendar_id ?: 'primary';

            try {
                $response = Http::withToken($marketingUser->getValidGoogleAccessToken())
                    ->acceptJson()
                    ->post('https://www.googleapis.com/calendar/v3/freeBusy', [
                        'timeMin' => $requestedStart->toIso8601String(),
                        'timeMax' => $searchEnd->toIso8601String(),
                        'timeZone' => $request->string('timezone'),
                        'items' => [
                            ['id' => $calendarId],
                        ],
                    ])
                    ->throw();
            } catch (\Throwable $exception) {
                $errors[] = [
                    'user_id' => $marketingUser->getKey(),
                    'email' => $marketingUser->email,
                    'calendar_id' => $calendarId,
                    'message' => $exception->getMessage(),
                ];

                continue;
            }

            $busySlots = $this->normalizeBusySlots(
                data_get($response->json(), "calendars.{$calendarId}.busy", []),
                $request->string('timezone'),
            );

            if (
                $this->slotFitsBusinessHours($requestedStart, $requestedEnd) &&
                ! $this->slotOverlapsBusy($requestedStart, $requestedEnd, $busySlots)
            ) {
                return json_encode([
                    'available' => true,
                    'alternatives_found' => false,
                    'user_id' => $marketingUser->getKey(),
                    'user_name' => $marketingUser->name,
                    'user_email' => $marketingUser->email,
                    'calendar_id' => $calendarId,
                    'requested_start' => $requestedStart->toIso8601String(),
                    'requested_end' => $requestedEnd->toIso8601String(),
                    'timezone' => $request->string('timezone'),
                    'duration_minutes' => $durationMinutes,
                    'business_timezone' => self::BUSINESS_TIMEZONE,
                ], JSON_PRETTY_PRINT);
            }

            $alternatives = [
                ...$alternatives,
                ...$this->findAlternativeSlots(
                    $busySlots,
                    $requestedStart,
                    $searchEnd,
                    $durationMinutes,
                    [
                        'user_id' => $marketingUser->getKey(),
                        'user_name' => $marketingUser->name,
                        'user_email' => $marketingUser->email,
                        'calendar_id' => $calendarId,
                    ],
                ),
            ];
        }

        usort($alternatives, fn (array $left, array $right): int => strcmp($left['start_iso'], $right['start_iso']));
        $alternatives = $this->uniqueAlternativesByStart($alternatives);
        $alternatives = array_slice($alternatives, 0, self::ALTERNATIVE_LIMIT);

        if (! empty($alternatives)) {
            return json_encode([
                'available' => false,
                'alternatives_found' => true,
                'reason' => 'The requested slot is unavailable, but there are open alternatives within the next week.',
                'requested_start' => $requestedStart->toIso8601String(),
                'requested_end' => $requestedEnd->toIso8601String(),
                'timezone' => $request->string('timezone'),
                'duration_minutes' => $durationMinutes,
                'business_timezone' => self::BUSINESS_TIMEZONE,
                'alternatives' => $alternatives,
                'errors' => $errors,
            ], JSON_PRETTY_PRINT);
        }

        return json_encode([
            'available' => false,
            'alternatives_found' => false,
            'reason' => 'All eligible marketing calendars are busy for the requested time and no alternatives were found within one week.',
            'requested_start' => $requestedStart->toIso8601String(),
            'requested_end' => $requestedEnd->toIso8601String(),
            'timezone' => $request->string('timezone'),
            'duration_minutes' => $durationMinutes,
            'business_timezone' => self::BUSINESS_TIMEZONE,
            'errors' => $errors,
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'book_date_time' => $schema->string()->description('Requested meeting start time in a parseable datetime format.')->required(),
            'timezone' => $schema->string()->description('The caller timezone, for example America/New_York.')->required(),
            'duration_minutes' => $schema->integer()->description('Meeting duration in minutes. Defaults to 30 if omitted.'),
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $busySlots
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    protected function normalizeBusySlots(array $busySlots, string $timezone): array
    {
        return array_map(function (array $slot) use ($timezone): array {
            return [
                'start' => Carbon::parse($slot['start'], $timezone),
                'end' => Carbon::parse($slot['end'], $timezone),
            ];
        }, $busySlots);
    }

    /**
     * @param  array<int, array{start: Carbon, end: Carbon}>  $busySlots
     */
    protected function slotOverlapsBusy(Carbon $start, Carbon $end, array $busySlots): bool
    {
        foreach ($busySlots as $busySlot) {
            if ($start->lt($busySlot['end']) && $end->gt($busySlot['start'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{start: Carbon, end: Carbon}>  $busySlots
     * @param  array<string, mixed>  $userContext
     * @return array<int, array<string, mixed>>
     */
    protected function findAlternativeSlots(
        array $busySlots,
        Carbon $requestedStart,
        Carbon $searchEnd,
        int $durationMinutes,
        array $userContext,
    ): array {
        $alternatives = [];
        $candidateStart = $this->moveToNextBusinessSlot(
            $requestedStart->copy()->addMinutes(self::SLOT_INTERVAL_MINUTES),
            $durationMinutes,
        );

        while ($candidateStart->lt($searchEnd) && count($alternatives) < self::ALTERNATIVE_LIMIT) {
            $candidateEnd = $candidateStart->copy()->addMinutes($durationMinutes);

            if (
                $this->slotFitsBusinessHours($candidateStart, $candidateEnd) &&
                ! $this->slotOverlapsBusy($candidateStart, $candidateEnd, $busySlots)
            ) {
                $alternatives[] = [
                    ...$userContext,
                    'start_iso' => $candidateStart->toIso8601String(),
                    'end_iso' => $candidateEnd->toIso8601String(),
                    'start_label' => $candidateStart->format('l, M j \a\t g:i A'),
                    'end_label' => $candidateEnd->format('l, M j \a\t g:i A'),
                ];
            }

            $candidateStart = $this->moveToNextBusinessSlot(
                $candidateStart->copy()->addMinutes(self::SLOT_INTERVAL_MINUTES),
                $durationMinutes,
            );
        }

        return $alternatives;
    }

    protected function roundUpToNextSlot(Carbon $date): Carbon
    {
        $minuteRemainder = $date->minute % self::SLOT_INTERVAL_MINUTES;

        if ($minuteRemainder === 0 && $date->second === 0) {
            return $date;
        }

        return $date
            ->copy()
            ->addMinutes(self::SLOT_INTERVAL_MINUTES - $minuteRemainder)
            ->second(0);
    }

    protected function slotFitsBusinessHours(Carbon $start, Carbon $end): bool
    {
        $businessStart = $start->copy()->setTimezone(self::BUSINESS_TIMEZONE);
        $businessEnd = $end->copy()->setTimezone(self::BUSINESS_TIMEZONE);

        if (! $businessStart->isSameDay($businessEnd)) {
            return false;
        }

        if (! in_array($businessStart->dayOfWeek, self::BUSINESS_WEEKDAYS, true)) {
            return false;
        }

        $dayOpen = $businessStart->copy()->startOfDay()->setTime(self::BUSINESS_START_HOUR, 0, 0);
        $dayClose = $businessStart->copy()->startOfDay()->setTime(self::BUSINESS_END_HOUR, 0, 0);
        $lunchStart = $businessStart->copy()->startOfDay()->setTime(self::LUNCH_START_HOUR, 0, 0);
        $lunchEnd = $businessStart->copy()->startOfDay()->setTime(self::LUNCH_END_HOUR, 0, 0);

        if (! ($businessStart->gte($dayOpen) && $businessEnd->lte($dayClose))) {
            return false;
        }

        return ! ($businessStart->lt($lunchEnd) && $businessEnd->gt($lunchStart));
    }

    protected function moveToNextBusinessSlot(Carbon $date, int $durationMinutes): Carbon
    {
        $roundedDate = $this->roundUpToNextSlot($date);
        $businessDate = $roundedDate->copy()->setTimezone(self::BUSINESS_TIMEZONE);

        while (! in_array($businessDate->dayOfWeek, self::BUSINESS_WEEKDAYS, true)) {
            $businessDate = $businessDate->copy()->addDay()->startOfDay()->setTime(self::BUSINESS_START_HOUR, 0, 0);
        }

        $dayOpen = $businessDate->copy()->startOfDay()->setTime(self::BUSINESS_START_HOUR, 0, 0);
        $lunchStart = $businessDate->copy()->startOfDay()->setTime(self::LUNCH_START_HOUR, 0, 0);
        $lunchEnd = $businessDate->copy()->startOfDay()->setTime(self::LUNCH_END_HOUR, 0, 0);
        $latestStart = $businessDate
            ->copy()
            ->startOfDay()
            ->setTime(self::BUSINESS_END_HOUR, 0, 0)
            ->subMinutes($durationMinutes);
        $latestMorningStart = $lunchStart->copy()->subMinutes($durationMinutes);

        if ($businessDate->lt($dayOpen)) {
            return $dayOpen->copy()->setTimezone($roundedDate->timezoneName);
        }

        if ($businessDate->lt($lunchEnd) && $businessDate->gt($latestMorningStart)) {
            return $lunchEnd->copy()->setTimezone($roundedDate->timezoneName);
        }

        if ($businessDate->gte($lunchStart) && $businessDate->lt($lunchEnd)) {
            return $lunchEnd->copy()->setTimezone($roundedDate->timezoneName);
        }

        if ($businessDate->gt($latestStart)) {
            $nextBusinessDay = $dayOpen->copy()->addDay();

            while (! in_array($nextBusinessDay->dayOfWeek, self::BUSINESS_WEEKDAYS, true)) {
                $nextBusinessDay = $nextBusinessDay->copy()->addDay();
            }

            return $nextBusinessDay->startOfDay()->setTime(self::BUSINESS_START_HOUR, 0, 0)->setTimezone($roundedDate->timezoneName);
        }

        return $roundedDate;
    }

    /**
     * @param  array<int, array<string, mixed>>  $alternatives
     * @return array<int, array<string, mixed>>
     */
    protected function uniqueAlternativesByStart(array $alternatives): array
    {
        $uniqueAlternatives = [];
        $seenStarts = [];

        foreach ($alternatives as $alternative) {
            if (isset($seenStarts[$alternative['start_iso']])) {
                continue;
            }

            $seenStarts[$alternative['start_iso']] = true;
            $uniqueAlternatives[] = $alternative;
        }

        return $uniqueAlternatives;
    }
}
