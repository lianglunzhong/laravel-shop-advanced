<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CouponCode;
use Carbon\Carbon;

class CouponCodesController extends Controller
{
    public function show($code)
    {
    	// 判断优惠券是否存在
    	if (!$record = CouponCode::where('code', $code)->first()) {
    		// abort() 方法可以直接中断我们程序的运行，接受的参数会变成 Http 状态码返回
    		// abort(404);
    		throw new CouponCodeUnavailableException('优惠券不存在');
    	}

    	$record->checkAvailable($request->user());

        return $record;
    }
}
