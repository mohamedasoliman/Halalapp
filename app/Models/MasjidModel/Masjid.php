<?php

namespace App\Models\MasjidModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Masjid extends Model
{
    use HasFactory;
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
