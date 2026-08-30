<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function informationReplies(): HasMany
    {
        return $this->hasMany(UserInformationReply::class, 'request_notification_delivery_id');
    }
}
