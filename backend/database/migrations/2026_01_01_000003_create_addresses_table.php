<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Bảng địa chỉ giao hàng của khách.
 *           Một khách có thể có nhiều địa chỉ, mỗi địa chỉ có cờ `is_default`.
 *           Dùng khi đặt hàng và khi tính phí giao hàng.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('receiver_name', 100);
            $table->string('phone', 20);
            $table->string('province', 100);   // Tỉnh / Thành phố
            $table->string('district', 100);   // Quận / Huyện
            $table->string('ward', 100);       // Phường / Xã
            $table->string('detail_address', 255); // Số nhà, tên đường
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
