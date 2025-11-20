<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EssayQuestion extends Model
{
    protected $fillable = [
        'question',
        'rubric',
        'max_points',
        'min_words',
        'max_words',
    ];

    // Polymorphic relationship
    public function testItems()
    {
        return $this->morphMany(TestItem::class, 'itemable');
    }
}
