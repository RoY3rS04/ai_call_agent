<?php

namespace App\Ai\Tools;

use App\Enums\MeetingStatus;
use App\Models\Call;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class BookMarketingMeeting implements Tool
{
    public function __construct(public Call $call) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return "This tool's function is to create the meeting event in the assigned marketing user's calendar.";
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $meeting = $this->resolveMeeting($request);
        $requestedStart = Carbon::parse($request->string('requested_start'), $request->string('timezone'));
        $requestedEnd = Carbon::parse($request->string('requested_end'), $request->string('timezone'));
        $storageStart = $requestedStart->copy()->setTimezone(config('app.timezone', 'UTC'));
        $storageEnd = $requestedEnd->copy()->setTimezone(config('app.timezone', 'UTC'));

        if ($requestedEnd->lte($requestedStart)) {
            return json_encode([
                'booked' => false,
                'reason' => 'The meeting end time must be after the start time.',
                'requested_start' => $requestedStart->toIso8601String(),
                'requested_end' => $requestedEnd->toIso8601String(),
                'timezone' => $request->string('timezone'),
            ], JSON_PRETTY_PRINT);
        }

        if ($requestedStart->isPast()) {
            return json_encode([
                'booked' => false,
                'reason' => 'The requested meeting slot is in the past.',
                'requested_start' => $requestedStart->toIso8601String(),
                'requested_end' => $requestedEnd->toIso8601String(),
                'timezone' => $request->string('timezone'),
            ], JSON_PRETTY_PRINT);
        }

        $marketingUser = User::query()
            ->whereKey($request->integer('user_id'))
            ->where('email', $request->string('user_email'))
            ->first();

        if ($marketingUser === null) {
            return json_encode([
                'booked' => false,
                'reason' => 'The selected marketing user could not be found.',
                'user_id' => $request->integer('user_id'),
                'user_email' => $request->string('user_email'),
            ], JSON_PRETTY_PRINT);
        }

        $calendarId = trim($request->string('calendar_id', $marketingUser->google_calendar_id ?: 'primary'));
        $reason = $this->cleanString($request['reason'] ?? null);
        $customerName = $this->cleanString($request['customer_name'] ?? null);
        $customerEmail = $this->cleanString($request['customer_email'] ?? null);
        $reason ??= $meeting?->reason;

        if (
            $meeting !== null &&
            $meeting->status === MeetingStatus::CONFIRMED &&
            filled($meeting->google_calendar_event_id)
        ) {
            return json_encode([
                'booked' => true,
                'already_booked' => true,
                'meeting_id' => $meeting->getKey(),
                'google_calendar_event_id' => $meeting->google_calendar_event_id,
                'calendar_id' => $calendarId,
                'user_id' => $marketingUser->getKey(),
                'user_email' => $marketingUser->email,
                'requested_start' => $meeting->start_time?->toIso8601String() ?? $requestedStart->toIso8601String(),
                'requested_end' => $meeting->end_time?->toIso8601String() ?? $requestedEnd->toIso8601String(),
                'timezone' => $meeting->timezone ?? $request->string('timezone'),
            ], JSON_PRETTY_PRINT);
        }

        try {
            $token = $marketingUser->getValidGoogleAccessToken();
            $busySlots = data_get(
                Http::withToken($token)
                    ->acceptJson()
                    ->post('https://www.googleapis.com/calendar/v3/freeBusy', [
                        'timeMin' => $requestedStart->toIso8601String(),
                        'timeMax' => $requestedEnd->toIso8601String(),
                        'timeZone' => $request->string('timezone'),
                        'items' => [
                            ['id' => $calendarId],
                        ],
                    ])
                    ->throw()
                    ->json(),
                "calendars.{$calendarId}.busy",
                [],
            );

            if (! empty($busySlots)) {
                return json_encode([
                    'booked' => false,
                    'reason' => 'The selected slot is no longer available.',
                    'calendar_id' => $calendarId,
                    'user_id' => $marketingUser->getKey(),
                    'user_email' => $marketingUser->email,
                    'requested_start' => $requestedStart->toIso8601String(),
                    'requested_end' => $requestedEnd->toIso8601String(),
                    'timezone' => $request->string('timezone'),
                ], JSON_PRETTY_PRINT);
            }

            $eventPayload = [
                'summary' => $reason !== null
                    ? "Nerdify Meeting: {$reason}"
                    : 'Nerdify Meeting',
                'description' => implode("\n", array_filter([
                    'Booked by the Nerdify AI phone assistant.',
                    $customerName !== null ? "Customer: {$customerName}" : null,
                    $customerEmail !== null ? "Customer email: {$customerEmail}" : null,
                    $reason !== null ? "Reason: {$reason}" : null,
                ])),
                'start' => [
                    'dateTime' => $requestedStart->toIso8601String(),
                    'timeZone' => $request->string('timezone'),
                ],
                'end' => [
                    'dateTime' => $requestedEnd->toIso8601String(),
                    'timeZone' => $request->string('timezone'),
                ],
                'attendees' => array_values(array_filter([
                    $customerEmail !== null
                        ? array_filter([
                            'email' => $customerEmail,
                            'displayName' => $customerName,
                        ], static fn (mixed $value): bool => $value !== null)
                        : null,
                ])),
            ];

            $event = Http::withToken($token)
                ->acceptJson()
                ->post(
                    sprintf(
                        'https://www.googleapis.com/calendar/v3/calendars/%s/events',
                        rawurlencode($calendarId),
                    ),
                    $eventPayload,
                )
                ->throw()
                ->json();
        } catch (\Throwable $exception) {
            return json_encode([
                'booked' => false,
                'reason' => 'The meeting could not be created in Google Calendar.',
                'calendar_id' => $calendarId,
                'user_id' => $marketingUser->getKey(),
                'user_email' => $marketingUser->email,
                'requested_start' => $requestedStart->toIso8601String(),
                'requested_end' => $requestedEnd->toIso8601String(),
                'timezone' => $request->string('timezone'),
                'error' => $exception->getMessage(),
            ], JSON_PRETTY_PRINT);
        }

        if ($meeting !== null) {
            DB::transaction(function () use (
                $meeting,
                $marketingUser,
                $storageStart,
                $storageEnd,
                $request,
                $reason,
                $event,
            ): void {
                $meeting->fill(array_filter([
                    'marketing_user_id' => $marketingUser->getKey(),
                    'start_time' => $storageStart,
                    'end_time' => $storageEnd,
                    'timezone' => $request->string('timezone'),
                    'google_calendar_event_id' => data_get($event, 'id'),
                    'status' => MeetingStatus::CONFIRMED->value,
                    'confirmed_at' => now(),
                    'reason' => $reason ?? $meeting->reason,
                    'source' => $meeting->source ?: 'ai_call',
                ], static fn (mixed $value): bool => $value !== null));

                if ($meeting->customer === null && $meeting->call?->customer !== null) {
                    $meeting->customer()->associate($meeting->call->customer);
                }

                if ($meeting->company === null && $meeting->call?->customer?->company !== null) {
                    $meeting->company()->associate($meeting->call->customer->company);
                }

                $meeting->save();
            });
        }

        return json_encode([
            'booked' => true,
            'meeting_id' => $meeting?->getKey(),
            'google_calendar_event_id' => data_get($event, 'id'),
            'google_calendar_event_link' => data_get($event, 'htmlLink'),
            'calendar_id' => $calendarId,
            'user_id' => $marketingUser->getKey(),
            'user_email' => $marketingUser->email,
            'requested_start' => $requestedStart->toIso8601String(),
            'requested_end' => $requestedEnd->toIso8601String(),
            'timezone' => $request->string('timezone'),
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'meeting_id' => $schema->integer(),
            'call_id' => $schema->integer(),
            'user_id' => $schema->integer()->required(),
            'calendar_id' => $schema->string()->required(),
            'user_email' => $schema->string()->required(),
            'requested_start' => $schema->string()->required(),
            'requested_end' => $schema->string()->required(),
            'timezone' => $schema->string()->required(),
            'reason' => $schema->string(),
            'customer_name' => $schema->string(),
            'customer_email' => $schema->string(),
        ];
    }

    protected function resolveMeeting(Request $request): ?Meeting
    {
        $meetingId = $request->filled('meeting_id')
            ? $request->integer('meeting_id')
            : null;

        if ($meetingId !== null) {
            return Meeting::query()->find($meetingId);
        }

        $call = $this->call;

        if ($request->filled('call_id')) {
            $resolvedCall = Call::query()->find($request->integer('call_id'));

            if ($resolvedCall !== null) {
                $call = $resolvedCall;
            }
        }

        $meeting = Meeting::firstOrNew([
            'call_id' => $call->getKey(),
        ]);

        if (! $meeting->exists) {
            $meeting->call()->associate($call);
        }

        return $meeting;
    }

    protected function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
