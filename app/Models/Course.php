<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'short_description',
        'objectives',
        'thumbnail',
        'difficulty_id',
        'category_id',
        'exp_reward',
        'estimated_duration',
        'is_published',
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

    public function category()
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function skillTags()
    {
        return $this->belongsToMany(SkillTag::class, 'course_skill_tag');
    }

    public function userProgress()
    {
        return $this->hasMany(UserCourseProgress::class);
    }
}
