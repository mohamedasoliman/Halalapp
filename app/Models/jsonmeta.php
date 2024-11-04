<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class jsonmeta extends Model
{
    use HasFactory;
    public $table = 'jsonmeta';
    protected $fillable = ['json2_id','meta_key','meta_value'];

    public function json2()
    {
        return $this->belongsTo(json2::class);
    }
}
