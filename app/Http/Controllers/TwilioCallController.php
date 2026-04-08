<?php

namespace App\Http\Controllers;

use App\Enums\CallStatus;
use App\Events\CallStatusUpdated;
use App\Jobs\ExtractCustomerInfo;
use App\Models\Call;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Twilio\TwiML\VoiceResponse;

class TwilioCallController extends Controller
{
    public function incoming(Request $request): Response
    {
        $response = new VoiceResponse;
        $connect = $response->connect();

        $connect->setAction(config('app.url').'/webhooks/twilio/call-status');
        $connect->setMethod('POST');

        $conversationRelay = $connect->conversationRelay([
            'url' => 'wss://'.config('services.go_websocket_server.host'),
            // STT
            'transcriptionProvider' => 'Deepgram',
            'transcriptionLanguage' => 'multi',
            'speechModel' => 'nova-3-general',

            // TTS
            'ttsProvider' => 'ElevenLabs',

            'welcomeGreeting' => 'Thank you for calling Nerdify. I’m here to help schedule your meeting. To get started, what time zone are you in?',
            'interruptible' => true,
        ]);

        $conversationRelay->language([
            'code' => 'en-US',
            'voice' => config('services.eleven-labs.voices.en'),
        ]);

        $conversationRelay->language([
            'code' => 'es-MX',
            'voice' => config('services.eleven-labs.voices.es'),
        ]);

        return response($response)->header('Content-Type', 'text/xml');
    }

    public function callStatus(Request $request): Response
    {
        $validated = $request->validate([
            'callSid' => 'required',
            'callStatus' => 'required',
        ]);

        $call = Call::where('twilio_call_sid', $validated['callSid'])
            ->first();

        if ($call === null) {
            return response()->noContent();
        }

        $status = $this->normalizeCallStatus($validated['callStatus']);

        $call->update([
            'status' => $status,
            'duration' => $request->callDuration ?? null,
        ]);

        CallStatusUpdated::dispatch($call);
        ExtractCustomerInfo::dispatchIf(
            $status === CallStatus::COMPLETED,
            $call
        );

        return response()->noContent();
    }

    protected function normalizeCallStatus(string $status): CallStatus
    {
        return match (str($status)->lower()->replace(['_', ' '], '-')->value()) {
            'queued', 'initiated' => CallStatus::INITIATED,
            'ringing' => CallStatus::RINGING,
            'in-progress' => CallStatus::IN_PROGRESS,
            'completed' => CallStatus::COMPLETED,
            'no-answer' => CallStatus::NO_ANSWER,
            'busy' => CallStatus::BUSY,
            'failed' => CallStatus::FAILED,
            'canceled', 'cancelled' => CallStatus::CANCELLED,
            default => CallStatus::IN_PROGRESS,
        };
    }
}
