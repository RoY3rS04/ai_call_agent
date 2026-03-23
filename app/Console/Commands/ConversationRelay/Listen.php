<?php

namespace App\Console\Commands\ConversationRelay;

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
            // TODO: AI AGENT WILL RESPOND
            echo $message . "\n";
        });
    }
}
