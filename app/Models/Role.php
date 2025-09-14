<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'key_name',
        'label',
        'workspace_id',
        'is_system_role',
    ];

    protected $casts = [
        'is_system_role' => 'boolean',
    ];

    /**
     * Get the workspace that owns the role.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * The permissions that belong to the role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }

    /**
     * Check if the role has a specific permission.
     */
    public function hasPermission(string $permissionKey): bool
    {
        return $this->permissions()
            ->where('key_name', $permissionKey)
            ->exists();
    }

    /**
     * Check if the role is a system role.
     */
    public function isSystemRole(): bool
    {
        return $this->is_system_role;
    }

    /**
     * Find a role by its key name.
     */
    public static function findByKey(string $keyName, ?int $workspaceId = null): ?self
    {
        return static::where('key_name', $keyName)
            ->where('workspace_id', $workspaceId)
            ->first();
    }
}