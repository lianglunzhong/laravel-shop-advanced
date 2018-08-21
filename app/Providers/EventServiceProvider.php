<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\OrderPaid;
use App\Listeners\UpdateProductSoldCount;
use App\Listeners\SendOrderPaidMail;
use App\Events\OrderReviewd;
use App\Listeners\UpdateProductRating;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\Event' => [
            'App\Listeners\EventListener',
        ],
        // 用户注册成功之后事件监听器
        'Illuminate\Auth\Events\Registered' => [
            'App\Listeners\RegisteredListener',
        ],
        // 订单支付完成后的监听器
        OrderPaid::class => [
            UpdateProductSoldCount::class,
            SendOrderPaidMail::class,
        ],
        // 更新商品评价
        OrderReviewd::class => [
            UpdateProductRating::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
