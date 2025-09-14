<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkspaceMember extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'workspace_members';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'user_id',
        'role_id',
        'status',
        'joined_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'joined_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the workspace that owns the workspace member.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * Get the user that owns the workspace member.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the role that owns the workspace member.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Scope a query to only include active members.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include invited members.
     */
    public function scopeInvited($query)
    {
        return $query->where('status', 'invited');
    }

    /**
     * Scope a query to only include suspended members.
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    /**
     * Check if the member is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the member is invited.
     */
    public function isInvited(): bool
    {
        return $this->status === 'invited';
    }

    /**
     * Check if the member is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Activate the member.
     */
    public function activate(): bool
    {
        return $this->update(['status' => 'active']);
    }

    /**
     * Suspend the member.
     */
    public function suspend(): bool
    {
        return $this->update(['status' => 'suspended']);
    }

    /**
     * Get the full name of the member.
     */
    public function getFullNameAttribute(): string
    {
        return $this->user->first_name . ' ' . $this->user->last_name;
    }

    /**
     * Get the email of the member.
     */
    public function getEmailAttribute(): string
    {
        return $this->user->email;
    }

    /**
     * Find workspace member by user and workspace.
     */
    public static function findByUserAndWorkspace(int $userId, int $workspaceId): ?self
    {
        return static::where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->first();
    }

    /**
     * Check if a user is a member of a workspace.
     */
    public static function isMember(int $userId, int $workspaceId): bool
    {
        return static::where('user_id', $userId)
            ->where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Get all workspaces for a user.
     */
    public static function getWorkspacesForUser(int $userId)
    {
        return static::with('workspace')
            ->where('user_id', $userId)
            ->active()
            ->get()
            ->pluck('workspace');
    }
}