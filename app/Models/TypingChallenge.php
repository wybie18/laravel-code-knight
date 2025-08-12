<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypingChallenge extends Model
{
    public $timestamps = false;
    protected $fillable = ['text_content', 'programming_language_id', 'target_wpm', 'target_accuracy'];

    public function challenge()
    {
        return $this->morphOne(Challenge::class, 'challengeable');
    }

    public function programmingLanguage()
    {
        return $this->belongsTo(ProgrammingLanguage::class);
    }
}
