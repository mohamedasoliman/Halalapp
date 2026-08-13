<?php

namespace App\Models;

use App\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportMessage extends Model
{
    public const DIRECTIONS = ['inbound', 'outbound_draft', 'outbound', 'internal_notification', 'discarded_draft'];

    protected $guarded = [];

    protected $casts = [
        'references_header' => 'array',
        'raw_headers' => 'array',
        'received_at' => 'datetime',
        'drafted_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportAttachment::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(SupportDelivery::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
