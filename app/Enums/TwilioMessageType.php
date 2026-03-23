<?php

namespace App\Enums;

enum TwilioMessageType: string
{
    case SETUP = 'setup';
    case PROMPT = 'prompt';
    case DTMF = 'dtmf';
    case INTERRUPT = 'interrupt';
    case ERROR = 'error';
    case TEXT = 'text';
    case PLAY = 'play';
    case SEND_DIGITS = 'sendDigits';
    case LANGUAGE = 'language';
    case END = 'end';
}
