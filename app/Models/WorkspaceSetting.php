<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WorkspaceSetting extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'workspace_settings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'k',
        'v',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'v' => 'json',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the workspace that owns the setting.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Scope a query to only include settings for a specific key.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $key
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForKey($query, $key)
    {
        return $query->where('k', $key);
    }

    /**
     * Get a setting value by key.
     *
     * @param  int  $workspaceId
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public static function getValue($workspaceId, $key, $default = null)
    {
        $setting = static::where('workspace_id', $workspaceId)
            ->where('k', $key)
            ->first();

        return $setting ? $setting->v : $default;
    }

    /**
     * Set a setting value by key.
     *
     * @param  int  $workspaceId
     * @param  string  $key
     * @param  mixed  $value
     * @return \App\Models\WorkspaceSetting
     */
    public static function setValue($workspaceId, $key, $value)
    {
        return static::updateOrCreate(
            ['workspace_id' => $workspaceId, 'k' => $key],
            ['v' => $value]
        );
    }

    /**
     * Remove a setting by key.
     *
     * @param  int  $workspaceId
     * @param  string  $key
     * @return bool
     */
    public static function remove($workspaceId, $key)
    {
        return static::where('workspace_id', $workspaceId)
            ->where('k', $key)
            ->delete() > 0;
    }
}