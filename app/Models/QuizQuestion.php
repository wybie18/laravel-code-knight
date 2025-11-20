<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model
{
    protected $fillable = [
        'activity_id',
        'question',
        'type',
        'options',
        'correct_answer',
        'explanation',
        'points',
        'order',
    ];

    protected $casts = [
        'options' => 'array'
    ];

    // Relationships
    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    // Polymorphic relationship for test items
    public function testItems()
    {
        return $this->morphMany(TestItem::class, 'itemable');
    }

    // Helper methods
    public function isForActivity()
    {
        return !is_null($this->activity_id);
    }

    public function isForTest()
    {
        return is_null($this->activity_id) && $this->testItems()->exists();
    }
}
