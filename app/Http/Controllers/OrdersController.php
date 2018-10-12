<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\OrderRequest;
use App\Http\Requests\CrowdFundingOrderRequest;
use App\Http\Requests\SendReviewRequest;
use App\Http\Requests\ApplyRefundRequest;
use App\Models\ProductSku;
use App\Models\UserAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\CouponCode;
use Carbon\Carbon;
use App\Exceptions\InvalidRequestException;
use App\Jobs\CloseOrder;
use App\Services\CartService;
use App\Services\OrderService;
use App\Events\OrderReviewd;

class OrdersController extends Controller
{
    public function store(OrderRequest $request, OrderService $orderService)
    {
    	$user = $request->user();
        $address = UserAddress::find($request->address_id);
        $remark = $request->remark;
        $items = $request->items;
        $coupon  = null;

        // 如果用户提交了优惠码
        if ($code = $request->input('coupon_code')) {
            $coupon = CouponCode::where('code', $code)->first();
            if (!$coupon) {
                throw new CouponCodeUnavailableException('优惠券不存在');
            }
        }

        $order = $orderService->store($user, $address, $remark, $items, $coupon);
    	
    	return $order;
    }

    public function index(Request $request)
    {
        $orders = Order::query()
                // 使用 with 方法预加载，避免 N + 1 问题 
                ->with(['items.product', 'items.productSku'])
                ->where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->paginate();

        return view('orders.index', ['orders' => $orders]);
    }

    public function show(Order $order, Request $request)
    {
        $this->authorize('own', $order);
        
        // 这里的 load() 方法与上一章节介绍的 with() 预加载方法有些类似，称为 延迟预加载，不同点在于 load() 是在已经查询出来的模型上调用，而 with() 则是在 ORM 查询构造器上调用
        $order =  $order->load(['items.productSku', 'items.product']);

        return view('orders.show',['order' => $order]);
    }

    public function received(Order $order, Request $request)
    {
        // 校验权限
        $this->authorize('own', $order);

        // 判断订单的发货状态是否为已发货
        if ($order->ship_status !== Order::SHIP_STATUS_DELIVERED) {
            throw new InvalidRequestException('发货状态不正确');
        }

        // 更新发货状态为已收到
        $order->update(['ship_status' => Order::SHIP_STATUS_RECEIVED]);

        // 返回原页面
        return $order;
    }

    public function review(Order $order)
    {
        $this->authorize('own', $order);

        // 判断是否已经支付
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未支付，不可评价');
        }

        // 未收货订单不能评价
        if ($order->ship_status !== Order::SHIP_STATUS_RECEIVED) {
            throw new InvalidRequestException('该订单未确认收货，不可评价');
        }

        // 使用 load 方法加载关联数据，避免 N + 1 性能问题
        return view('orders.review', [
            'order' => $order->load(['items.productSku', 'items.product']),
        ]);
    }

    public function sendReview(Order $order, SendReviewRequest $request)
    {
        $this->authorize('own', $order);

        // 判断是否已经支付
        if (!$order->paid_at) {
            throw new InvalidRequestException('该订单未支付，不可评价');
        }

        // 未收货订单不能评价
        if ($order->ship_status !== Order::SHIP_STATUS_RECEIVED) {
            throw new InvalidRequestException('该订单未确认收货，不可评价');
        }

        // 判断是否已经评价
        if ($order->reviewed) {
            throw new InvalidRequestException('该订单已评价，不可重复提交');
        }

        $reviews = $request->input('reviews');

        // 开启事物
        \DB::transaction(function() use ($reviews , $order) {
            // 遍历用户提交的数据
            foreach ($reviews as $review) {
                $orderItem = $order->items()->find($review['id']);
                // 保存评分和评价
                $orderItem->update([
                    'rating'      => $review['rating'],
                    'review'      => $review['review'],
                    'reviewed_at' => Carbon::now(),
                ]);
            }

            // 将订单标记为已评价
            $order->update(['reviewed' => true]);

            event(new OrderReviewd($order));
        });

        return redirect()->back();
    }

    public function applyRefund(Order $order, ApplyRefundRequest $request)
    {
        $this->authorize('own', $order);

        if (!$order->paid_at) {
            throw new InvalidRequestException('改订单未支付，不可退款');
        }

        if ($order->refund_status !== Order::REFUND_STATUS_PENDING) {
            throw new InvalidRequestException('该订单已经申请过退款，请勿重复申请');
        }

        $extra = $order->extra ?: [];
        $extra['refund_reason'] = $request->input('reason');

        $order->update([
            'refund_status' => Order::REFUND_STATUS_APPLIED,
            'extra' => $extra,
        ]);

        return $order;
    }

    // 众筹商品下单请求
    public function crowdfunding(CrowdFundingOrderRequest $request, OrderService $orderService)
    {
        $user = $request->user();
        $sku = productSku::find($request->input('sku_id'));
        $address = UserAddress::find($request->input('address_id'));
        $amount = $request->input('amount');

        return $orderService->crowdfunding($user, $address, $sku, $amount);
    }
}
