<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Model Chi tiết gói định kỳ.
 *           Cho biết mỗi lần giao gói này gồm sản phẩm nào, bao nhiêu.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_plan_id',
        'product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
