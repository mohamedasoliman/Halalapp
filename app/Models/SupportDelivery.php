<?php

namespace App\Models;

use App\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportDelivery extends Model
{
    public const STATUSES = ['pending', 'sending', 'sent', 'failed', 'uncertain'];

    protected $guarded = [];

    protected $casts = [
        'attempts' => 'integer',
        'last_attempted_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'uncertain_at' => 'datetime',
        'reconciled_by' => 'integer',
        'reconciled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportMessage::class, 'support_message_id');
    }

    public function reconciler(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reconciled_by');
    }
}
