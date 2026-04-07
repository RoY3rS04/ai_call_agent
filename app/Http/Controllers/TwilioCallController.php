<?php

namespace App\Http\Controllers;

use App\Enums\CallStatus;
use App\Events\CallStatusUpdated;
use App\Models\Call;
use Illuminate\Http\Request;
use Twilio\TwiML\VoiceResponse;

class TwilioCallController extends Controller
{
    public function incoming(Request $request)
    {

        $response = new VoiceResponse();
        $connect = $response->connect();

        $connect->setAction(config('app.url') . '/webhooks/twilio/call-status');
        $connect->setMethod('POST');

        $conversationRelay = $connect->conversationRelay([
            'url' => 'wss://' . config('services.go_websocket_server.host'),
            // STT
            'transcriptionProvider' => 'Deepgram',
            'transcriptionLanguage' => 'multi',
            'speechModel' => 'nova-3-general',

            // TTS
            'ttsProvider' => 'ElevenLabs',

            'welcomeGreeting' => 'Thank you for calling Nerdify. I’m here to help schedule your meeting. To get started, what time zone are you in?',
            'interruptible' => true
        ]);

        $conversationRelay->language([
            'code' => 'en-US',
            'voice' => config('services.eleven-labs.voices.en')
        ]);

        $conversationRelay->language([
            'code' => 'es-MX',
            'voice' => config('services.eleven-labs.voices.es')
        ]);

        return response($response)->header('Content-Type', 'text/xml');
    }

    public function callStatus(Request $request) {

        $validated = $request->validate([
           'callSid' => 'required',
           'callStatus' => 'required',
        ]);

        $call = Call::where('twilio_call_sid', $validated['callSid'])
            ->first();

        $call->update([
            'status' => CallStatus::tryFrom(ucwords(strtolower($validated['callStatus']))) ?? CallStatus::IN_PROGRESS,
            'duration' => $request->callDuration ?? null,
        ]);

        CallStatusUpdated::dispatch($call);
    }
}
