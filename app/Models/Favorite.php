<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    use HasFactory;

    protected $table = 'favorites';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id', 'resource_type', 'resource_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDocuments($query)
    {
        return $query->where('resource_type', 'document');
    }

    public function scopeFolders($query)
    {
        return $query->where('resource_type', 'folder');
    }
}