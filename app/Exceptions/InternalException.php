<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

class InternalException extends Exception
{
    protected $mesForUser;

    public function __construct(string $message, string $msgForUser = '系统内部错误', int $code = 500)
    {
    	parent::__construct($message, $code);
    	$this->msgForUser = $msgForUser;
    }

    public function render(Request $request)
    {
    	if ($request->expectsJson()) {
    		// json() 方法第二个参数就是 Http 的返回码
    		return response()->json(['msg' => $this->msgForUser], $this->code);
    	}

    	return view('pages.error', ['msg' => $this->msgForUser]);
    }
}
