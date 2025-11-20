<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestAttempt extends Model
{
    protected $fillable = [
        'test_id',
        'student_id',
        'attempt_number',
        'started_at',
        'submitted_at',
        'time_spent_minutes',
        'total_score',
        'status',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    // Relationships
    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function submissions()
    {
        return $this->hasMany(TestItemSubmission::class);
    }

    // Helper methods
    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    public function isSubmitted()
    {
        return in_array($this->status, ['submitted', 'graded']);
    }

    public function calculateTimeSpent()
    {
        if ($this->started_at && $this->submitted_at) {
            return $this->started_at->diffInMinutes($this->submitted_at);
        }

        return null;
    }
}
