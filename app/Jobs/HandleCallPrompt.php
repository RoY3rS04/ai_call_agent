<?php

namespace App\Jobs;

use App\Ai\Agents\AiCallAgent;
use App\Enums\CallRoles;
use App\Enums\CallStatus;
use App\Enums\TwilioMessageType;
use App\Events\NewCallMessage;
use App\Models\Call;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Streaming\Events\TextDelta;
use Throwable;

class HandleCallPrompt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(
        public int $callId,
        public string $voicePrompt,
        public string $voiceLang,
    ) {}

    public function handle(): void
    {
        $call = Call::query()->find($this->callId);

        if (! $call) {
            Log::warning('Cannot process AI prompt because the call record no longer exists.', [
                'call_id' => $this->callId,
            ]);

            return;
        }

        $callSid = $call->twilio_call_sid;
        $lock = Cache::store('redis')->lock("call:{$callSid}:ai", 120);

        if (! $lock->get()) {
            $this->release(2);

            return;
        }

        $fullResponse = '';

        try {
            $agent = $call->agent_conversation_id
                ? (new AiCallAgent)->continue($call->agent_conversation_id, as: $call)
                : (new AiCallAgent)->forUser($call);

            $agent->stream($this->voicePrompt)
                ->each(function ($event) use ($call, &$fullResponse) {
                    if (! $event instanceof TextDelta || $event->delta === '') {
                        return;
                    }

                    $fullResponse .= $event->delta;

                    $this->publishTextToken(
                        call: $call,
                        token: $event->delta,
                        lang: $this->voiceLang,
                        last: false,
                    );
                })
                ->then(function (StreamedAgentResponse $response) use ($call, $callSid, $fullResponse) {
                    $call->update([
                        'agent_conversation_id' => $response->conversationId,
                        'status' => CallStatus::IN_PROGRESS,
                    ]);

                    $this->publishTextToken(
                        call: $call,
                        token: '',
                        lang: $this->voiceLang,
                        last: true,
                    );

                    Log::info('Published streamed AI response to Twilio.', [
                        'callSid' => $callSid,
                        'response' => $fullResponse,
                        'conversation_id' => $response->conversationId,
                        'tool_calls' => $response->toolCalls->map->toArray()->all(),
                    ]);
                });
        } catch (Throwable $exception) {
            Log::error('Failed streaming AI response to Twilio.', [
                'call_id' => $call->getKey(),
                'callSid' => $callSid,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            optional($lock)->release();
        }
    }

    protected function publishTextToken(
        Call $call,
        string $token,
        string $lang,
        bool $last = false,
        bool $interruptible = true,
        bool $preemptible = true,
    ): void {
        $receivers = Redis::connection('publish')->publish('twilio:outbound', json_encode([
            'type' => TwilioMessageType::TEXT->value,
            'callSid' => $call->twilio_call_sid,
            'data' => [
                'token' => $token,
                'last' => $last,
                'interruptible' => $interruptible,
                'preemptible' => $preemptible,
                'lang' => $lang,
            ],
        ]));

        $callMessage = $call->callMessages()->create([
            'role' => CallRoles::ASSISTANT,
            'content' => $token,
        ]);

        NewCallMessage::dispatch($call, $callMessage, 'outbound');

        Log::info('Published Twilio outbound token.', [
            'callSid' => $call->twilio_call_sid,
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
