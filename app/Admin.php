<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use Notifiable, HasRoles;

    // protected $guard = 'admin';

    protected $guarded = [];

    public function getRole() {
        return $this->belongsTo('App\Models\Role\CustomRole', 'role_id', 'id');
    }
}
