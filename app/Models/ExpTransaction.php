<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'description',
        'source_id',
        'source_type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function source()
    {
        return $this->morphTo();
    }
}
