<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Model Giao dịch thanh toán (VNPay / COD).
 *           Lưu lại mã giao dịch do VNPay trả về để đối soát khi có
 *           tranh chấp. Với COD, một dòng method = 'cod' được tạo khi
 *           giao hàng thành công.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_SUCCESS  = 'success';
    public const STATUS_FAILED   = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'order_id',
        'customer_subscription_id',
        'method',
        'amount',
        'vnp_txn_ref',
        'vnp_transaction_no',
        'vnp_response_code',
        'bank_code',
        'status',
        'raw_response',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'decimal:0',
            'raw_response' => 'array',
            'paid_at'      => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class, 'customer_subscription_id');
    }
}
