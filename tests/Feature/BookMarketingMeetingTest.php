<?php

use App\Ai\Tools\BookMarketingMeeting;
use App\Enums\MeetingStatus;
use App\Models\Call;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Tools\Request as ToolRequest;

uses(LazilyRefreshDatabase::class);

test('it books a marketing meeting in google calendar and syncs the local meeting record', function () {
    Http::preventStrayRequests();

    $call = Call::factory()->create([
        'customer_id' => null,
    ]);

    $marketingUser = User::factory()->create([
        'name' => 'Marketing Manager',
        'email' => 'marketing.manager@example.com',
        'google_access_token' => 'valid-access-token',
        'google_token_expires_at' => now()->addHour(),
        'google_calendar_id' => 'primary',
    ]);

    Http::fake([
        'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
            'calendars' => [
                'primary' => [
                    'busy' => [],
                ],
            ],
        ], 200),
        'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response([
            'id' => 'google-event-123',
            'htmlLink' => 'https://calendar.google.com/event?eid=google-event-123',
        ], 200),
    ]);

    $tool = new BookMarketingMeeting($call);

    $response = json_decode((string) $tool->handle(new ToolRequest([
        'user_id' => $marketingUser->getKey(),
        'calendar_id' => 'primary',
        'user_email' => $marketingUser->email,
        'requested_start' => '2026-04-20T14:00:00-06:00',
        'requested_end' => '2026-04-20T14:30:00-06:00',
        'timezone' => 'America/Managua',
        'reason' => 'Product demo',
        'customer_name' => 'Ana Lopez',
        'customer_email' => 'ana@example.com',
    ])), true, flags: JSON_THROW_ON_ERROR);

    $meeting = Meeting::query()->whereBelongsTo($call)->first();

    expect($response['booked'])->toBeTrue()
        ->and($response['meeting_id'])->toBe($meeting?->getKey())
        ->and($response['google_calendar_event_id'])->toBe('google-event-123')
        ->and($response['google_calendar_event_link'])->toBe('https://calendar.google.com/event?eid=google-event-123')
        ->and($response['calendar_id'])->toBe('primary')
        ->and($response['user_id'])->toBe($marketingUser->getKey())
        ->and($response['user_email'])->toBe($marketingUser->email)
        ->and($response['timezone'])->toBe('America/Managua');

    expect($meeting)
        ->not->toBeNull()
        ->call->is($call)->toBeTrue()
        ->marketing_user_id->toBe($marketingUser->getKey())
        ->timezone->toBe('America/Managua')
        ->google_calendar_event_id->toBe('google-event-123')
        ->status->toBe(MeetingStatus::CONFIRMED)
        ->reason->toBe('Product demo')
        ->source->toBe('ai_call')
        ->customer_id->toBeNull()
        ->company_id->toBeNull();

    expect($meeting?->confirmed_at)->not->toBeNull();
    expect($meeting?->start_time?->setTimezone('America/Managua')->format('Y-m-d H:i:s'))->toBe('2026-04-20 14:00:00');
    expect($meeting?->end_time?->setTimezone('America/Managua')->format('Y-m-d H:i:s'))->toBe('2026-04-20 14:30:00');

    Http::assertSentCount(2);

    Http::assertSent(function (HttpClientRequest $request): bool {
        if ($request->url() !== 'https://www.googleapis.com/calendar/v3/freeBusy') {
            return false;
        }

        $data = $request->data();

        return $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer valid-access-token')
            && $data['timeMin'] === '2026-04-20T14:00:00-06:00'
            && $data['timeMax'] === '2026-04-20T14:30:00-06:00'
            && $data['timeZone'] === 'America/Managua'
            && $data['items'][0]['id'] === 'primary';
    });

    Http::assertSent(function (HttpClientRequest $request): bool {
        if ($request->url() !== 'https://www.googleapis.com/calendar/v3/calendars/primary/events') {
            return false;
        }

        $data = $request->data();

        return $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer valid-access-token')
            && $data['summary'] === 'Nerdify Meeting: Product demo'
            && $data['start']['dateTime'] === '2026-04-20T14:00:00-06:00'
            && $data['start']['timeZone'] === 'America/Managua'
            && $data['end']['dateTime'] === '2026-04-20T14:30:00-06:00'
            && $data['end']['timeZone'] === 'America/Managua'
            && $data['attendees'][0]['email'] === 'ana@example.com'
            && $data['attendees'][0]['displayName'] === 'Ana Lopez'
            && str_contains($data['description'], 'Customer: Ana Lopez')
            && str_contains($data['description'], 'Customer email: ana@example.com')
            && str_contains($data['description'], 'Reason: Product demo');
    });
});

test('it does not create a google event when the selected slot is no longer available', function () {
    Http::preventStrayRequests();

    $call = Call::factory()->create([
        'customer_id' => null,
    ]);

    $marketingUser = User::factory()->create([
        'name' => 'Marketing Manager',
        'email' => 'marketing.manager@example.com',
        'google_access_token' => 'valid-access-token',
        'google_token_expires_at' => now()->addHour(),
        'google_calendar_id' => 'primary',
    ]);

    Http::fake([
        'https://www.googleapis.com/calendar/v3/freeBusy' => Http::response([
            'calendars' => [
                'primary' => [
                    'busy' => [
                        [
                            'start' => '2026-04-20T14:00:00-06:00',
                            'end' => '2026-04-20T14:30:00-06:00',
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $tool = new BookMarketingMeeting($call);

    $response = json_decode((string) $tool->handle(new ToolRequest([
        'user_id' => $marketingUser->getKey(),
        'calendar_id' => 'primary',
        'user_email' => $marketingUser->email,
        'requested_start' => '2026-04-20T14:00:00-06:00',
        'requested_end' => '2026-04-20T14:30:00-06:00',
        'timezone' => 'America/Managua',
        'reason' => 'Product demo',
    ])), true, flags: JSON_THROW_ON_ERROR);

    expect($response['booked'])->toBeFalse()
        ->and($response['reason'])->toBe('The selected slot is no longer available.')
        ->and($response['calendar_id'])->toBe('primary')
        ->and($response['user_id'])->toBe($marketingUser->getKey());

    expect(Meeting::query()->whereBelongsTo($call)->exists())->toBeFalse();

    Http::assertSentCount(1);

    Http::assertSent(function (HttpClientRequest $request): bool {
        return $request->url() === 'https://www.googleapis.com/calendar/v3/freeBusy';
    });

    Http::assertNotSent(function (HttpClientRequest $request): bool {
        return $request->url() === 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
    });
});
