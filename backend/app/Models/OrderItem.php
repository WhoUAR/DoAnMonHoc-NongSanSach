<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Model Chi tiết đơn hàng.
 *           Lưu tên và giá sản phẩm tại thời điểm mua để hoá đơn cũ
 *           không bị thay đổi khi nông trại cập nhật giá mới.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_unit',
        'unit_price',
        'quantity',
        'line_total',
        'quantity_prepared',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'        => 'decimal:0',
            'line_total'        => 'decimal:0',
            'quantity'          => 'decimal:3',
            'quantity_prepared' => 'decimal:3',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(OrderBatchAllocation::class);
    }
}
