<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Bảng LÔ HÀNG (Batch) - trái tim của toàn bộ nghiệp vụ kho.
 *           Khác với cách quản lý tồn kho "số lượng tổng" thông thường,
 *           mỗi lần thu hoạch tạo ra MỘT LÔ riêng, có:
 *             - ngày gieo trồng (planted_date)
 *             - ngày thu hoạch (harvest_date)
 *             - hạn sử dụng  (expiry_date) => dùng để chạy thuật toán FEFO
 *             - số lượng nhập, số lượng còn lại
 *             - mã truy xuất (traceability_token) => sinh ra link QR
 *
 *           Nhân viên nông trại tạo lô từ lúc gieo trồng (status = growing),
 *           đến khi thu hoạch thì chuyển sang available và bắt đầu bán được.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnUpdate()->restrictOnDelete();

            // Mã lô người dùng nhìn thấy, ví dụ: LO-20261115-001
            $table->string('batch_code', 40)->unique();

            // Mã bí mật dùng để tạo link QR truy xuất nguồn gốc
            $table->string('traceability_token', 64)->unique();

            $table->foreignId('farm_staff_id')->nullable()
                ->comment('Nhân viên nông trại phụ trách lô này')
                ->constrained('users')->nullOnDelete();

            $table->date('planted_date');
            $table->date('harvest_date')->nullable();
            $table->date('expiry_date')->nullable();

            // Nông sản bán theo kg nên phải dùng số thập phân (3 số lẻ: gam)
            $table->decimal('quantity_imported', 12, 3)->default(0);
            $table->decimal('quantity_available', 12, 3)->default(0);

            // growing   = đang canh tác, chưa thu hoạch
            // available = đã thu hoạch, đang bán
            // depleted  = đã bán hết
            // expired   = quá hạn sử dụng
            // disposed  = đã xuất hủy
            $table->enum('status', ['growing', 'available', 'depleted', 'expired', 'disposed'])
                ->default('growing');

            $table->string('quality_note', 255)->nullable();

            $table->timestamps();

            // Index phục vụ câu truy vấn FEFO: lọc theo sản phẩm, sắp theo hạn dùng
            $table->index(['product_id', 'status', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
