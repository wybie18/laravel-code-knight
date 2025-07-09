<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'type_id',
        'exp_reward',
        'requirements',
    ];

    /**
     * Get the route key for the model.
     * This tells Laravel to use the 'slug' column for route model binding.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    protected $casts = ['requirements' => 'array'];

    public function type()
    {
        return $this->belongsTo(AchievementType::class, 'type_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_achievements')->withTimestamps();
    }
}
