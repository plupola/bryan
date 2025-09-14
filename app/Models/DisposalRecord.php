<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/*
This models include:

DisposalRecord:
Relationships with workspace, performer, and retention policy
MorphTo relationship for the resource
Scopes for filtering by workspace and resource type
Proper casting for JSON evidence data
*/

class DisposalRecord extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'disposal_records';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'resource_type',
        'resource_id',
        'method',
        'performed_by',
        'retention_policy_id',
        'evidence_json',
        'certificate_key',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'evidence_json' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the workspace that owns the disposal record.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the user who performed the disposal.
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Get the retention policy associated with the disposal.
     */
    public function retentionPolicy(): BelongsTo
    {
        return $this->belongsTo(RetentionPolicy::class);
    }

    /**
     * Scope a query to filter by workspace.
     */
    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope a query to filter by resource type.
     */
    public function scopeResourceType($query, $type)
    {
        return $query->where('resource_type', $type);
    }

    /**
     * Get the resource associated with the disposal record.
     */
    public function resource()
    {
        return $this->morphTo('resource', 'resource_type', 'resource_id');
    }
}