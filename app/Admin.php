<?php

namespace App;

use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Admin extends Authenticatable
{
    use HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'status',
        'admin_image',
        'phone',
        'username',
        'shop_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getRole()
    {
        return $this->belongsTo('App\Models\Role\CustomRole', 'role_id', 'id');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AdminResetPasswordNotification($token));
    }
}
