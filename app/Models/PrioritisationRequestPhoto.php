<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrioritisationRequestPhoto extends Model
{
    protected $guarded = [];

    public function request()
    {
        return $this->belongsTo(PrioritisationRequest::class, 'request_id');
    }
}
