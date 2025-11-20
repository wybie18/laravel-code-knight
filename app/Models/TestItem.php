<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestItem extends Model
{
    protected $fillable = [
        'test_id',
        'itemable_type',
        'itemable_id',
        'order',
        'points',
    ];

    // Relationships
    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    public function itemable()
    {
        return $this->morphTo();
    }

    public function submissions()
    {
        return $this->hasMany(TestItemSubmission::class);
    }
}
