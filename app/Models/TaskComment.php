<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaskComment extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'task_comments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'task_id',
        'author_id',
        'body',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the task that owns the comment.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the author of the comment.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Scope a query to only include comments for a specific task.
     */
    public function scopeForTask($query, $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    /**
     * Scope a query to only include comments by a specific user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('author_id', $userId);
    }

    /**
     * Scope a query to only include recent comments.
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Get the elapsed time since creation in human readable format.
     */
    public function getElapsedTime(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Check if the comment was recently created.
     */
    public function isRecent(): bool
    {
        return $this->created_at->gt(now()->subHours(24));
    }

    /**
     * Get a truncated version of the comment body.
     */
    public function getExcerpt($length = 100): string
    {
        if (strlen($this->body) <= $length) {
            return $this->body;
        }

        return substr($this->body, 0, $length) . '...';
    }
}