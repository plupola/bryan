<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'name',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the workspace that owns the team.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the users that belong to the team.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /**
     * Get the team members.
     */
    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    /**
     * Scope a query to only include teams for a specific workspace.
     */
    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope a query to search teams by name or description.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Get the team's member count.
     */
    public function getMemberCountAttribute(): int
    {
        return $this->members()->count();
    }

    /**
     * Check if a user is a member of this team.
     */
    public function hasUser($userId): bool
    {
        return $this->users()->where('user_id', $userId)->exists();
    }

    /**
     * Add a user to the team.
     */
    public function addUser($userId, $role = 'member'): void
    {
        $this->users()->attach($userId, ['role' => $role]);
    }

    /**
     * Remove a user from the team.
     */
    public function removeUser($userId): void
    {
        $this->users()->detach($userId);
    }

    /**
     * Update user's role in the team.
     */
    public function updateUserRole($userId, $role): void
    {
        $this->users()->updateExistingPivot($userId, ['role' => $role]);
    }

    /**
     * Get all team members with their roles.
     */
    public function getMembersWithRoles()
    {
        return $this->members()->with('user')->get()->map(function ($member) {
            return [
                'user_id' => $member->user_id,
                'name' => $member->user->full_name ?? $member->user->email,
                'email' => $member->user->email,
                'role' => $member->role,
                'joined_at' => $member->created_at,
            ];
        });
    }

    /**
     * Check if the team has any members with admin role.
     */
    public function hasAdminMembers(): bool
    {
        return $this->members()->where('role', 'admin')->exists();
    }

    /**
     * Get the team's admin members.
     */
    public function getAdminMembers()
    {
        return $this->members()->where('role', 'admin')->with('user')->get();
    }

    /**
     * Get the team's regular members (non-admin).
     */
    public function getRegularMembers()
    {
        return $this->members()->where('role', '!=', 'admin')->with('user')->get();
    }
}