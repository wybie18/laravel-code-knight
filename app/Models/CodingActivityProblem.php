<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodingActivityProblem extends Model
{
    protected $fillable = [
        'problem_statement',
        'test_cases',
        'solution_code',
        'starter_code',
    ];

    protected $casts = [
        'test_cases' => 'array', 
        'starter_code' => 'array'
    ];

    public function activities()
    {
        return $this->hasMany(Activity::class, 'coding_activity_problem_id');
    }
}
