<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\{
    HasMany, BelongsTo, BelongsToMany
};

class User extends Authenticatable
{
    use Notifiable, HasFactory;

    protected $table = 'users';
    protected $primaryKey = 'id';

    protected $fillable = [
        'company_id', 'email', 'password_hash', 'first_name',
        'last_name', 'avatar_url', 'locale', 'time_zone',
        'is_active', 'last_login_at'
    ];

    protected $hidden = [
        'password_hash'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->using(WorkspaceMember::class)
            ->withPivot('role_id', 'status', 'joined_at')
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'author_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getInitialsAttribute(): string
    {
        $initials = '';
        if ($this->first_name) $initials .= substr($this->first_name, 0, 1);
        if ($this->last_name) $initials .= substr($this->last_name, 0, 1);
        return $initials ?: substr($this->email, 0, 1);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Authentication
    public function getAuthPassword()
    {
        return $this->password_hash;
    }
    // In App\Models\User.php

/**
 * Get the teams that the user belongs to.
 */
public function teams(): BelongsToMany
{
    return $this->belongsToMany(Team::class, 'team_members')
                ->withPivot('role')
                ->withTimestamps();
}

/**
 * Get the team memberships.
 */
public function teamMemberships(): HasMany
{
    return $this->hasMany(TeamMember::class);
}
}