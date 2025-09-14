<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileRequestFile extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'submission_id',
        'original_name',
        'storage_path',
        'file_name',
        'mime_type',
        'size',
        'checksum',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the submission that owns the file.
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(FileRequestSubmission::class);
    }

    /**
     * Get the file size in human readable format.
     */
    public function getSizeFormattedAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->size;
        $factor = floor((strlen($bytes) - 1) / 3);
        
        if ($factor > 0) {
            $bytes = $bytes / pow(1024, $factor);
        }
        
        return sprintf('%.2f %s', $bytes, $units[$factor]);
    }
}