<?php

namespace App\Models;

use App\Enums\CallRoles;
use Database\Factories\CallMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallMessage extends Model
{
    /** @use HasFactory<CallMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'role',
        'content',
    ];

    protected $casts = [
        'role' => CallRoles::class,
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
