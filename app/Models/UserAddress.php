<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    protected $fillable = [
    	'province',
        'city',
        'district',
        'address',
        'zip',
        'contact_name',
        'contact_phone',
        'last_used_at',
    ];

    // 需要被转换成日期的属性
    protected $dates = ['last_used_at'];

    public function user() {
    	return $this->belongsTo(User::class);
    }

    public function getFullAddressAttribute()
    {
    	return $this->province . $this->city . $this->district . $this->address;
    }
}
