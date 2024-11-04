<?php

use Illuminate\Support\Facades\Auth;
use App\Models\Role\CustomRole;


if (!function_exists('getLoginUserRoleName')) {
    function getLoginUserRoleName()
    {
        $roleName = CustomRole::find(Auth::user()->role_id)->name;
        return str_replace('_', ' ', ucwords($roleName, '_'));
    }
}
if (!function_exists('getRoleNameBYId')) {
    function getRoleNameBYId($roleId)
    {
        $roleName = CustomRole::find($roleId)->name;
        return str_replace('_', ' ', $roleName);
    }
}
?>
