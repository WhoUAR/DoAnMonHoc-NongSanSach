<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Model Gói rau định kỳ (mẫu gói do nông trại bán).
 *           `delivery_weekdays` ví dụ [3, 6] = giao Thứ 3 và Thứ 6.
 *           `items()` là danh sách sản phẩm có trong một lần giao.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_url',
        'price',
        'duration_days',
        'delivery_weekdays',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'delivery_weekdays' => 'array',
            'price'             => 'decimal:0',
            'duration_days'     => 'integer',
            'is_active'         => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionPlanItem::class);
    }

    public function customerSubscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }
}
