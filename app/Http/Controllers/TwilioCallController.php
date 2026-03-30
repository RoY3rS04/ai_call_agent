<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Twilio\TwiML\VoiceResponse;

class TwilioCallController extends Controller
{
    public function __invoke(Request $request)
    {

        $response = new VoiceResponse();
        $connect = $response->connect();

        $conversationRelay = $connect->conversationRelay([
            'url' => 'wss://' . config('services.go_websocket_server.host'),
            // STT
            'transcriptionProvider' => 'Deepgram',
            'transcriptionLanguage' => 'multi',
            'speechModel' => 'nova-3-general',

            // TTS
            'ttsProvider' => 'ElevenLabs',

            'welcomeGreeting' => 'Thank you for calling to Nerdify offices, how may I help you?',
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
}
