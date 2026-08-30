<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserInformationReply extends Model
{
    public const PROCESSING_STATUSES = [
        'pending_review',
        'processed',
        'needs_clarification',
        'no_action',
        'manual_review',
    ];

    protected $guarded = [];

    protected $casts = [
        'request_id' => 'integer',
        'request_notification_delivery_id' => 'integer',
        'references_header' => 'array',
        'delivered_to' => 'array',
        'raw_headers' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(PrioritisationRequest::class, 'request_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(RequestNotificationDelivery::class, 'request_notification_delivery_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(UserInformationReplyAttachment::class, 'reply_id')->orderBy('id');
    }
}
