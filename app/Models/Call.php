<?php

namespace App\Models;

use App\Enums\CallStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    /** @use HasFactory<\Database\Factories\CallFactory> */
    use HasFactory;

    protected $fillable = [
        'twilio_call_sid',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
      'status' => CallStatus::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function callMessages(): HasMany
    {
        return $this->hasMany(CallMessage::class);
    }
}
