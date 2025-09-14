<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
This models include:

Relationships with document version, signer, and revoker
Constants for signature statuses
Scopes for filtering by status and revocation
Methods for signing, revoking, and generating audit trails
Accessors for signer information
*/


class Signature extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'signatures';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'document_version_id',
        'signer_user_id',
        'signer_name',
        'signer_email',
        'status',
        'signed_at',
        'evidence_json',
        'ip_address',
        'audit_trail_hash',
        'is_revoked',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'evidence_json' => 'array',
        'signed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'is_revoked' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * The possible status values.
     *
     * @var array<string>
     */
    public const STATUSES = [
        'pending',
        'signed',
        'declined',
        'expired'
    ];

    /**
     * Get the document version associated with the signature.
     */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    /**
     * Get the user who signed (if internal user).
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signer_user_id');
    }

    /**
     * Get the user who revoked the signature.
     */
    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    /**
     * Scope a query to only include pending signatures.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include signed signatures.
     */
    public function scopeSigned($query)
    {
        return $query->where('status', 'signed');
    }

    /**
     * Scope a query to only include revoked signatures.
     */
    public function scopeRevoked($query)
    {
        return $query->where('is_revoked', true);
    }

    /**
     * Check if the signature is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the signature is signed.
     */
    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }

    /**
     * Check if the signature is revoked.
     */
    public function isRevoked(): bool
    {
        return $this->is_revoked;
    }

    /**
     * Mark the signature as signed.
     */
    public function markAsSigned(array $evidence, ?string $ipAddress = null): void
    {
        $this->update([
            'status' => 'signed',
            'signed_at' => now(),
            'evidence_json' => $evidence,
            'ip_address' => $ipAddress,
            'audit_trail_hash' => $this->generateAuditTrailHash(),
        ]);
    }

    /**
     * Revoke the signature.
     */
    public function revoke(User $revoker, string $reason): void
    {
        $this->update([
            'is_revoked' => true,
            'revoked_at' => now(),
            'revoked_by' => $revoker->id,
            'revoke_reason' => $reason,
        ]);
    }

    /**
     * Generate an audit trail hash for the signature.
     */
    protected function generateAuditTrailHash(): string
    {
        return hash('sha256', implode('|', [
            $this->document_version_id,
            $this->signer_user_id ?? $this->signer_email,
            now()->toIso8601String(),
            random_bytes(16)
        ]));
    }

    /**
     * Get the signer's full name (either from user record or direct input).
     */
    public function getSignerFullNameAttribute(): string
    {
        if ($this->signer_user_id && $this->signer) {
            return $this->signer->full_name;
        }

        return $this->signer_name ?? 'Unknown Signer';
    }

    /**
     * Get the signer's email address.
     */
    public function getSignerEmailAttribute(): string
    {
        if ($this->signer_user_id && $this->signer) {
            return $this->signer->email;
        }

        return $this->attributes['signer_email'] ?? '';
    }
}