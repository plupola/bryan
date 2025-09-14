<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo, HasMany
};

class LegalHold extends Model
{
    use HasFactory;

    protected $table = 'legal_holds';
    protected $primaryKey = 'id';

    protected $fillable = [
        'workspace_id', 'name', 'description', 'case_number',
        'issued_by', 'issued_at', 'released_at', 'released_by',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'issued_at' => 'datetime',
        'released_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LegalHoldItem::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereNull('released_at');
    }

    // Accessors
    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }
}