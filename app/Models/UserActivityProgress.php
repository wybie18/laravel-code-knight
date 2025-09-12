<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivityProgress extends Model
{
    protected $fillable = [
        'user_id', 'activity_id', 'started_at', 'completed_at', 'is_completed'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_completed' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
