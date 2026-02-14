<?php

namespace App\Models\MasjidModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Masjid extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'Masjid_name',
        'Address',
        'Area_id',
        'Area_name',
        'Website',
        'Fajar',
        'Duhur',
        'Asr',
        'Maghrib',
        'Ishaa',
        'Jumaa',
        'Latitude',
        'Longitude'
    ];
}
