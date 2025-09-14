<?php
// app/Models/WorkflowStep.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStep extends Model
{
    protected $fillable = [
        'workflow_id', 'name', 'type', 'step_order', 'assigned_to_type',
        'assigned_to_id', 'assigned_role_id', 'time_limit_days',
        'notify_assignee', 'is_required', 'created_by'
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'assigned_role_id');
    }
}

// app/Models/WorkflowTemplate.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowTemplate extends Model
{
    protected $fillable = [
        'name', 'description', 'category', 'is_public', 'created_by'
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowTemplateStep::class)->orderBy('step_order');
    }
}

// app/Models/WorkflowInstance.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInstance extends Model
{
    protected $fillable = [
        'workflow_id', 'document_id', 'status', 'started_at', 'completed_at'
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function currentStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'current_step_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(WorkflowActivity::class);
    }
}
