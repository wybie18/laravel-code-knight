<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
        return $this->hasMany(UserAchievement::class);
    }

    public function badges()
    {
        return $this->hasMany(UserBadge::class);
    }

    public function courseEnrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function courseProgress()
    {
        return $this->hasMany(UserCourseProgress::class);
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
