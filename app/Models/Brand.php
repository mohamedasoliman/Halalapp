<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_contacted_at' => 'datetime',
        'contact_verified_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'outreach_paused_at' => 'datetime',
        'follow_up_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function communications()
    {
        return $this->hasMany(BrandCommunication::class);
    }

    public function outreachBatches()
    {
        return $this->hasMany(BrandOutreachBatch::class);
    }

    public function prioritisationRequests()
    {
        return $this->hasMany(PrioritisationRequest::class, 'brand_name', 'name');
    }

    public function scopeWithEmail($query)
    {
        return $query->whereNotNull('email')->where('email', '!=', '');
    }
}
