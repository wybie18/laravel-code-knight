<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = [
        'lesson_id',
        'title',
        'description',
        'type',
        'coding_activity_problem_id',
        'exp_reward',
        'order',
        'is_required',
    ];

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function codingActivityProblem()
    {
        return $this->belongsTo(CodingActivityProblem::class);
    }

    public function activitySubmissions()
    {
        return $this->hasMany(UserActivitySubmission::class);
    }

    public function quizQuestions()
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }
}
