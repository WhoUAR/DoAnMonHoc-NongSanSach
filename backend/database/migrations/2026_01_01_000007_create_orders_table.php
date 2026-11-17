<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Bảng ĐƠN HÀNG.
 *           - Hỗ trợ cả khách vãng lai (user_id = null) và khách thành viên.
 *           - Lưu ảnh chụp thông tin người nhận tại thời điểm đặt hàng
 *             (không tham chiếu tới bảng addresses) để đơn cũ không bị đổi
 *             khi khách sửa địa chỉ.
 *           - Lưu tiền tạm tính, phí ship, giảm giá và tổng tiền.
 *           - `subscription_id` khác null nghĩa là đơn này do hệ thống
 *             tự sinh từ một Gói định kỳ.
 *           - `payment_status` tách riêng khỏi `status` vì một đơn có thể
 *             đã giao xong nhưng chưa thanh toán (COD).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 30)->unique();

            // Khách vãng lai (Guest) chưa đăng nhập => null
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()
                ->constrained('customer_subscriptions')->nullOnDelete();

            $table->string('receiver_name', 100);
            $table->string('receiver_phone', 20);
            $table->string('shipping_address', 255);
            $table->string('note', 500)->nullable();

            $table->decimal('subtotal', 12, 0);
            $table->decimal('shipping_fee', 12, 0)->default(0);
            $table->decimal('discount_amount', 12, 0)->default(0);
            $table->unsignedInteger('points_used')->default(0);
            $table->decimal('total_amount', 12, 0);

            $table->enum('payment_method', ['cod', 'vnpay']);
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid');

            // pending    = chờ Sales duyệt
            // confirmed  = Sales đã duyệt, đã trừ kho FEFO
            // shipping   = đang giao
            // delivered  = đã giao
            // cancelled  = đã huỷ
            $table->enum('status', ['pending', 'confirmed', 'shipping', 'delivered', 'cancelled'])
                ->default('pending');

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
