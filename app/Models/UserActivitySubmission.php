<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivitySubmission extends Model
{
    protected $fillable = [
        'activity_id',
        'user_id',
        'submitted_code',
        'is_correct',
        'test_results',
    ];

    protected $casts = ['test_results' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
