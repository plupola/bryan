<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'payload',
        'last_activity',
        'device_type',
        'browser',
        'platform',
        'country',
        'city',
        'login_time',
        'logout_time',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_activity' => 'datetime',
        'login_time' => 'datetime',
        'logout_time' => 'datetime',
        'is_active' => 'boolean',
        'payload' => 'array',
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_sessions';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Get the user that owns the session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include active sessions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include sessions from a specific country.
     */
    public function scopeFromCountry($query, $country)
    {
        return $query->where('country', $country);
    }

    /**
     * Scope a query to only include sessions from a specific device type.
     */
    public function scopeDeviceType($query, $deviceType)
    {
        return $query->where('device_type', $deviceType);
    }

    /**
     * Get the session duration in minutes.
     *
     * @return int|null
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->login_time) {
            return null;
        }

        $endTime = $this->logout_time ?? now();
        return $endTime->diffInMinutes($this->login_time);
    }

    /**
     * Check if the session is expired based on a given lifetime.
     *
     * @param int $lifetime Session lifetime in minutes
     * @return bool
     */
    public function isExpired(int $lifetime = 10): bool
    {
        return $this->last_activity->diffInMinutes(now()) > $lifetime;
    }

    /**
     * Mark session as inactive and set logout time.
     *
     * @return bool
     */
    public function markAsInactive(): bool
    {
        return $this->update([
            'is_active' => false,
            'logout_time' => now(),
        ]);
    }

    /**
     * Create a new user session from a login event.
     *
     * @param User $user
     * @param array $sessionData
     * @return static
     */
    public static function createFromLogin(User $user, array $sessionData = []): self
    {
        // Extract device information from user agent
        $agent = app('agent');
        $agent->setUserAgent($sessionData['user_agent'] ?? request()->userAgent());

        return static::create([
            'user_id' => $user->id,
            'ip_address' => $sessionData['ip_address'] ?? request()->ip(),
            'user_agent' => $sessionData['user_agent'] ?? request()->userAgent(),
            'payload' => $sessionData['payload'] ?? [],
            'last_activity' => now(),
            'device_type' => $agent->deviceType(),
            'browser' => $agent->browser(),
            'platform' => $agent->platform(),
            'country' => $sessionData['country'] ?? null,
            'city' => $sessionData['city'] ?? null,
            'login_time' => now(),
            'is_active' => true,
        ]);
    }

    /**
     * Update the last activity timestamp.
     *
     * @return bool
     */
    public function touchLastActivity(): bool
    {
        return $this->update(['last_activity' => now()]);
    }
}