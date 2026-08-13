<?php

namespace App\Models;

use App\Admin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public const CATEGORIES = [
        'general_inquiry',
        'product_issue',
        'restaurant_update',
        'masjid_update',
        'bug_report',
        'feature_request',
        'privacy_security',
        'advertising',
        'barcode_submission',
        'muslim_business_network',
        'event_submission',
        'business',
        'other',
        'no_action',
    ];

    public const PRIORITIES = ['urgent', 'high', 'normal', 'low'];

    public const STATUSES = [
        'new',
        'triaged',
        'waiting_user',
        'waiting_internal',
        'draft_ready',
        'resolved',
        'no_action',
    ];

    public const HANDOFFS = [
        'product_prioritisation',
        'manufacturer_review',
        'restaurant_directory',
        'masjid_directory',
        'technical_backlog',
        'privacy_review',
        'business_team',
    ];

    public const LINKED_ENTITY_TYPES = [
        'product',
        'prioritisation_request',
        'restaurant',
        'masjid',
        'business',
        'development_issue',
        'other',
    ];

    protected $guarded = [];

    protected $casts = [
        'assigned_to' => 'integer',
        'received_at' => 'datetime',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function normalizedRequesterEmailHash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportAttachment::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(SupportDelivery::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SupportTicketEvent::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_to');
    }
}
