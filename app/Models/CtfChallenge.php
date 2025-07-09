<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CtfChallenge extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'flag', 
        'file_paths', 
        'category_id'
    ];
    protected $casts = ['file_paths' => 'array'];

    public function challenge()
    {
        return $this->morphOne(Challenge::class, 'challengeable');
    }

    public function category()
    {
        return $this->belongsTo(CtfCategory::class, 'category_id');
    }
}
