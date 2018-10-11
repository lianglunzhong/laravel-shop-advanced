<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrowdfundingProduct extends Model
{
    // 定义众筹的 3 种状态
    const STATUS_FUNDING = 'funding';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAIL = 'fail';

    public static $statusMap = [
    	self::STATUS_FUNDING => '众筹中',
    	self::STATUS_SUCCESS => '众筹成功',
    	self::STATUS_FAIL => '众筹失败',
    ];

    protected $fillable = ['total_amount', 'target_amount', 'user_count', 'status', 'end_at'];

    // end_at 会自动转为 Carbon 类型
    protected $dates = ['end_at'];
    // 不需要 created_at 和 updated_at 字段
    public $timestamps = false;

    public function product()
    {
    	return $this->belongsTo(Product::class);
    }

    // 定义一个名为 percent 的访问器，返回当前忠诚进度
    public function getPercentAttribute()
    {
    	// 已筹金额除以目标金额 this->total_amount/ $this->target_amount;
    	//在访问器里面除非有特殊需求，否则一律用 $this->attributes['xxx'] 来获取属性值，原因有两个：1.通过 $this->xxx 的方式有可能会获取到别的访问器的值，而不是真正要的原始值；2.如果 $this->xxx 这个也是访问器，也恰巧依赖于当前的访问器，那就会造成死循环。
    	$value = $this->attributes['total_amount'] / $this->attributes['target_amount'];

    	return floatval(number_format($value * 100, 2, '.', ''));
    }
}
