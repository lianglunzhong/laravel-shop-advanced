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
    // 订单列表
    $router->get('orders', 'OrdersController@index')->name('admin.orders.index');
    // 订单详情
    $router->get('orders/{order}', 'OrdersController@show')->name('admin.orders.show');
    // 发货
    $router->post('orders/{order}/ship', 'OrdersController@ship')->name('admin.orders.ship');
    // 退款
    $router->post('orders/{order}/refund', 'OrdersController@handleRefund')->name('admin.orders.handle_refund');
    // 优惠券相关
    $router->resource('coupon_codes', 'CouponCodesController');

    // 类目相关
    $router->resource('categories', 'CategoriesController', ['except' => ['show', 'destroy']]);
    $router->get('api/categories', 'CategoriesController@apiIndex');

    // 众筹商品相关
    $router->resource('crowdfunding_products', 'CrowdfundingProductsController', ['except' => ['show', 'destroy']]);

    // 秒杀商品相关
    $router->get('seckill_products', 'SeckillProductsController@index');
    $router->get('seckill_products/create', 'SeckillProductsController@create');
    $router->post('seckill_products', 'SeckillProductsController@store');
    $router->get('seckill_products/{id}/edit', 'SeckillProductsController@edit');
    $router->put('seckill_products/{id}', 'SeckillProductsController@update');
});
