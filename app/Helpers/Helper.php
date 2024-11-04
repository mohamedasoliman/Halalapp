<?php

use App\Models\GeneralSetting;

if (!function_exists('stringcutter')) {
	function stringcutter($text)
	{
		return String::limit($text, 20, ' ...');
	}
}


if (!function_exists('userRoleCheck')) {
    function userRoleCheck($roles)
    {
        return in_array(auth()->user()->role_id, $roles) ? true : false;
    }
}

// if (!function_exists('getSiteSetting')) {
//     function getSiteSetting(){
//         // $web =  GeneralSetting::all();
//         $web = array('website_title'=> 'halalapp');
//         // dd($web);
//         return $web;
//     }
// }

?>
