<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thanh Liêm (0306241214)
 * CHỨC NĂNG: Bảng NHẬT KÝ CANH TÁC (Farming Log).
 *           Mỗi dòng là một mốc trong dòng thời gian của một Lô hàng:
 *           gieo hạt, tưới nước, bón phân, phun thuốc sinh học, thu hoạch...
 *           Ảnh/video được upload lên Cloudinary và lưu thành mảng JSON.
 *           Toàn bộ nhật ký này chính là nội dung hiển thị khi khách quét mã QR.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farming_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();

            // Người ghi nhật ký (Nhân viên nông trại)
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            $table->enum('activity_type', [
                'planting',      // Gieo hạt / trồng cây
                'watering',      // Tưới nước
                'fertilizing',   // Bón phân
                'spraying',      // Phun thuốc sinh học
                'inspection',    // Kiểm tra sâu bệnh
                'harvesting',    // Thu hoạch
            ]);

            $table->date('activity_date');
            $table->string('title', 150);
            $table->text('description')->nullable();

            // Ví dụ: ["https://res.cloudinary.com/.../anh1.jpg", "..."]
            $table->json('media')->nullable();

            $table->timestamps();

            $table->index(['batch_id', 'activity_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farming_logs');
    }
};
