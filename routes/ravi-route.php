<?php
Route::group(['prefix' => 'admin', 'middleware' => ['auth:admin']], function () {
	// Route::resources([
	// 	'maincategory'  => 'Admin\MainCategory\MainCategoryController',
	// ]);


});



?>
