<?php
namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'student_id',
        'first_name',
        'last_name',
        'email',
        'password',
        'avatar',
        'role_id',
        'total_xp',
        'current_level',
        'provider',
        'provider_id',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Relationships
    public function role()
    {
        return $this->belongsTo(UserRole::class, 'role_id');
    }

    public function achievements()
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->with('type')
            ->withPivot('earned_at')
            ->withTimestamps();
    }

    public function courseEnrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function courseProgress()
    {
        return $this->hasMany(UserCourseProgress::class);
    }

    public function moduleProgress()
    {
        return $this->hasMany(UserModuleProgress::class);
    }

    public function activityProgress()
    {
        return $this->hasMany(UserActivityProgress::class);
    }

    public function lessonProgress()
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    public function activitySubmissions()
    {
        return $this->hasMany(UserActivitySubmission::class);
    }

    public function streaks()
    {
        return $this->hasMany(UserStreak::class);
    }

    public function expTransactions()
    {
        return $this->hasMany(ExpTransaction::class);
    }

    public function challengeSubmissions()
    {
        return $this->hasMany(ChallengeSubmission::class);
    }

    // Test Relationships
    public function createdTests()
    {
        return $this->hasMany(Test::class, 'teacher_id');
    }

    public function assignedTests()
    {
        return $this->belongsToMany(Test::class, 'test_students', 'student_id', 'test_id')
            ->withTimestamps();
    }

    public function testAttempts()
    {
        return $this->hasMany(TestAttempt::class, 'student_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }
}
