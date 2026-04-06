<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements Authorizable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'google_email',
        'google_access_token',
        'google_refresh_token',
        'google_token_expires_at',
        'google_calendar_id',
        'google_calendar_connected_at'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'google_access_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'google_token_expires_at' => 'datetime',
            'google_calendar_connected_at' => 'datetime',
        ];
    }

    public function getValidGoogleAccessToken(): string
    {
        if ($this->google_token_expires_at?->isFuture() && filled($this->google_access_token)) {
            return $this->google_access_token;
        }

        if (blank($this->google_refresh_token)) {
            throw new \RuntimeException('Google Calendar must be reconnected.');
        }

        $tokenResponse = \Http::asForm()
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->google_refresh_token,
            ])
            ->throw()
            ->json();

        $this->update([
            'google_access_token' => $tokenResponse['access_token'],
            'google_token_expires_at' => now()->addSeconds($tokenResponse['expires_in'] ?? 3600),
        ]);

        return $this->fresh()->google_access_token;
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'marketing_user_id');
    }
}
