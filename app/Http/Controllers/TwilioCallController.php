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
            'ttsProvider' => 'ElevenLabs',
            'url' => 'wss://' . config('services.go_websocket_server.host'),
            'voice' => config('services.eleven-labs.voice_id'),
            'welcomeGreeting' => 'Thank you for calling to Nerdify offices, how may I help you?',
            'interruptible' => true
        ]);

        return response($response)->header('Content-Type', 'text/xml');
    }
}
