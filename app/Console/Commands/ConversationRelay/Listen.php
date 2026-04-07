<?php

namespace App\Console\Commands\ConversationRelay;

use App\Jobs\HandleCallPrompt;
use App\Enums\CallRoles;
use App\Enums\CallStatus;
use App\Enums\LanguageCode;
use App\Enums\TwilioMessageType;
use App\Events\CallStarted;
use App\Events\NewCallMessage;
use App\Models\Call;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Stringable;

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
                       ]);

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

                       Redis::connection('publish')->publish('twilio:outbound', json_encode([
                           'type' => TwilioMessageType::LANGUAGE->value,
                           'callSid' => $callSid,
                           'data' => [
                               'ttsLanguage' => $voiceLang,
                               'TranscriptionLanguage' => $voiceLang,
                           ]
                       ]));

                       HandleCallPrompt::dispatch(
                           callId: $call->getKey(),
                           voicePrompt: $voicePrompt,
                           voiceLang: $voiceLang,
                       );

                       \Log::info('Queued AI call prompt for processing.', [
                           'call_id' => $call->getKey(),
                           'callSid' => $callSid,
                       ]);
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

}
