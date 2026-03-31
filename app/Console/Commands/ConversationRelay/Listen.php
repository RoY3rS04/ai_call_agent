<?php

namespace App\Console\Commands\ConversationRelay;

use App\Ai\Agents\AiCallAgent;
use App\Enums\CallRoles;
use App\Enums\CallStatus;
use App\Enums\LanguageCode;
use App\Enums\TwilioMessageType;
use App\Events\CallStarted;
use App\Events\InboundCallMessage;
use App\Events\NewCallMessage;
use App\Events\OutboundCallMessage;
use App\Models\Call;
use App\Models\CallMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Stringable;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

class Listen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conversation-relay:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will listen for twilio messages published from go\'s websocket';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Redis::connection('subscribe')->subscribe(['twilio:inbound'], function ($message) {
            $jsonMsg = json_decode($message, true);
            $data = is_string($jsonMsg['data'] ?? null)
                ? json_decode($jsonMsg['data'], true)
                : ($jsonMsg['data'] ?? []);

            \Log::info($data);

            match ($data['type'] ?? null) {
                TwilioMessageType::SETUP->value => (
                   function () use ($jsonMsg) {

                       $callStatus = (new Stringable($jsonMsg['status']))
                           ->lower()
                           ->replace('_', ' ')
                           ->ucwords();

                       $call = Call::create([
                           'twilio_call_sid' => $jsonMsg['callSid'],
                           'start_time' => \Illuminate\Support\now(),
                           'status' => CallStatus::tryFrom($callStatus) ?? CallStatus::INITIATED,
                       ]);

                       CallStarted::dispatch($call);
                   }
                )(),
                TwilioMessageType::PROMPT->value => (
                   function() use ($data, $jsonMsg) {
                       $callSid = $jsonMsg['callSid'] ?? null;
                       $voicePrompt = $data['voicePrompt'] ?? null;

                       if (! $callSid || ! $voicePrompt) {
                           \Log::warning('Missing call SID or voice prompt for Twilio prompt message.', [
                               'message' => $jsonMsg,
                           ]);

                           return;
                       }

                       $call = Call::firstOrCreate([
                           'twilio_call_sid' => $callSid,
                       ], [
                           'start_time' => \Illuminate\Support\now(),
                           'status' => CallStatus::IN_PROGRESS
                       ])->first();

                       $callMessage = $call->callMessages()->create([
                           'role' => CallRoles::CUSTOMER,
                           'content' => $voicePrompt,
                       ]);

                       NewCallMessage::dispatch($call, $callMessage, 'inbound');

                       $voiceLang = match ($jsonMsg['lang']) {
                           LanguageCode::English->value => LanguageCode::English->value,
                           LanguageCode::Spanish->value => LanguageCode::Spanish->value,
                           default => LanguageCode::English->value,
                       };

                       $fullResponse = '';

                       try {
                           (new AiCallAgent)
                               ->stream($voicePrompt)
                               ->each(function ($event) use ($callSid, &$fullResponse, $voiceLang) {
                                   if (! $event instanceof TextDelta || $event->delta === '') {
                                       return;
                                   }

                                   $fullResponse .= $event->delta;

                                   $this->publishTextToken(
                                       callSid: $callSid,
                                       token: $event->delta,
                                       lang: $voiceLang,
                                       last: false,
                                   );
                               });

                           $this->publishTextToken(
                               callSid: $callSid,
                               token: '',
                               lang: $voiceLang,
                               last: true,
                           );

                           \Log::info('Published streamed AI response to Twilio.', [
                               'callSid' => $callSid,
                               'response' => $fullResponse,
                           ]);
                       } catch (Throwable $e) {
                           \Log::error('Failed streaming AI response to Twilio.', [
                               'callSid' => $callSid,
                               'error' => $e->getMessage(),
                           ]);
                       }
                   }
                )(),
                TwilioMessageType::INTERRUPT->value => (
                    function() {
                        // TODO:
                    }
                )(),
                TwilioMessageType::ERROR->value => (
                    function() {
                        // TODO:
                    }
                )()
            };
        });
    }

    protected function publishTextToken(
        string $callSid,
        string $token,
        string $lang,
        bool $last = false,
        bool $interruptible = true,
        bool $preemptible = true,
    ): void {
        echo "Publish method reached" . PHP_EOL;
        $receivers = Redis::connection('publish')->publish('twilio:outbound', json_encode([
                'type' => TwilioMessageType::TEXT->value,
                'callSid' => $callSid,
                'data' => [
                    'token' => $token,
                    'last' => $last,
                    'interruptible' => $interruptible,
                    'preemptible' => $preemptible,
                    'lang' => $lang,
                ],
            ]));

        $call = Call::firstOrCreate([
            'twilio_call_sid' => $callSid,
        ], [
            'start_time' => \Illuminate\Support\now(),
            'status' => CallStatus::IN_PROGRESS
        ])->first();

        $callMessage = $call->callMessages()->create([
            'role' => CallRoles::ASSISTANT,
            'content' => $token,
        ]);

        NewCallMessage::dispatch($call, $callMessage, 'outbound');

        \Log::info('Published Twilio outbound token.', [
            'callSid' => $callSid,
            'payload' => [
                'token' => $token,
                'last' => $last,
                'interruptible' => $interruptible,
                'preemptible' => $preemptible,
                'lang' => $lang,
            ],
            'receivers' => $receivers,
        ]);
    }
}
