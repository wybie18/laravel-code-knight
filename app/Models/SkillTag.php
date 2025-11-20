<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillTag extends Model
{
    protected $fillable = [
        'name',
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_skill_tag');
    }
}
