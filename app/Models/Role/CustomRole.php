<?php

namespace App\Models\Role;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomRole extends Model
{
    use SoftDeletes;
    protected $table = 'roles';
    protected $guarded = [];
	protected $casts = [
		'created_at' => 'datetime',
		'updated_at' => 'datetime',
		'deleted_at' => 'datetime',
	];
}
