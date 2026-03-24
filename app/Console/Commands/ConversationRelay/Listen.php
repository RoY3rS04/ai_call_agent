<?php

namespace App\Console\Commands\ConversationRelay;

use App\Ai\Agents\AiCallAgent;
use App\Enums\TwilioMessageType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

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
        Redis::connection('pub-sub')->subscribe(['twilio:inbound'], function ($message) {
            $jsonMsg = json_decode($message, true);
            $data = json_decode($jsonMsg['data'], true);

            \Log::info($data);
            echo $message . PHP_EOL;

            match ($jsonMsg['data']['type']) {
                TwilioMessageType::SETUP->value => (
                   function () {

                   }
                )(), //TODO: START THE Customer json blob
                TwilioMessageType::PROMPT->value => (
                   function() use ($data, $jsonMsg) {

                       echo 'prompt: ' . $data['voicePrompt'] . PHP_EOL;

                       $resp = (new AiCallAgent)
                           ->prompt($data['voicePrompt']);

                       \Log::info($resp);

                       Redis::connection('pub-sub')
                           ->publish('twilio:outbound', json_encode([
                               'callSid' => $jsonMsg['callSid'],
                               'data' => [
                                   'type' => 'text',
                                   'text' => $resp['response'],
                                   'last' => false,
                                   'interruptible' => false,
                                   'preemptible' => false,
                               ]
                           ]));
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
