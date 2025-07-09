<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BadgeCategory extends Model
{
    protected $fillable = [
        'name',
    ];

    public function badges()
    {
        return $this->hasMany(Badge::class, 'category_id');
    }
}
