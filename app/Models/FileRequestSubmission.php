<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileRequestSubmission extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'file_request_id',
        'email',
        'name',
        'message',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the file request that owns the submission.
     */
    public function fileRequest(): BelongsTo
    {
        return $this->belongsTo(FileRequest::class);
    }

    /**
     * Get the files for the submission.
     */
    public function files()
    {
        return $this->hasMany(FileRequestFile::class, 'submission_id');
    }
}