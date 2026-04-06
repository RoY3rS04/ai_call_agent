<?php

namespace App\Models;

use App\Enums\LeadSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;

    protected $casts = [
        'lead_source' => LeadSource::class,
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'lead_source',
        'email',
        'phone',
        'company_id',
        'timezone',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}
