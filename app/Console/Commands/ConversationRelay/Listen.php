<?php

namespace App\Console\Commands\ConversationRelay;

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
            $message = json_decode($message, true);

            match ($message['type']) {
                TwilioMessageType::SETUP->value => null, //TODO: START THE Customer json blob
                TwilioMessageType::PROMPT->value => (
                   function() {
                       // TODO: SEND TO AI
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
