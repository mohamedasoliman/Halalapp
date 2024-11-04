<?php

namespace App\Models;

use App\Http\Controllers\Admin\Configurations\GeneralSettingController;
use Illuminate\Database\Eloquent\Model;


class GeneralSetting extends Model
{
    public $timestamps = false;
    protected $table = 'general_settings';
}
