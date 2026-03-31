<?php

namespace App\Console\Commands;

use App\Enums\CallRoles;
use App\Enums\CallStatus;
use App\Events\CallStarted;
use App\Events\CallStatusUpdated;
use App\Events\NewCallMessage;
use App\Models\Call;
use Illuminate\Console\Command;

class TestCallMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-call-messages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $faker = fake();
        $call = Call::factory()->create();

        CallStarted::dispatch($call);
        while (true) {

            $message = $call->callMessages()->create([
                'content' => $faker->text,
                'role' => $faker->randomElement(CallRoles::cases())
            ]);

            $call->status = $faker->randomElement(CallStatus::cases());
            $call->save();

            CallStatusUpdated::dispatch($call->fresh());

            NewCallMessage::dispatch(
                $call,
                $message,
                $message->role === CallRoles::CUSTOMER ? 'inbound' : 'outbound'
            );

            sleep(10);
        }
    }
}
