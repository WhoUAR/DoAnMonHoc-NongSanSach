<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Bảng SỔ ĐIỂM TÍCH LUỸ (Loyalty Ledger).
 *           Mỗi lần khách mua hàng thành công, cộng điểm.
 *           Mỗi lần khách dùng điểm trừ vào đơn, ghi một dòng âm.
 *           Nhờ bảng này mà luôn đối soát được:
 *              users.loyalty_points  ==  SUM(loyalty_transactions.points)
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // earn   = cộng điểm sau khi đơn giao thành công
            // redeem = dùng điểm trừ tiền đơn
            // adjust = Admin điều chỉnh tay
            $table->enum('type', ['earn', 'redeem', 'adjust']);

            // Số dương khi cộng, số âm khi trừ
            $table->integer('points');

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('description', 255)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
