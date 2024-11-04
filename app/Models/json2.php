<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class json2 extends Model
{
    use HasFactory;
    public $table = 'json2';
    protected $fillable = ['jsondata_id'];

    public function jsonmeta()
    {
        return $this->hasMany(jsonmeta::class);
    }

    public function jsondata()
    {
        return $this->belongsTo(jsondata::class);
    }
}
