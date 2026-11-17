<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Model Đơn hàng.
 *           - Quan hệ: khách, các dòng hàng, phân bổ lô, thanh toán, khiếu nại.
 *           - `canBeCancelled()`: chỉ huỷ được khi đơn còn chờ duyệt.
 *           - `canBeComplained()`: đơn đã giao và còn trong hạn 24 giờ.
 *           Đây là 2 quy tắc nghiệp vụ quan trọng giảng viên hay hỏi.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPING  = 'shipping';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_COD   = 'cod';
    public const PAYMENT_VNPAY = 'vnpay';

    protected $fillable = [
        'order_code',
        'user_id',
        'subscription_id',
        'receiver_name',
        'receiver_phone',
        'shipping_address',
        'note',
        'subtotal',
        'shipping_fee',
        'discount_amount',
        'points_used',
        'total_amount',
        'payment_method',
        'payment_status',
        'status',
        'confirmed_at',
        'delivered_at',
        'cancelled_at',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'        => 'decimal:0',
            'shipping_fee'    => 'decimal:0',
            'discount_amount' => 'decimal:0',
            'total_amount'    => 'decimal:0',
            'points_used'     => 'integer',
            'confirmed_at'    => 'datetime',
            'delivered_at'    => 'datetime',
            'cancelled_at'    => 'datetime',
        ];
    }

    // ------------------------------------------------------------------
    // Quan hệ
    // ------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function batchAllocations(): HasMany
    {
        return $this->hasMany(OrderBatchAllocation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function complaint(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class, 'subscription_id');
    }

    // ------------------------------------------------------------------
    // Quy tắc nghiệp vụ
    // ------------------------------------------------------------------

    /**
     * Khách chỉ được tự huỷ khi đơn chưa được Sales duyệt.
     * Đã duyệt rồi thì hàng đã bị trừ khỏi lô, phải liên hệ CSKH.
     */
    public function canBeCancelled(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Điều kiện để khách được gửi khiếu nại:
     *   1. Đơn đã giao thành công.
     *   2. Chưa quá 24 giờ kể từ lúc giao (nông sản tươi hỏng nhanh).
     */
    public function canBeComplained(): bool
    {
        if ($this->status !== self::STATUS_DELIVERED || $this->delivered_at === null) {
            return false;
        }

        return $this->delivered_at->copy()->addHours(24)->isFuture();
    }
}
