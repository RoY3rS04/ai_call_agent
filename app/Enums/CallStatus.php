<?php

namespace App\Enums;

enum CallStatus: string
{
    case INITIATED = 'Initiated';
    case RINGING = 'Ringing';
    case IN_PROGRESS = 'In Progress';
    case COMPLETED = 'Completed';
    case NO_ANSWER = 'No Answer';
    case BUSY = 'Busy';
    case FAILED = 'Failed';
    case CANCELLED = 'Cancelled';
}
