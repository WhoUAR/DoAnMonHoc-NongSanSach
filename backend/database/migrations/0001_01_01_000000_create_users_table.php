<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Tạo bảng người dùng cho toàn hệ thống.
 *           Một bảng duy nhất chứa cả Khách hàng, Nhân viên nông trại,
 *           Nhân viên kho, Nhân viên bán hàng và Quản trị viên.
 *           Phân biệt nhau bằng cột `role` (đã có Middleware kiểm tra quyền).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('full_name', 100);
            $table->string('email', 150)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('password');

            // 5 vai trò đúng theo tài liệu phân tích hệ thống
            $table->enum('role', [
                'customer',        // Khách hàng
                'farm_staff',      // Nhân viên nông trại
                'warehouse_staff', // Nhân viên kho
                'sales',           // Nhân viên bán hàng / CSKH
                'admin',           // Quản trị viên
            ])->default('customer');

            // Điểm tích luỹ hiện tại (bảng loyalty_transactions là sổ chi tiết)
            $table->unsignedInteger('loyalty_points')->default(0);
            $table->enum('status', ['active', 'locked'])->default('active');
            $table->string('avatar_url', 500)->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
