<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Bảng PHÂN BỔ LÔ CHO ĐƠN HÀNG.
 *           Đây là bằng chứng của thuật toán FEFO.
 *           Khi duyệt đơn, hệ thống có thể phải lấy hàng từ NHIỀU lô khác nhau
 *           (Lô 1 còn 2kg + Lô 2 lấy 3kg = đủ 5kg khách đặt).
 *           Mỗi dòng trong bảng này ghi lại: đơn nào, sản phẩm nào,
 *           lấy ở lô nào, bao nhiêu kg.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_batch_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->restrictOnDelete();

            $table->decimal('quantity', 10, 3);

            $table->timestamps();

            $table->index(['order_id', 'batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_batch_allocations');
    }
};
