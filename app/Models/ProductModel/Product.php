<?php

namespace App\Models\ProductModel;

use App\Support\ProductBarcode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $guarded = [];

    protected $attributes = [
        'halal_status' => '2',
    ];

    public function scopeMatchingBarcode(Builder $query, string $barcode): Builder
    {
        $barcode = ProductBarcode::clean($barcode);
        $canonicalBarcode = ProductBarcode::canonical($barcode);

        if (ProductBarcode::isValidGtin($canonicalBarcode)) {
            return $query->where(
                'barcode_key',
                ProductBarcode::key($canonicalBarcode),
            );
        }

        // Preserve exact lookup support for historical placeholder or malformed
        // values without making valid mobile scans use an unindexed OR query.
        return $query->where('Barcode', $barcode);
    }

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getAllUniqueCategories()
    {
        $uniqueCategories = Product::whereNotNull('category') // Filter out null values
            ->distinct()
            ->pluck('category')
            ->toArray();

        return $uniqueCategories;
    }
}
