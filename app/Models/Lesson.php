<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    protected $fillable = [
        'module_id',
        'slug',
        'title',
        'content',
        'video_url',
        'exp_reward',
        'estimated_duration',
        'order',
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

    public function module()
    {
        return $this->belongsTo(CourseModule::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function userProgress()
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    public function prerequisites()
    {
        return $this->belongsToMany(Lesson::class, 'lesson_prerequisites', 'lesson_id', 'prerequisite_lesson_id');
    }

    public function dependentLessons()
    {
        return $this->belongsToMany(Lesson::class, 'lesson_prerequisites', 'prerequisite_lesson_id', 'lesson_id');
    }
}
