<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Model Lô hàng (Batch) - đơn vị quản lý tồn kho của hệ thống.
 *           - Hằng số trạng thái để code không phải gõ tay chuỗi.
 *           - `scopeAvailable()`: điều kiện "lô bán được" dùng lại nhiều nơi.
 *           - `daysUntilExpiry()`: số ngày còn lại đến hạn, dùng cho
 *             cảnh báo xả hàng và tính % hao hụt.
 *           - `scopeFefoOrder()`: sắp xếp theo hạn dùng - trái tim của FEFO.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    use HasFactory;

    public const STATUS_GROWING   = 'growing';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_DEPLETED  = 'depleted';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_DISPOSED  = 'disposed';

    protected $fillable = [
        'product_id',
        'batch_code',
        'traceability_token',
        'farm_staff_id',
        'planted_date',
        'harvest_date',
        'expiry_date',
        'quantity_imported',
        'quantity_available',
        'status',
        'quality_note',
    ];

    protected function casts(): array
    {
        return [
            'planted_date'       => 'date',
            'harvest_date'       => 'date',
            'expiry_date'        => 'date',
            'quantity_imported'  => 'decimal:3',
            'quantity_available' => 'decimal:3',
        ];
    }

    // ------------------------------------------------------------------
    // Quan hệ
    // ------------------------------------------------------------------

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function farmStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'farm_staff_id');
    }

    public function farmingLogs(): HasMany
    {
        return $this->hasMany(FarmingLog::class)->orderBy('activity_date');
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    // ------------------------------------------------------------------
    // Scope (bộ lọc dùng lại) và hàm tiện ích
    // ------------------------------------------------------------------

    /**
     * Lô đang bán được: đã thu hoạch, còn hàng, chưa quá hạn.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_AVAILABLE)
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '>=', now()->toDateString());
    }

    /**
     * Sắp xếp FEFO: hạn sử dụng gần nhất lên đầu.
     */
    public function scopeFefoOrder(Builder $query): Builder
    {
        return $query->orderBy('expiry_date')->orderBy('id');
    }

    /**
     * Số ngày còn lại đến hạn sử dụng (âm nếu đã quá hạn).
     */
    public function daysUntilExpiry(): int
    {
        if ($this->expiry_date === null) {
            return PHP_INT_MAX;
        }

        return (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
    }
}
