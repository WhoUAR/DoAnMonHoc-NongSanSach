<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Bảng CẤU HÌNH CẢNH BÁO XẢ HÀNG (Flash Sale Alert).
 *           Admin đặt ngưỡng: "lô nào còn <= 2 ngày là hết hạn thì cảnh báo".
 *           Hệ thống quét định kỳ và đẩy thông báo đỏ cho Sales.
 *           Chỉ cần một dòng cấu hình duy nhất nên dùng bảng key-value đơn giản.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('value', 500);
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
