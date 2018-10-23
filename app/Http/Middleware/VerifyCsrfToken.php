<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        // 支付宝支付服务器回调
        'payment/alipay/notify',
        // 支付宝分期付款服务区回调
        'installments/alipay/notify',
    ];
}
