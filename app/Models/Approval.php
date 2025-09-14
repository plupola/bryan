<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Approval extends Model
{
    use HasFactory;

    protected $table = 'approvals';
    protected $primaryKey = 'id';

    protected $fillable = [
        'document_version_id', 'step_no', 'assigned_to', 'status',
        'decision_note', 'decided_at'
    ];

    protected $casts = [
        'step_no' => 'integer',
        'decided_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Accessors
    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'approved';
    }

    public function getIsRejectedAttribute(): bool
    {
        return $this->status === 'rejected';
    }
}