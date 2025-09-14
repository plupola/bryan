<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo, HasMany, HasManyThrough
};

class Folder extends Model
{
    use HasFactory;

    protected $table = 'folders';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name', 'parent_id', 'workspace_id', 'path',
        'depth', 'created_by', 'is_deleted', 'deleted_at'
    ];

    protected $casts = [
        'depth' => 'integer',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->notDeleted();
    }

    public function allDocuments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Document::class,
            Folder::class,
            'parent_id',
            'folder_id',
            'id',
            'id'
        )->notDeleted();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'resource_id')
            ->where('resource_type', 'folder');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'resource_id')
            ->where('resource_type', 'folder');
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

    // Accessors
    public function getFullPathAttribute(): string
    {
        return $this->path . $this->name . '/';
    }

    public function getDocumentsCountAttribute(): int
    {
        return $this->documents()->count();
    }

    public function getSubfoldersCountAttribute(): int
    {
        return $this->children()->notDeleted()->count();
    }
}