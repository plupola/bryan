<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workspace extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'owner_user_id',
        'storage_quota',
        'storage_used',
        'is_archived',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'storage_quota' => 'integer',
        'storage_used' => 'integer',
    ];

    /**
     * Get the owner of the workspace.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Get the members of the workspace.
     */
    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class, 'workspace_id');
    }

    /**
     * Get the active members of the workspace.
     */
    public function activeMembers(): HasMany
    {
        return $this->members()->active();
    }

    /**
     * Check if the workspace has a specific user as a member.
     */
    public function hasMember(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Add a member to the workspace.
     */
    public function addMember(User $user, Role $role, string $status = 'active'): WorkspaceMember
    {
        return WorkspaceMember::create([
            'workspace_id' => $this->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => $status,
            'joined_at' => now(),
        ]);
    }

    /**
     * Remove a member from the workspace.
     */
    public function removeMember(User $user): bool
    {
        return $this->members()
            ->where('user_id', $user->id)
            ->delete();
    }
}