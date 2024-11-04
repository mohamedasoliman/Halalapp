<?php

namespace App\Models\Role;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomRole extends Model
{
    use SoftDeletes;
    protected $table = 'roles';
    protected $guarded = [];
	protected $dates = [
		'created_at',
		'updated_at',
		'deleted_at',
	];
}
