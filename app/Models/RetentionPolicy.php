<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetentionPolicy extends Model
{
    use HasFactory;

    protected $table = 'retention_policies';
    protected $primaryKey = 'id';

    protected $fillable = [
        'workspace_id', 'name', 'description', 'keep_rule_json',
        'action', 'is_active', 'created_by'
    ];

    protected $casts = [
        'keep_rule_json' => 'array',
        'is_active' => 'boolean',
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

    public function documentRetentions(): HasMany
    {
        return $this->hasMany(DocumentRetention::class);
    }

    // Accessors
    public function getKeepMonthsAttribute(): ?int
    {
        return $this->keep_rule_json['keep_months'] ?? null;
    }

    public function getKeepYearsAttribute(): ?int
    {
        return $this->keep_rule_json['keep_years'] ?? null;
    }
}