<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo, MorphTo
};

class Comment extends Model
{
    use HasFactory;

    protected $table = 'comments';
    protected $primaryKey = 'id';

    protected $fillable = [
        'workspace_id', 'resource_type', 'resource_id', 'author_id',
        'parent_id', 'body', 'resolved_at', 'resolved_by'
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // Scopes
    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    // Accessors
    public function getIsResolvedAttribute(): bool
    {
        return !is_null($this->resolved_at);
    }
}