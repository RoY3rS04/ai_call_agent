<?php

namespace App\Console\Commands\ConversationRelay;

use App\Ai\Agents\AiCallAgent;
use App\Enums\TwilioMessageType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
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
                   function () {

                   }
                )(), //TODO: START THE Customer json blob
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

                       echo 'prompt: ' . $voicePrompt . PHP_EOL;

                       $fullResponse = '';

                       try {
                           (new AiCallAgent)
                               ->stream($voicePrompt)
                               ->each(function ($event) use ($callSid, &$fullResponse) {
                                   if (! $event instanceof TextDelta || $event->delta === '') {
                                       return;
                                   }

                                   $fullResponse .= $event->delta;

                                   $this->publishTextToken(
                                       callSid: $callSid,
                                       token: $event->delta,
                                       last: false,
                                   );
                               });

                           $this->publishTextToken(
                               callSid: $callSid,
                               token: '',
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
                ],
            ]));

        \Log::info('Published Twilio outbound token.', [
            'callSid' => $callSid,
            'payload' => [
                'token' => $token,
                'last' => $last,
                'interruptible' => $interruptible,
                'preemptible' => $preemptible,
            ],
            'receivers' => $receivers,
        ]);
    }
}
