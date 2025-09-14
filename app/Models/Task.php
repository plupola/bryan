<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'due_at',
        'priority',
        'status',
        'created_by',
        'assigned_to',
        'document_id',
        'workspace_id',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array<int, string>
     */
    protected $dates = [
        'due_at',
        'completed_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Priority values.
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';

    /**
     * Status values.
     */
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the workspace that owns the task.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the user who created the task.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user assigned to the task.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get all users assigned to the task (many-to-many relationship).
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_assignees', 'task_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Get the document associated with the task.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the comments for the task.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at', 'desc');
    }

    /**
     * Scope a query to only include open tasks.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    /**
     * Scope a query to only include in progress tasks.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope a query to only include completed tasks.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope a query to only include cancelled tasks.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope a query to only include overdue tasks.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_at', '<', now())
            ->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    /**
     * Scope a query to only include tasks due today.
     */
    public function scopeDueToday($query)
    {
        return $query->whereDate('due_at', today())
            ->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    /**
     * Scope a query to only include tasks for a specific workspace.
     */
    public function scopeForWorkspace($query, $workspaceId)
    {
        return $query->where('workspace_id', $workspaceId);
    }

    /**
     * Scope a query to only include tasks assigned to a specific user.
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Check if the task is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && 
               !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    /**
     * Check if the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the task is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if the task is open.
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if the task is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Get the priority badge class for UI.
     */
    public function getPriorityBadgeClass(): string
    {
        return match($this->priority) {
            self::PRIORITY_HIGH => 'badge-danger',
            self::PRIORITY_MEDIUM => 'badge-warning',
            self::PRIORITY_LOW => 'badge-info',
            default => 'badge-secondary',
        };
    }

    /**
     * Get the status badge class for UI.
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_COMPLETED => 'badge-success',
            self::STATUS_IN_PROGRESS => 'badge-primary',
            self::STATUS_OPEN => 'badge-secondary',
            self::STATUS_CANCELLED => 'badge-danger',
            default => 'badge-secondary',
        };
    }

    /**
     * Get the elapsed time since creation in human readable format.
     */
    public function getElapsedTime(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get the time remaining until due date.
     */
    public function getTimeRemaining(): ?string
    {
        if (!$this->due_at || $this->isCompleted() || $this->isCancelled()) {
            return null;
        }

        return $this->due_at->isPast() 
            ? 'Overdue by ' . $this->due_at->diffForHumans(null, true) 
            : 'Due in ' . $this->due_at->diffForHumans(null, true);
    }
}