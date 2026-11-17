<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Bảng YÊU CẦU KHIẾU NẠI / ĐỔI TRẢ.
 *           Khách chọn một đơn đã giao, chọn lý do, BẮT BUỘC đính kèm
 *           ít nhất 1 ảnh/video minh chứng (lưu trên Cloudinary).
 *           Sales duyệt và chọn cách xử lý: hoàn tiền hoặc giao bù.
 *
 *           Quy tắc nghiệp vụ theo Tuần 2: chỉ nhận khiếu nại trong vòng
 *           24 giờ kể từ lúc giao hàng (kiểm tra ở ComplaintService).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->string('complaint_code', 30)->unique();

            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('reason', ['damaged', 'rotten', 'missing_item', 'wrong_item', 'other']);

            $table->text('description')->nullable();

            // Mảng URL ảnh/video minh chứng. Bắt buộc phải có ít nhất 1 phần tử.
            $table->json('evidence_media');

            // pending        = chờ Sales tiếp nhận
            // approved_refund = duyệt hoàn tiền
            // approved_replace= duyệt giao bù
            // rejected       = từ chối
            $table->enum('status', ['pending', 'approved_refund', 'approved_replace', 'rejected'])
                ->default('pending');

            $table->decimal('refund_amount', 12, 0)->default(0);
            $table->string('staff_note', 500)->nullable();

            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
