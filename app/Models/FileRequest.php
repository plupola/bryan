<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileRequest extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'folder_id',
        'created_by',
        'title',
        'instructions',
        'token',
        'opens_at',
        'closes_at',
        'require_email',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'require_email' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the workspace that owns the file request.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the folder that owns the file request.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /**
     * Get the user who created the file request.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the submissions for the file request.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FileRequestSubmission::class);
    }

    /**
     * Scope a query to only include active file requests.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('opens_at')
                ->orWhere('opens_at', '<=', now());
        })->where(function ($query) {
            $query->whereNull('closes_at')
                ->orWhere('closes_at', '>', now());
        });
    }

    /**
     * Scope a query to only include expired file requests.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('closes_at')
            ->where('closes_at', '<=', now());
    }

    /**
     * Scope a query to only include upcoming file requests.
     */
    public function scopeUpcoming($query)
    {
        return $query->whereNotNull('opens_at')
            ->where('opens_at', '>', now());
    }

    /**
     * Check if the file request is currently active.
     */
    public function isActive(): bool
    {
        $now = now();
        
        $opensCondition = $this->opens_at ? $this->opens_at <= $now : true;
        $closesCondition = $this->closes_at ? $this->closes_at > $now : true;
        
        return $opensCondition && $closesCondition;
    }

    /**
     * Check if the file request has expired.
     */
    public function isExpired(): bool
    {
        return $this->closes_at && $this->closes_at <= now();
    }

    /**
     * Check if the file request is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->opens_at && $this->opens_at > now();
    }

    /**
     * Get the submission count for this file request.
     */
    public function getSubmissionCountAttribute(): int
    {
        return $this->submissions()->count();
    }

    /**
     * Get the URL for the file request upload page.
     */
    public function getUploadUrlAttribute(): string
    {
        return route('file-requests.upload', ['requestId' => $this->token]);
    }

    /**
     * Get the folder path for storing uploaded files.
     */
    public function getStoragePathAttribute(): string
    {
        return "file-requests/{$this->id}";
    }

    /**
     * Find a file request by token.
     */
    public static function findByToken(string $token): ?self
    {
        return static::where('token', $token)->first();
    }
}