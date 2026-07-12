<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    protected $fillable = [
        'event_uuid',
        'anonymous_id',
        'session_id',
        'event_name',
        'entity_type',
        'entity_key',
        'entity_label',
        'properties',
        'platform',
        'app_version',
        'occurred_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'occurred_at' => 'datetime',
    ];
}
