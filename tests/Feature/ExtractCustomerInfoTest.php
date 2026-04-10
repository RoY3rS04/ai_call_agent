<?php

use App\Ai\Agents\AiCallDataExtractorAgent;
use App\Enums\CallRoles;
use App\Enums\LeadSource;
use App\Enums\MeetingStatus;
use App\Jobs\ExtractCustomerInfo;
use App\Models\Call;
use App\Models\CallMessage;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Ai\Prompts\AgentPrompt;

uses(LazilyRefreshDatabase::class);

test('it extracts transcript-driven call data for :dataset', function (array $expectedExtraction, array $transcriptMessages, bool $shouldCreateMeeting) {
    AiCallDataExtractorAgent::fake([
        $expectedExtraction,
    ])->preventStrayPrompts();

    $call = Call::factory()->create([
        'customer_id' => null,
    ]);

    foreach ($transcriptMessages as $transcriptMessage) {
        CallMessage::factory()->for($call)->create($transcriptMessage);
    }

    (new ExtractCustomerInfo($call))->handle();

    assertExtractorReceivedTranscript($transcriptMessages);

    $company = Company::query()->where('name', $expectedExtraction['company']['name'])->first();
    $customer = Customer::query()->where('email', $expectedExtraction['customer']['email'])->first();
    $meeting = Meeting::query()->whereBelongsTo($call)->first();

    expect($company)
        ->not->toBeNull()
        ->country->toBe($expectedExtraction['company']['country']);

    expect($customer)
        ->not->toBeNull()
        ->first_name->toBe($expectedExtraction['customer']['first_name'])
        ->last_name->toBe($expectedExtraction['customer']['last_name'])
        ->phone->toBe($expectedExtraction['customer']['phone'])
        ->timezone->toBe($expectedExtraction['customer']['timezone'])
        ->lead_source->toBe(LeadSource::from($expectedExtraction['customer']['lead_source']))
        ->company->is($company)->toBeTrue();

    expect($call->fresh()->customer?->is($customer))->toBeTrue();

    if (! $shouldCreateMeeting) {
        expect($meeting)->toBeNull();

        return;
    }

    expect($meeting)
        ->not->toBeNull()
        ->call->is($call)->toBeTrue()
        ->customer->is($customer)->toBeTrue()
        ->company->is($company)->toBeTrue()
        ->timezone->toBe($expectedExtraction['meeting']['timezone'])
        ->reason->toBe($expectedExtraction['meeting']['reason'])
        ->source->toBe($expectedExtraction['meeting']['source'])
        ->notes->toBe($expectedExtraction['meeting']['notes'])
        ->status->toBe(MeetingStatus::from($expectedExtraction['meeting']['status']));

    $meetingTimezone = $expectedExtraction['meeting']['timezone'];
    $expectedStart = Carbon::parse($expectedExtraction['meeting']['start_time'], $meetingTimezone)
        ->format('Y-m-d H:i:s');
    $expectedEnd = Carbon::parse($expectedExtraction['meeting']['end_time'], $meetingTimezone)
        ->format('Y-m-d H:i:s');

    expect($meeting?->start_time?->setTimezone($meetingTimezone)->format('Y-m-d H:i:s'))->toBe($expectedStart);
    expect($meeting?->end_time?->setTimezone($meetingTimezone)->format('Y-m-d H:i:s'))->toBe($expectedEnd);
})->with([
    'confirmed meeting in Managua' => [
        'expectedExtraction' => [
            'customer' => [
                'first_name' => 'Ana',
                'last_name' => 'Lopez',
                'email' => 'ana@example.com',
                'phone' => '+50555550123',
                'timezone' => 'America/Managua',
                'lead_source' => LeadSource::LINKEDIN->value,
            ],
            'company' => [
                'name' => 'Acme Labs',
                'country' => 'Nicaragua',
            ],
            'meeting' => [
                'start_time' => '2026-04-15T10:00:00-06:00',
                'end_time' => '2026-04-15T10:30:00-06:00',
                'timezone' => 'America/Managua',
                'reason' => 'Product demo',
                'source' => 'ai_call',
                'notes' => 'Customer wants a walkthrough for the marketing automation flow.',
                'status' => MeetingStatus::CONFIRMED->value,
            ],
        ],
        'transcriptMessages' => [
            [
                'role' => CallRoles::ASSISTANT,
                'content' => welcomeGreeting(),
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'America/Managua.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'Thank you. What is your name?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Hi, my name is Ana Lopez.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'Thanks Ana. What company are you calling from?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'I am calling from Acme Labs.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'Got it. What country is Acme Labs based in?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Nicaragua.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'What email should I use for the meeting details?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Use ana@example.com.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'And what phone number should I keep on file?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Use +50555550123.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'How did you hear about Nerdify?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'I found you on LinkedIn.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'What would you like the meeting to cover?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'I want a product demo.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'I can offer Tuesday, April 15 from 10:00 AM to 10:30 AM your time.',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Yes, that works for me. Please book that demo slot.',
            ],
        ],
        'shouldCreateMeeting' => true,
    ],
    'confirmed meeting in New York' => [
        'expectedExtraction' => [
            'customer' => [
                'first_name' => 'Mia',
                'last_name' => 'Carter',
                'email' => 'mia.carter@example.com',
                'phone' => '+12125550111',
                'timezone' => 'America/New_York',
                'lead_source' => LeadSource::WEBSITE->value,
            ],
            'company' => [
                'name' => 'Northwind Health',
                'country' => 'United States',
            ],
            'meeting' => [
                'start_time' => '2026-04-16T15:00:00-04:00',
                'end_time' => '2026-04-16T15:30:00-04:00',
                'timezone' => 'America/New_York',
                'reason' => 'Onboarding help',
                'source' => 'ai_call',
                'notes' => 'Customer needs help understanding the first-step setup process.',
                'status' => MeetingStatus::CONFIRMED->value,
            ],
        ],
        'transcriptMessages' => [
            [
                'role' => CallRoles::ASSISTANT,
                'content' => welcomeGreeting(),
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'America/New_York.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'Thank you. What is your name?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Hello, this is Mia Carter.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'Thanks Mia. What company are you calling from today?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Northwind Health.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'And which country should I note for Northwind Health?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'United States.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'What is the best email address for follow-up?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'mia.carter@example.com.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'What phone number should we use?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Use +12125550111.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'How did you first find Nerdify?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Through your website.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'What would you like help with in the meeting?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'I need onboarding help for our team.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'I can offer Thursday, April 16 from 3:00 PM to 3:30 PM your time.',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Perfect, yes, please schedule that.',
            ],
        ],
        'shouldCreateMeeting' => true,
    ],
    'customer from Brazil with no meeting requested' => [
        'expectedExtraction' => [
            'customer' => [
                'first_name' => 'Bruna',
                'last_name' => 'Silva',
                'email' => 'bruna.silva@example.com.br',
                'phone' => '+5511987654321',
                'timezone' => 'America/Sao_Paulo',
                'lead_source' => LeadSource::FACEBOOK->value,
            ],
            'company' => [
                'name' => 'Verde Comercio',
                'country' => 'Brazil',
            ],
            'meeting' => [
                'start_time' => null,
                'end_time' => null,
                'timezone' => null,
                'reason' => null,
                'source' => null,
                'notes' => null,
                'status' => null,
            ],
        ],
        'transcriptMessages' => [
            [
                'role' => CallRoles::ASSISTANT,
                'content' => welcomeGreeting(),
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'America/Sao_Paulo.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'Thank you. What is your name?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Hi, my name is Bruna Silva.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'Thanks Bruna. What company are you calling from?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Verde Comercio.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'What country is your company based in?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Brazil.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'What is the best email address for us to keep on file?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'bruna.silva@example.com.br.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'And your phone number?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'Use +5511987654321.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'How did you hear about Nerdify?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'On Facebook.',
            ],
            [
                'role' => CallRoles::ASSISTANT,
                'content' => 'Would you like me to help you book a meeting now?',
            ],
            [
                'role' => CallRoles::CUSTOMER,
                'content' => 'No, not right now. I just wanted to leave my details first.',
            ],
        ],
        'shouldCreateMeeting' => false,
    ],
]);

function assertExtractorReceivedTranscript(array $transcriptMessages): void
{
    AiCallDataExtractorAgent::assertPrompted(function (AgentPrompt $prompt) use ($transcriptMessages): bool {
        $messages = collect($prompt->agent->messages())
            ->map(fn ($message) => [
                'role' => $message->role->value,
                'content' => $message->content,
            ])
            ->values()
            ->all();

        $expectedMessages = collect($transcriptMessages)
            ->map(fn (array $message) => [
                'role' => $message['role'] === CallRoles::ASSISTANT ? 'assistant' : 'user',
                'content' => $message['content'],
            ])
            ->values()
            ->all();

        return $prompt->prompt === 'Extract the relevant data from this call transcript'
            && $messages === $expectedMessages;
    });
}

function welcomeGreeting(): string
{
    return 'Thank you for calling Nerdify. I’m here to help schedule your meeting. To get started, what time zone are you in?';
}
