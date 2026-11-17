<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Bảng ĐĂNG KÝ GÓI ĐỊNH KỲ của khách.
 *           Khách trả trước một tháng, hệ thống ghi nhận:
 *             - gói nào, bắt đầu/ kết thúc ngày nào
 *             - giao vào những thứ nào (sao chép từ gói, cho phép khách đổi)
 *             - địa chỉ giao và trạng thái
 *           Mỗi ngày, một lệnh (Artisan Command) sẽ quét bảng này để tự
 *           sinh đơn hàng con cho đúng ngày giao.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_code', 30)->unique();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->restrictOnDelete();

            $table->date('start_date');
            $table->date('end_date');

            // Danh sách thứ giao hàng của riêng khách này, ví dụ [3, 6]
            $table->json('delivery_weekdays');

            $table->string('receiver_name', 100);
            $table->string('receiver_phone', 20);
            $table->string('shipping_address', 255);

            $table->decimal('total_paid', 12, 0)->default(0);
            $table->enum('payment_method', ['cod', 'vnpay'])->default('vnpay');
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');

            // active    = đang chạy, tự sinh đơn
            // paused    = tạm dừng (khách yêu cầu)
            // completed = đã hết kỳ
            // cancelled = huỷ giữa kỳ
            $table->enum('status', ['active', 'paused', 'completed', 'cancelled'])->default('active');

            $table->date('next_delivery_date')->nullable();
            $table->string('note', 500)->nullable();

            $table->timestamps();

            $table->index(['status', 'next_delivery_date']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};
