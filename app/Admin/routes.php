<?php

use Illuminate\Routing\Router;

Admin::registerAuthRoutes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {
	// 首页
    $router->get('/', 'HomeController@index');
    // 用户列表
    $router->get('users', 'UsersController@index');
    // 产品相关
    $router->resource('products', 'ProductsController');

});
