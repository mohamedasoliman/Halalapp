<?php

namespace App\Models;

use App\Models\ProductModel\Product;
use Illuminate\Database\Eloquent\Model;

class BrandCommunicationBarcodeDisposition extends Model
{
    public const TERMINAL_DISPOSITIONS = [
        'applied',
        'kept_unreviewed',
        'needs_clarification',
        'no_action',
    ];

    protected $guarded = [];

    protected $casts = [
        'product_id' => 'integer',
        'resolved_status' => 'integer',
        'decided_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function communication()
    {
        return $this->belongsTo(BrandCommunication::class, 'brand_communication_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->disposition, self::TERMINAL_DISPOSITIONS, true);
    }
}
