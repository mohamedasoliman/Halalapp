<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasjidTimingCorrection extends Model
{
    protected $fillable = [
        'masjid_id',
        'area_id',
        'masjid_name',
        'status',
        'original_times',
        'submitted_changes',
        'verified_times',
        'request_fingerprint',
        'install_fingerprint',
        'failure_reason',
    ];

    protected $casts = [
        'original_times' => 'array',
        'submitted_changes' => 'array',
        'verified_times' => 'array',
    ];
}
