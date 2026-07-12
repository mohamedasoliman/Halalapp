<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsDailySummary extends Model
{
    protected $fillable = [
        'summary_date',
        'event_name',
        'entity_type',
        'entity_key',
        'entity_label',
        'dimension_key',
        'dimension_value',
        'event_count',
    ];

    protected $casts = [
        'summary_date' => 'date',
        'event_count' => 'integer',
    ];
}
