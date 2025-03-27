<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
    ];

    /**
     * Get the projects that use this language.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_languages')
            ->withPivot('fallback_language_id')
            ->withTimestamps();
    }

    /**
     * Get the translations using this language.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class);
    }
}
