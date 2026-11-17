<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Seeder tạo 5 tài khoản mẫu, mỗi vai trò một tài khoản.
 *           Dùng để đăng nhập thử khi bảo vệ đồ án.
 *           Mật khẩu chung: 12345678
 */

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'full_name' => 'Trần Quốc Khánh',
                'email'     => 'admin@nongsan.vn',
                'phone'     => '0901234561',
                'role'      => User::ROLE_ADMIN,
            ],
            [
                'full_name' => 'Nguyễn Văn Đoàn',
                'email'     => 'kho@nongsan.vn',
                'phone'     => '0901234562',
                'role'      => User::ROLE_WAREHOUSE_STAFF,
            ],
            [
                'full_name' => 'Trần Thanh An',
                'email'     => 'sales@nongsan.vn',
                'phone'     => '0901234563',
                'role'      => User::ROLE_SALES,
            ],
            [
                'full_name' => 'Nguyễn Thanh Liêm',
                'email'     => 'nongtrai@nongsan.vn',
                'phone'     => '0901234564',
                'role'      => User::ROLE_FARM_STAFF,
            ],
            [
                'full_name' => 'Đào Quốc Đạt',
                'email'     => 'khachhang@nongsan.vn',
                'phone'     => '0901234565',
                'role'      => User::ROLE_CUSTOMER,
                'loyalty_points' => 120,
            ],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'full_name'      => $account['full_name'],
                    'phone'          => $account['phone'],
                    'password'       => '12345678', // Model tự băm bằng Bcrypt
                    'role'           => $account['role'],
                    'status'         => 'active',
                    'loyalty_points' => $account['loyalty_points'] ?? 0,
                ],
            );
        }
    }
}
