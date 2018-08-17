<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// 首页
Route::redirect('/', '/products')->name('root');

// 用户 user 相关
Auth::routes();

// 需要用户登录才能访问的路由组
Route::group([
	'middleware' => 'auth' // 登录用户才能访问
], function() {
	// 验证邮箱提示
	Route::get('/email_verify_notice', 'PagesController@emailVerifyNotice')->name('email_verify_notice');
	// 验证邮箱
	Route::get('/email_verification/verify', 'EmailVerificationController@verify')->name('email_verification.verify');
	// 发送验证邮件
	Route::get('/email_verification/send', 'EmailVerificationController@send')->name('email_verification.send');

	// 需要用户验证过邮箱才能访问的路由组
	Route::group(['middleware' => 'email_verified'], function() {
		// 收货地址
		Route::resource('user_addresses', 'UserAddressesController');
		// 收藏商品
		Route::post('products/{product}/favorite', 'ProductsController@favor')->name('products.favor');
		// 取消收藏
		Route::delete('products/{product}/favorite', 'ProductsController@disfavor')->name('products.disfavor');
		// 收藏列表
		Route::get('products/favorites', 'ProductsController@favorites')->name('products.favorites');
		// 加入购物车
		Route::post('cart', 'CartController@add')->name('cart.add');
		// 购物车
		Route::get('cart', 'CartController@index')->name('cart.index');
		// 购物车移除商品
		Route::delete('cart/{sku}', 'CartController@remove')->name('cart.remove');
		// 保存订单
		Route::post('orders', 'OrdersController@store')->name('orders.store');
		// 订单列表
		Route::get('orders', 'OrdersController@index')->name('orders.index');
		// 订单详情
		Route::get('orders/{order}', 'OrdersController@show')->name('orders.show');
		// 支付宝支付
		Route::get('payment/{order}/alipay', 'PaymentController@payByAlipay')->name('payment.alipay');
		// 支付宝支付前端回调
		Route::get('payment/alipay/return', 'PaymentController@alipayReturn')->name('payment.alipay.return');
	});
});

// 产品列表
Route::get('products', 'ProductsController@index')->name('products.index');
// 产品详情
Route::get('products/{product}', 'ProductsController@show')->name('products.show');
// 支付宝支付服务器回调
Route::post('payment/alipay/notify', 'PaymentController@alipayNotify')->name('payment.alipay.notify');

