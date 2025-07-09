<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'difficulty_id',
        'points',
        'hints',
        'challengeable_id',
        'challengeable_type',
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

    public function difficulty()
    {
        return $this->belongsTo(Difficulty::class);
    }

    public function submissions()
    {
        return $this->hasMany(ChallengeSubmission::class);
    }

    public function challengeable()
    {
        return $this->morphTo();
    }
}
