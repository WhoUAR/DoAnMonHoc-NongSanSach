<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Bảng GÓI RAU ĐỊNH KỲ (Subscription Plan).
 *           Ví dụ: "Gói Rau Gia Đình" 450.000đ/tháng, giao Thứ 3 & Thứ 6.
 *           Admin/Sales tạo các gói này, khách chọn một gói để đăng ký.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('image_url', 500)->nullable();

            $table->decimal('price', 12, 0);

            // Số ngày của một kỳ gói, ví dụ 30 ngày
            $table->unsignedSmallInteger('duration_days')->default(30);

            // Các thứ trong tuần giao hàng: 2 = Thứ 2, ..., 7 = Chủ nhật
            // Ví dụ [3, 6] nghĩa là giao Thứ 3 và Thứ 6
            $table->json('delivery_weekdays');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
