<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class jsondata extends Model
{
    use HasFactory;

    protected $fillable = ['Name','slug','Description'];

    public function json2()
    {
        return $this->hasMany(json2::class);
    }
}
