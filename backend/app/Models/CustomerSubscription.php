<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Model Đăng ký gói định kỳ của khách.
 *           - `next_delivery_date`: ngày giao kế tiếp, được lệnh
 *             subscription:generate-orders cập nhật sau mỗi lần sinh đơn.
 *           - `isActive()`: gói còn hiệu lực và chưa hết kỳ.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSubscription extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'subscription_code',
        'user_id',
        'subscription_plan_id',
        'start_date',
        'end_date',
        'delivery_weekdays',
        'receiver_name',
        'receiver_phone',
        'shipping_address',
        'total_paid',
        'payment_method',
        'payment_status',
        'status',
        'next_delivery_date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'start_date'         => 'date',
            'end_date'           => 'date',
            'next_delivery_date' => 'date',
            'delivery_weekdays'  => 'array',
            'total_paid'         => 'decimal:0',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'subscription_id');
    }

    /**
     * Gói còn đang chạy và chưa quá ngày kết thúc.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->end_date->isFuture();
    }
}
