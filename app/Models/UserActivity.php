<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
    protected $fillable = [
        'user_id',
        'activity_type',
        'activity_date',
        'activityable_id',
        'activityable_type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function activity()
    {
        return $this->morphTo();
    }
}
