<?php

namespace App\Models\ResturantModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resturant extends Model
{
    use HasFactory;
    public $skipValidation = true;
    protected $fillable = [
        'Resturant_name',
        'Description',
        'Website',
        'Logo',
        'Image_1',
        'Image_2',
        'Image_3',
        'Image_4',
        'Image_5',
        'Image_6',
        'Phone',
    ];
}
