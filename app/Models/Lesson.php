<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Lesson extends Model
{
    protected $fillable = [
        'course_module_id',
        'slug',
        'title',
        'content',
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

    public function course()
    {
        return $this->hasOneThrough(
            Course::class,
            CourseModule::class,
            'id',               // Foreign key on course_modules
            'id',               // Foreign key on courses
            'course_module_id', // Local key on lessons
            'course_id'         // Local key on course_modules
        );
    }

    public function module()
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    public function userProgress()
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    public function currentUserProgress()
    {
        return $this->hasOne(UserLessonProgress::class)->where('user_id', Auth::id());
    }
}
