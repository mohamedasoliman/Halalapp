<?php

use Illuminate\Support\Facades\Auth;
use App\Models\Role\CustomRole;


if (!function_exists('getLoginUserRoleName')) {
    function getLoginUserRoleName()
    {
        $role = CustomRole::find(Auth::user()->role_id ?? 0);
        $roleName = $role ? $role->name : 'Admin';
        return str_replace('_', ' ', ucwords($roleName, '_'));
    }
}
if (!function_exists('getRoleNameBYId')) {
    function getRoleNameBYId($roleId)
    {
        $role = CustomRole::find($roleId);
        $roleName = $role ? $role->name : 'Admin';
        return str_replace('_', ' ', $roleName);
    }
}
?>
