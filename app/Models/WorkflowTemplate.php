<?php
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