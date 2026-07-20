<?php

namespace App\Models;

use App\Support\ProductBarcode;
use Illuminate\Database\Eloquent\Builder;
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

    public function scopeMatchingBarcode(Builder $query, string $barcode): Builder
    {
        $barcode = ProductBarcode::clean($barcode);
        $key = ProductBarcode::key($barcode);

        return $query->where(function (Builder $query) use ($barcode, $key) {
            $query->where('barcode', $barcode);
            if ($key !== null) {
                $query->orWhere('barcode_key', $key);
            }
        });
    }
}
