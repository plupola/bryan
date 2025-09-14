
<?php
// app/Models/WorkflowActivity.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowActivity extends Model
{
    protected $fillable = [
        'workflow_id', 'instance_id', 'user_id', 'action', 'details'
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}