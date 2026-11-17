<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Bảng chi tiết của một Gói định kỳ.
 *           Cho biết gói đó gồm những sản phẩm nào và mỗi lần giao bao nhiêu.
 *           Ví dụ: Gói Gia Đình = 2kg rau muống + 1kg cà chua + 1 bó cải.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            $table->decimal('quantity', 10, 3);
            $table->timestamps();

            $table->unique(['subscription_plan_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_items');
    }
};
