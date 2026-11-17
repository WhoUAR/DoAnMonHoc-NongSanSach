<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Bảng GIAO DỊCH THANH TOÁN (VNPay).
 *           Ghi lại mã giao dịch do VNPay trả về để đối soát.
 *           - vnp_txn_ref: mã tham chiếu của mình gửi sang VNPay
 *           - vnp_transaction_no: mã giao dịch VNPay cấp
 *           - raw_response: toàn bộ dữ liệu VNPay trả về (JSON) để đối soát
 *           Với đơn thanh toán COD thì bảng này có một dòng method = 'cod'.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('customer_subscription_id')->nullable()
                ->constrained('customer_subscriptions')->nullOnDelete();

            $table->enum('method', ['cod', 'vnpay']);
            $table->decimal('amount', 12, 0);

            $table->string('vnp_txn_ref', 64)->nullable()->unique();
            $table->string('vnp_transaction_no', 64)->nullable();
            $table->string('vnp_response_code', 10)->nullable();
            $table->string('bank_code', 20)->nullable();

            $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
            $table->json('raw_response')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
