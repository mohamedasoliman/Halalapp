<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestNotificationDelivery extends Model
{
    protected $guarded = [];

    protected $casts = [
        'request_ids' => 'array',
        'halal_status' => 'integer',
        'attempts' => 'integer',
        'last_attempted_at' => 'datetime',
        'sent_at' => 'datetime',
        'uncertain_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
