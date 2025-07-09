<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserQuizAnswer extends Model
{
    protected $fillable = [
        'user_id',
        'quiz_question_id',
        'answer',
        'is_correct',
    ];

    protected $casts = ['answer' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function quizQuestion()
    {
        return $this->belongsTo(QuizQuestion::class);
    }
}
