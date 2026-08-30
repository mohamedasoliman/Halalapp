<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInformationReplyAttachment extends Model
{
    public const SECURITY_STATUSES = [
        'pending_review',
        'accepted_photo',
        'quarantined',
        'rejected',
    ];

    protected $guarded = [];

    protected $casts = [
        'reply_id' => 'integer',
        'prioritisation_request_photo_id' => 'integer',
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'promoted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function reply(): BelongsTo
    {
        return $this->belongsTo(UserInformationReply::class, 'reply_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(PrioritisationRequestPhoto::class, 'prioritisation_request_photo_id');
    }
}
