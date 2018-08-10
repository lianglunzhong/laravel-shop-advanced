<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Cache;
use Illuminate\Http\Request;
use App\Notifications\EmailVerifiCationNotification;
use Mail;
use App\Exceptions\InvalidRequestException;

class EmailVerificationController extends Controller
{
	protected $cache_key_prefix = 'email_verification_';

	/**
	 * 验证邮箱
	 */
    public function verify(Request $request)
    {
    	// 从 url 中获取 email 和 token 两个参数
    	$email = $request->input('email');
    	$token = $request->token;

    	// 如果有一个为空，说明不是一个合法的验证链接，直接抛出异常
    	if (!$email || !$token) {
    		throw new InvalidRequestException('验证链接不正确');
    	}

    	// 从缓存中取出数据，并对比
    	$key = $this->cache_key_prefix . $email;
    	if ($token != Cache::get($key)) {
    		throw new InvalidRequestException('验证链接不正确或已过期');
    	}

    	// 取出用户
    	if (!$user = User::where('email', $email)->first()) {
    		throw new InvalidRequestException('用户不存在');
    	}

    	// 更新用户数据
    	$user->update(['email_verified' => true]);
    	// 删除缓存
    	Cache::forget($key);

    	return redirect()->route('root')->with('success', '邮箱验证成功');
    }

    /**
     * 用户主动请求发送验证邮件
     */
    public function send(Request $request)
    {
        $user = $request->user();
        // 判断用户是否已经激活
        if ($user->email_verified) {
            return back()->with('message', '你已经验证过邮箱了');
            // throw new InvalidRequestException('你已经验证过邮箱了');
        }

        // 调用 notify() 方法用来发送我们定义好的公知类
        $user->notify(new EmailVerifiCationNotification());

        return redirect()->route('root')->with('success', '邮件发送成功');
    }
}
