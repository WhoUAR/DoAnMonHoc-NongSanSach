<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Model một dòng trong sổ điểm tích luỹ.
 *           Cộng điểm khi đơn giao thành công, trừ điểm khi khách dùng
 *           điểm để giảm giá. SUM(points) luôn bằng users.loyalty_points.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyTransaction extends Model
{
    use HasFactory;

    public const TYPE_EARN   = 'earn';
    public const TYPE_REDEEM = 'redeem';
    public const TYPE_ADJUST = 'adjust';

    // Quy đổi: 1 điểm = 1.000đ. Mua 100.000đ được 10 điểm (10% giá trị).
    public const POINT_VALUE = 1000;
    public const EARN_RATE_DIVISOR = 10000;

    protected $fillable = [
        'user_id',
        'type',
        'points',
        'order_id',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
