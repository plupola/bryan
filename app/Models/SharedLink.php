<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedLink extends Model
{
    use HasFactory;

    protected $table = 'shared_links';
    protected $primaryKey = 'id';

    protected $fillable = [
        'workspace_id', 'resource_type', 'resource_id', 'token',
        'expires_at', 'max_downloads', 'download_count', 'password_hash',
        'created_by'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'max_downloads' => 'integer',
        'download_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    public function downloadHistory(): HasMany
    {
        return $this->hasMany(DownloadHistory::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where(function($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })->where(function($q) {
            $q->whereNull('max_downloads')
              ->orWhereRaw('download_count < max_downloads');
        });
    }

    public function scopeExpired($query)
    {
        return $query->where(function($q) {
            $q->whereNotNull('expires_at')
              ->where('expires_at', '<=', now());
        })->orWhere(function($q) {
            $q->whereNotNull('max_downloads')
              ->whereRaw('download_count >= max_downloads');
        });
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return (!$this->expires_at || $this->expires_at->isFuture()) &&
               (!$this->max_downloads || $this->download_count < $this->max_downloads);
    }

    public function getIsExpiredAttribute(): bool
    {
        return !$this->is_active;
    }

    public function getShareUrlAttribute(): string
    {
        return route('shared.show', $this->token);
    }
}