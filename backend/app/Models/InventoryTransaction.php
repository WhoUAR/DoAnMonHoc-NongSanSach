<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Model Thẻ kho - sổ cái biến động số lượng của từng Lô.
 *           Mọi thay đổi tồn kho (nhập, xuất bán, xuất hủy, điều chỉnh)
 *           đều phải đi qua bảng này để giữ khả năng truy vết.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    public const TYPE_IMPORT       = 'import';
    public const TYPE_EXPORT_ORDER = 'export_order';
    public const TYPE_DISPOSE      = 'dispose';
    public const TYPE_ADJUST       = 'adjust';

    protected $fillable = [
        'batch_id',
        'type',
        'quantity',
        'balance_after',
        'reason',
        'note',
        'order_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity'      => 'decimal:3',
            'balance_after' => 'decimal:3',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
