<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meeting extends Model
{
    /** @use HasFactory<\Database\Factories\MeetingFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'company_id',
        'call_id',
        'marketing_user_id',
        'start_time',
        'end_time',
        'timezone',
        'google_calendar_event_id',
        'status',
        'confirmed_at',
        'source',
        'notes',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'confirmed_at' => 'datetime',
        'status' => MeetingStatus::class,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function marketingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketing_user_id');
    }
}
