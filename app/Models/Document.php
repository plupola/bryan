<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo, HasMany, BelongsToMany
};
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Document extends Model
{
    use HasFactory;

    protected $table = 'documents';
    protected $primaryKey = 'id';

    protected $fillable = [
        'uuid_bin', 'original_name', 'folder_id', 'workspace_id', 
        'uploaded_by', 'size_bytes', 'mime_type', 'latest_version',
        'checksum', 'confidentiality', 'is_locked', 'locked_by',
        'locked_at', 'expires_at', 'description', 'password_hash',
        'is_remote_wiped', 'is_deleted', 'deleted_at'
    ];

    protected $casts = [
        'uuid_bin' => 'binary',
        'checksum' => 'binary',
        'is_locked' => 'boolean',
        'is_remote_wiped' => 'boolean',
        'is_deleted' => 'boolean',
        'expires_at' => 'datetime',
        'locked_at' => 'datetime',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'resource_id')
            ->where('resource_type', 'document');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'resource_id')
            ->where('resource_type', 'document');
    }

    public function sharedLinks(): HasMany
    {
        return $this->hasMany(SharedLink::class, 'resource_id')
            ->where('resource_type', 'document');
    }

    public function retention(): HasOne
    {
        return $this->hasOne(DocumentRetention::class);
    }

    public function legalHoldItems(): HasMany
    {
        return $this->hasMany(LegalHoldItem::class, 'resource_id')
            ->where('resource_type', 'document');
    }

    // Scopes
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeInWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    public function scopeInFolder($query, $folderId)
    {
        return $query->where('folder_id', $folderId);
    }

    public function scopeLocked($query)
    {
        return $query->where('is_locked', true);
    }

    public function scopeExpiringSoon($query, $days = 7)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays($days));
    }

    // Accessors
    public function getUuidAttribute(): string
    {
        return $this->convertBinaryUuidToString($this->uuid_bin);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    // Helper methods
    protected function convertBinaryUuidToString($binaryUuid): string
    {
        if (!$binaryUuid) return '';
        
        $hex = bin2hex($binaryUuid);
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 8, 8),
            substr($hex, 4, 4),
            substr($hex, 0, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 12)
        );
    }
}