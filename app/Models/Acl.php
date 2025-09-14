<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Acl extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'acls';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'subject_type',
        'subject_id',
        'resource_type',
        'resource_id',
        'permission_id',
        'effect',
        'created_by',
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
     * Get the workspace that owns the ACL.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the permission that owns the ACL.
     */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    /**
     * Get the creator of the ACL.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the subject of the ACL (user or role).
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * Get the resource of the ACL (workspace, folder, or document).
     */
    public function resource()
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include ACLs for a specific workspace.
     */
    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope a query to only include ACLs for a specific subject.
     */
    public function scopeForSubject($query, $subjectType, $subjectId)
    {
        return $query->where('subject_type', $subjectType)
                    ->where('subject_id', $subjectId);
    }

    /**
     * Scope a query to only include ACLs for a specific resource.
     */
    public function scopeForResource($query, $resourceType, $resourceId)
    {
        return $query->where('resource_type', $resourceType)
                    ->where('resource_id', $resourceId);
    }

    /**
     * Scope a query to only include allow effects.
     */
    public function scopeAllow($query)
    {
        return $query->where('effect', 'allow');
    }

    /**
     * Scope a query to only include deny effects.
     */
    public function scopeDeny($query)
    {
        return $query->where('effect', 'deny');
    }
}