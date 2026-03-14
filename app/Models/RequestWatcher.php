<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestWatcher extends Model
{
    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(PrioritisationRequest::class, 'request_id');
    }
}
