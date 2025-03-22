<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'v2_order';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'surplus_order_ids' => 'array'
    ];

    protected $fillable = [
        'user_id',
        'plan_id',
        'period',
        'trade_no',
        'total_amount',
        'status',
        'type',
        'redeem_code',
        'created_at',
        'updated_at'
    ];

    /**
     * 获取订单关联的用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
