<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestItemSubmission extends Model
{
    protected $fillable = [
        'test_attempt_id',
        'test_item_id',
        'answer',
        'score',
        'is_correct',
        'feedback',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
    ];

    // Relationships
    public function attempt()
    {
        return $this->belongsTo(TestAttempt::class, 'test_attempt_id');
    }

    public function testItem()
    {
        return $this->belongsTo(TestItem::class);
    }
}
