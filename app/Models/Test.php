<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Test extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'teacher_id',
        'course_id',
        'title',
        'slug',
        'description',
        'instructions',
        'duration_minutes',
        'total_points',
        'start_time',
        'end_time',
        'status',
        'shuffle_questions',
        'show_results_immediately',
        'allow_review',
        'max_attempts',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'shuffle_questions' => 'boolean',
        'show_results_immediately' => 'boolean',
        'allow_review' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($test) {
            if (empty($test->slug)) {
                $test->slug = Str::slug($test->title);
            }
        });
    }

    // Relationships
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function items()
    {
        return $this->hasMany(TestItem::class)->orderBy('order');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'test_students', 'test_id', 'student_id')
            ->withTimestamps();
    }

    public function attempts()
    {
        return $this->hasMany(TestAttempt::class);
    }

    // Helper methods
    public function isActive()
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = now();

        if ($this->start_time && $now->lt($this->start_time)) {
            return false;
        }

        if ($this->end_time && $now->gt($this->end_time)) {
            return false;
        }

        return true;
    }

    public function isAccessibleBy(User $user)
    {
        // Teachers can access their own tests
        if ($this->teacher_id === $user->id) {
            return true;
        }

        // Students must be assigned to the test
        if (!$this->students()->where('test_students.student_id', $user->id)->exists()) {
            return false;
        }

        // If test is part of a course, student must be enrolled
        if ($this->course_id) {
            $isEnrolled = $user->courseEnrollments()
                ->where('course_id', $this->course_id)
                ->exists();

            if (!$isEnrolled) {
                return false;
            }
        }

        return true;
    }

    public function canStartAttempt(User $user)
    {
        if (!$this->isActive()) {
            return false;
        }

        if (!$this->isAccessibleBy($user)) {
            return false;
        }

        $attemptCount = $this->attempts()
            ->where('student_id', $user->id)
            ->count();

        return $attemptCount < $this->max_attempts;
    }

    public function getStudentAttempts(User $user)
    {
        return $this->attempts()
            ->where('student_id', $user->id)
            ->orderBy('attempt_number', 'desc')
            ->get();
    }
}
