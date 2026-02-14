<?php

namespace App\Models\ProductModel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';
    protected $guarded = [];
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
