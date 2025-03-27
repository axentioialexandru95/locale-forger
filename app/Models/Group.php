<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'name',
        'description',
        'slug',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];
    
    /**
     * Get the translation keys that belong to this group
     */
    public function translationKeys()
    {
        return $this->hasMany(TranslationKey::class, 'group', 'name');
    }
    
    /**
     * Get the route key for the model
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }
    
    /**
     * Generate a slug from the name
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($group) {
            if (!$group->slug) {
                $group->slug = \Illuminate\Support\Str::slug($group->name);
            }
        });
    }
}
