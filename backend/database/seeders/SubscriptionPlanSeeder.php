<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Seeder tạo 2 gói rau định kỳ mẫu.
 *           Gói Gia Đình giao Thứ 3 và Thứ 6 (đúng ví dụ trong tài liệu).
 */

namespace Database\Seeders;

use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionPlanItem;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        // Gói 1: Rau gia đình, giao Thứ 3 và Thứ 6 hằng tuần.
        $familyPlan = SubscriptionPlan::updateOrCreate(
            ['slug' => 'goi-rau-gia-dinh'],
            [
                'name'              => 'Gói Rau Gia Đình',
                'description'       => 'Gói rau củ tươi cho gia đình 3-4 người, giao Thứ 3 và Thứ 6 hằng tuần trong 1 tháng.',
                'price'             => 450000,
                'duration_days'     => 30,
                'delivery_weekdays' => [3, 6],
                'is_active'         => true,
            ],
        );

        // Gói 2: Gói văn phòng, giao mỗi Thứ 2.
        $officePlan = SubscriptionPlan::updateOrCreate(
            ['slug' => 'goi-rau-van-phong'],
            [
                'name'              => 'Gói Rau Văn Phòng',
                'description'       => 'Gói rau sạch nhỏ gọn, giao mỗi Thứ 2 hằng tuần, phù hợp người độc thân.',
                'price'             => 280000,
                'duration_days'     => 30,
                'delivery_weekdays' => [2],
                'is_active'         => true,
            ],
        );

        $this->attachItems($familyPlan, [
            'RAU-MUONG'  => 2,
            'CA-CHUA-BI' => 1,
            'CAI-NGOT'   => 1,
        ]);

        $this->attachItems($officePlan, [
            'RAU-MUONG' => 1,
            'HANH-LA'   => 1,
            'DUA-LEO'   => 0.5,
        ]);
    }

    /**
     * Gắn danh sách sản phẩm vào một gói.
     *
     * @param  array<string, float>  $itemsBySku  [mã sản phẩm => số lượng mỗi lần giao]
     */
    private function attachItems(SubscriptionPlan $plan, array $itemsBySku): void
    {
        foreach ($itemsBySku as $sku => $quantity) {
            $product = Product::where('sku', $sku)->first();

            if ($product === null) {
                continue;
            }

            SubscriptionPlanItem::updateOrCreate(
                [
                    'subscription_plan_id' => $plan->id,
                    'product_id'           => $product->id,
                ],
                ['quantity' => $quantity],
            );
        }
    }
}
