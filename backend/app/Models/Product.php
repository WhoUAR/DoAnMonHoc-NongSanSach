<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thanh Liêm (0306241214)
 * CHỨC NĂNG: Model Sản phẩm nông sản.
 *           - `currentPrice()`: trả về giá bán thực tế (ưu tiên sale_price).
 *           - `availableBatches()`: các lô còn hàng và chưa hết hạn,
 *             sắp xếp theo hạn dùng tăng dần - đúng thứ tự FEFO.
 *           - `totalAvailableQuantity()`: tổng tồn kho khả dụng của sản phẩm,
 *             cộng từ tất cả các lô. Đây là con số hiển thị "Còn hàng" trên web.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'unit',
        'price',
        'sale_price',
        'description',
        'thumbnail_url',
        'gallery',
        'origin',
        'certification_summary',
        'shelf_life_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gallery'         => 'array',
            'price'           => 'decimal:0',
            'sale_price'      => 'decimal:0',
            'is_active'       => 'boolean',
            'shelf_life_days' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Giá khách phải trả. Có giá khuyến mãi thì dùng giá khuyến mãi.
     */
    public function currentPrice(): float
    {
        return (float) ($this->sale_price ?? $this->price);
    }

    /**
     * Danh sách lô còn bán được, xếp theo hạn sử dụng gần nhất trước.
     * Đây chính là thứ tự mà thuật toán FEFO sẽ lấy hàng.
     */
    public function availableBatches()
    {
        return $this->batches()
            ->where('status', Batch::STATUS_AVAILABLE)
            ->where('quantity_available', '>', 0)
            ->whereDate('expiry_date', '>=', now()->toDateString())
            ->orderBy('expiry_date')
            ->orderBy('id');
    }

    /**
     * Tổng số lượng còn bán được của sản phẩm (cộng tất cả các lô).
     */
    public function totalAvailableQuantity(): float
    {
        return (float) $this->availableBatches()->sum('quantity_available');
    }
}
