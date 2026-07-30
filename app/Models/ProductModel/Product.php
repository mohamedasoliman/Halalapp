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
        $key = ProductBarcode::key($barcode);

        return $query->where(function (Builder $query) use ($barcode, $key) {
            $query->where('Barcode', $barcode);
            if ($key !== null) {
                $query->orWhere('barcode_key', $key);
            }
        });
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
