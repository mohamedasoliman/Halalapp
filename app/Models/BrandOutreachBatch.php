<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandOutreachBatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'products' => 'array',
        'request_ids' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
