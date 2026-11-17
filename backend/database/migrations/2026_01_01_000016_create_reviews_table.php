<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Bảng đánh giá sản phẩm của khách sau khi đã nhận hàng.
 *           Mỗi đơn chỉ được đánh giá một lần cho mỗi sản phẩm
 *           (ràng buộc unique bên dưới).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->unsignedTinyInteger('rating');  // 1 đến 5 sao
            $table->string('comment', 1000)->nullable();
            $table->json('media')->nullable();      // Ảnh khách chụp

            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'user_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
