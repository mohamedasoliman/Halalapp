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
	protected $dates = [
		'created_at',
		'updated_at',
		'deleted_at',
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
