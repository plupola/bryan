<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
/*
This models include:

Relationships with requester, document, workspace, and processor
Constants for statuses and request types
Scopes for filtering by status and type
Helper methods for checking status and processing requests
*/



class AccessRequest extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'access_requests';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'requester_id',
        'document_id',
        'workspace_id',
        'request_type',
        'status',
        'justification',
        'processed_at',
        'processed_by',
        'response_note',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'status' => 'string',
        'request_type' => 'string',
    ];

    /**
     * The possible status values.
     *
     * @var array<string>
     */
    public const STATUSES = [
        'pending',
        'approved',
        'rejected',
        'completed'
    ];

    /**
     * The possible request types.
     *
     * @var array<string>
     */
    public const TYPES = [
        'access',
        'deletion',
        'correction'
    ];

    /**
     * Get the user who made the request.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Get the document associated with the request.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the workspace associated with the request.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the user who processed the request.
     */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Scope a query to only include pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include completed requests.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to filter by request type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('request_type', $type);
    }

    /**
     * Check if the request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the request is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the request is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Mark the request as processed.
     */
    public function markAsProcessed(User $processor, string $status, ?string $responseNote = null): void
    {
        $this->update([
            'status' => $status,
            'processed_by' => $processor->id,
            'processed_at' => now(),
            'response_note' => $responseNote,
        ]);
    }
}