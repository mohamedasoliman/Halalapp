<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrioritisationRequest extends Model
{
    protected $guarded = [];

    protected $casts = [
        'resolved_status' => 'integer',
        'resolution_communication_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function watchers()
    {
        return $this->hasMany(RequestWatcher::class, 'request_id');
    }

    public function brand()
    {
        return Brand::where('name', $this->brand_name)->first();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['resolved', 'dead_end']);
    }
}
