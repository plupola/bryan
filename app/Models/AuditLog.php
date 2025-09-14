<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AuditLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'actor_user_id',
        'action',
        'resource_type',
        'resource_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'json',
        'created_at' => 'datetime',
    ];

    /**
     * The resource types that can be audited.
     *
     * @var array
     */
    public static $resourceTypes = [
        'workspace', 'folder', 'document', 'user', 'role', 
        'permission', 'team', 'file_request', 'task'
    ];

    /**
     * Get the workspace that owns the audit log.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the actor (user) that performed the action.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Get the resource that the audit log belongs to.
     */
    public function resource(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'resource_type', 'resource_id');
    }

    /**
     * Scope a query to only include logs for a specific workspace.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $workspaceId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope a query to only include logs for a specific actor.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForActor($query, $userId)
    {
        return $query->where('actor_user_id', $userId);
    }

    /**
     * Scope a query to only include logs for a specific resource.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $resourceType
     * @param  int  $resourceId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForResource($query, $resourceType, $resourceId)
    {
        return $query->where('resource_type', $resourceType)
                    ->where('resource_id', $resourceId);
    }

    /**
     * Scope a query to only include logs for a specific action.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $action
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to only include logs within a date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $from
     * @param  string  $to
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Log a new audit entry.
     *
     * @param  array  $attributes
     * @return \App\Models\AuditLog
     */
    public static function log(array $attributes)
    {
        // Ensure required fields are present
        $required = ['workspace_id', 'action', 'actor_user_id'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $attributes)) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Set default values
        $attributes = array_merge([
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => [],
        ], $attributes);

        return static::create($attributes);
    }

    /**
     * Get the human-readable action name.
     *
     * @return string
     */
    public function getActionNameAttribute()
    {
        $actions = [
            'workspace.created' => 'Workspace Created',
            'workspace.updated' => 'Workspace Updated',
            'workspace.archived' => 'Workspace Archived',
            'workspace.invited_users' => 'Users Invited',
            'workspace.settings_updated' => 'Settings Updated',
            'workspace.external_settings_updated' => 'External Settings Updated',
            'document.uploaded' => 'Document Uploaded',
            'document.updated' => 'Document Updated',
            'document.deleted' => 'Document Deleted',
            'folder.created' => 'Folder Created',
            'folder.updated' => 'Folder Updated',
            'folder.deleted' => 'Folder Deleted',
            'user.invited' => 'User Invited',
            'user.removed' => 'User Removed',
            'permission.granted' => 'Permission Granted',
            'permission.revoked' => 'Permission Revoked',
        ];

        return $actions[$this->action] ?? ucfirst(str_replace('.', ' ', $this->action));
    }

    /**
     * Get the IP address in human-readable format.
     *
     * @return string|null
     */
    public function getIpAddressReadableAttribute()
    {
        if (!$this->ip_address) {
            return null;
        }

        // Check if it's IPv6 or IPv4
        if (strlen($this->ip_address) === 16) {
            return inet_ntop($this->ip_address);
        }

        return $this->ip_address;
    }
}