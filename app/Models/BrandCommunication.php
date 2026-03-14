<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandCommunication extends Model
{
    protected $guarded = [];

    protected $casts = [
        'barcodes_mentioned' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
