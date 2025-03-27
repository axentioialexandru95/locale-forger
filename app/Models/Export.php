<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Export extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'user_id',
        'format',
        'file_path',
        'file_name',
        'status',
        'error_message',
    ];

    /**
     * Get the project that owns the export.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user that created the export.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
