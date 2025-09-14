<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'permissions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'key_name',
        'label',
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
    ];

    /**
     * The roles that belong to the permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    /**
     * The ACLs that use this permission.
     */
    public function acls()
    {
        return $this->hasMany(Acl::class);
    }

    /**
     * Scope a query to only include permissions with a specific key.
     */
    public function scopeByKey($query, $keyName)
    {
        return $query->where('key_name', $keyName);
    }

    /**
     * Scope a query to search permissions by key or label.
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('key_name', 'like', "%{$searchTerm}%")
                    ->orWhere('label', 'like', "%{$searchTerm}%");
    }

    /**
     * Check if this permission is assigned to a specific role.
     */
    public function isAssignedToRole($roleId): bool
    {
        return $this->roles()->where('role_id', $roleId)->exists();
    }

    /**
     * Get the permission category (based on key_name prefix).
     */
    public function getCategoryAttribute(): string
    {
        $parts = explode('.', $this->key_name);
        return $parts[0] ?? 'general';
    }

    /**
     * Get the permission action (based on key_name suffix).
     */
    public function getActionAttribute(): string
    {
        $parts = explode('.', $this->key_name);
        return $parts[1] ?? $this->key_name;
    }
}