<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use HasFactory;

    protected $table = 'document_versions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'uuid_bin', 'document_id', 'version_no', 'storage_key',
        'original_name', 'mime_type', 'extension', 'size_bytes',
        'sha256', 'uploaded_by', 'change_note'
    ];

    protected $casts = [
        'uuid_bin' => 'binary',
        'sha256' => 'binary',
        'uploaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'document_version_id');
    }

    public function previews(): HasMany
    {
        return $this->hasMany(DocumentPreview::class);
    }

    // Accessors
    public function getUuidAttribute(): string
    {
        $hex = bin2hex($this->uuid_bin);
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 8, 8),
            substr($hex, 4, 4),
            substr($hex, 0, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 12)
        );
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('files.download.version', ['versionId' => $this->id]);
    }

    // Scopes
    public function scopeLatest($query)
    {
        return $query->orderBy('version_no', 'desc');
    }
}