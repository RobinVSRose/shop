<?php

use Illuminate\Routing\Router;

//$router->resource('product_category', Ejoy\Shop\Admin\Controllers\ProductCategoryController::class);
//$router->resource('product', Ejoy\Shop\Admin\Controllers\ProductController::class);
//$router->resource('page', Ejoy\Shop\Admin\Controllers\PageController::class);

Route::group([
    'prefix'=>'admin',
    'namespace'     => 'Ejoy\Shop\Controllers\Admin',
], function (Router $router) {
    $router->resource('product_category', 'ProductCategoryController');
    $router->resource('product', 'ProductController');
    $router->resource('page', 'PageController');
    $router->resource('order', 'OrderController');

});

Route::group([
    'prefix'=>'api',
    'namespace'=> 'Ejoy\Shop\Controllers',
], function (Router $router) {
    $router->middleware(['login'])->group(function(Router $router){
        $router->any('product/list', 'ProductController@productList');
        $router->any('category/list', 'ProductController@categoryList');
        $router->any('product/detail', 'ProductController@productDetail');//产品详情
        $router->middleware(['user'])->group(function (Router $router) {//必须进行用户注册绑定的路由
            $router->any('user/cart/list', 'CartController@cartList');//购物车-产品列表
            $router->any('user/cart/update', 'CartController@updateCart');//更新购物车
            $router->any('user/cart/add', 'CartController@addCart');//添加购物车
            $router->middleware('throttle:2,1')->any('user/sms/send', 'UserController@send_sms');//发送短信验证码，每分钟一次
            $router->any('user/activity/list', 'ActivityController@myActivityList');//我的活动
            $router->any('user/order/calculate', 'OrderController@calculate');//订单金额计算
            $router->any('user/order/list', 'OrderController@orderList');//订单列表
            $router->any('user/order/detail', 'OrderController@orderDetail');//订单详情
            $router->any('user/order/cancel', 'OrderController@cancelOrder');//取消订单
            $router->any('user/order/confirm', 'OrderController@confirmOrder');//确认订单-确认收货
            $router->any('user/order/express_info', 'OrderController@expressInfo');//订单-物流信息
            $router->any('user/order/statistics', 'OrderController@orderStatistics');//订单-数量统计
            $router->any('user/wechat/index', 'WechatController@index');//支付
            $router->any('user/order/commit', 'OrderController@commit');//订单提交
        });
    });

});
