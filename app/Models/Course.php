<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Course extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'course_code',
        'description',
        'short_description',
        'objectives',
        'requirements',
        'thumbnail',
        'difficulty_id',
        'category_id',
        'exp_reward',
        'estimated_duration',
        'is_published',
        'visibility',
        'programming_language_id',
        'created_by',
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

    public function modules()
    {
        return $this->hasMany(CourseModule::class)->orderBy('order');
    }

    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function userEnrollment()
    {
        return $this->hasOne(CourseEnrollment::class)->where('user_id', Auth::id());
    }

    public function skillTags()
    {
        return $this->belongsToMany(SkillTag::class, 'course_skill_tag');
    }

    public function userProgress()
    {
        return $this->hasMany(UserCourseProgress::class);
    }

    public function currentUserProgress()
    {
        return $this->hasOne(UserCourseProgress::class)->where('user_id', Auth::id());
    }

    public function programmingLanguage()
    {
        return $this->belongsTo(ProgrammingLanguage::class);
    }

    public function tests()
    {
        return $this->hasMany(Test::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if course is private
     */
    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    /**
     * Check if course is public
     */
    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }
}
