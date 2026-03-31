<?php

namespace App\Models;

use App\Enums\CallRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CallMessage extends Model
{
    /** @use HasFactory<\Database\Factories\CallMessageFactory> */
    use HasFactory;

    protected $fillable = [
      'role',
      'content'
    ];

    protected $casts = [
        'role' => CallRoles::class,
    ];
}
