<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Seeder chạy toàn bộ dữ liệu mẫu theo đúng thứ tự phụ thuộc.
 *
 *   Chạy: php artisan db:seed
 *   Đặt lại toàn bộ: php artisan migrate:fresh --seed
 */

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,            // Phải chạy đầu tiên (lô hàng cần nhân viên)
            ProductSeeder::class,         // Danh mục + sản phẩm
            BatchSeeder::class,           // Lô hàng + nhật ký canh tác
            SubscriptionPlanSeeder::class, // Gói định kỳ
        ]);

        $this->seedSystemSettings();
    }

    /**
     * Cấu hình mặc định của hệ thống.
     */
    private function seedSystemSettings(): void
    {
        $settings = [
            ['key' => 'flash_sale.warning_days', 'value' => '3',     'description' => 'Số ngày trước hạn để cảnh báo xả hàng'],
            ['key' => 'shipping.base_fee',       'value' => '15000', 'description' => 'Phí giao hàng 3km đầu'],
            ['key' => 'shipping.fee_per_km',     'value' => '4000',  'description' => 'Phí mỗi km tiếp theo'],
            ['key' => 'shipping.free_threshold', 'value' => '300000', 'description' => 'Đơn từ mức này được miễn phí giao hàng'],
            ['key' => 'complaint.window_hours',  'value' => '24',    'description' => 'Số giờ được phép khiếu nại sau khi nhận hàng'],
            ['key' => 'loyalty.point_value',     'value' => '1000',  'description' => 'Giá trị quy đổi của 1 điểm (VNĐ)'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
