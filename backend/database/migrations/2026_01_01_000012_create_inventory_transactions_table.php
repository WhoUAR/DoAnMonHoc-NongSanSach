<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Bảng THẺ KHO (Inventory Transaction) - sổ cái ghi lại MỌI biến động
 *           số lượng của từng Lô hàng. Không có dòng nào bị sửa hay xoá,
 *           nhờ vậy giảng viên có thể truy vết "vì sao lô này còn 3kg".
 *
 *           Các loại phiếu:
 *             import       - nhập kho khi thu hoạch
 *             export_order - xuất kho để giao cho đơn hàng (do FEFO sinh ra)
 *             dispose      - xuất hủy hàng hỏng / hao hụt nội bộ
 *             adjust       - điều chỉnh sau kiểm kê
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();

            $table->enum('type', ['import', 'export_order', 'dispose', 'adjust']);

            // Số lượng luôn dương, chiều tăng/giảm do cột `type` quyết định
            $table->decimal('quantity', 12, 3);

            // Số lượng còn lại của lô sau khi thực hiện phiếu này
            $table->decimal('balance_after', 12, 3);

            // Lý do hủy hàng: 'damaged', 'expired', 'overripe', 'other'
            $table->string('reason', 50)->nullable();
            $table->string('note', 500)->nullable();

            // Nếu là xuất cho đơn hàng thì lưu id đơn để tra ngược
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            $table->index(['batch_id', 'type']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
