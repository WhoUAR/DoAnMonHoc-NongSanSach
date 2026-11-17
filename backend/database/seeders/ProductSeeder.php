<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thanh Liêm (0306241214)
 * CHỨC NĂNG: Seeder tạo danh mục và sản phẩm nông sản mẫu.
 *           Mỗi sản phẩm có số ngày bảo quản riêng, đây là dữ liệu đầu vào
 *           để tự tính hạn sử dụng khi Nhân viên kho thu hoạch lô hàng.
 */

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Rau ăn lá', 'slug' => 'rau-an-la', 'description' => 'Cải, muống, mồng tơi...', 'sort_order' => 1],
            ['name' => 'Củ quả',    'slug' => 'cu-qua',    'description' => 'Cà rốt, khoai tây, cà chua...', 'sort_order' => 2],
            ['name' => 'Trái cây',  'slug' => 'trai-cay',  'description' => 'Trái cây theo mùa', 'sort_order' => 3],
            ['name' => 'Gia vị',    'slug' => 'gia-vi',    'description' => 'Hành, tỏi, ớt, sả...', 'sort_order' => 4],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }

        $categoryIds = Category::pluck('id', 'slug');

        $products = [
            [
                'category_id' => $categoryIds['rau-an-la'],
                'name'        => 'Rau muống hữu cơ',
                'sku'         => 'RAU-MUONG',
                'unit'        => 'bó',
                'price'       => 12000,
                'origin'      => 'Nông trại Củ Chi',
                'shelf_life_days' => 3,
                'certification_summary' => 'VietGAP',
                'description' => 'Rau muống trồng theo chuẩn VietGAP, không thuốc trừ sâu hóa học.',
            ],
            [
                'category_id' => $categoryIds['rau-an-la'],
                'name'        => 'Cải ngọt',
                'sku'         => 'CAI-NGOT',
                'unit'        => 'kg',
                'price'       => 25000,
                'origin'      => 'Nông trại Củ Chi',
                'shelf_life_days' => 4,
                'certification_summary' => 'VietGAP',
                'description' => 'Cải ngọt non, thu hoạch trong ngày.',
            ],
            [
                'category_id' => $categoryIds['cu-qua'],
                'name'        => 'Cà chua bi',
                'sku'         => 'CA-CHUA-BI',
                'unit'        => 'kg',
                'price'       => 45000,
                'sale_price'  => 39000,
                'origin'      => 'Nông trại Củ Chi',
                'shelf_life_days' => 7,
                'certification_summary' => 'VietGAP',
                'description' => 'Cà chua bi ngọt, trồng trong nhà lưới.',
            ],
            [
                'category_id' => $categoryIds['cu-qua'],
                'name'        => 'Cà rốt Đà Lạt',
                'sku'         => 'CA-ROT',
                'unit'        => 'kg',
                'price'       => 32000,
                'origin'      => 'Đà Lạt',
                'shelf_life_days' => 14,
                'certification_summary' => 'VietGAP',
                'description' => 'Cà rốt tươi, bảo quản lạnh.',
            ],
            [
                'category_id' => $categoryIds['trai-cay'],
                'name'        => 'Dưa leo baby',
                'sku'         => 'DUA-LEO',
                'unit'        => 'kg',
                'price'       => 28000,
                'origin'      => 'Nông trại Củ Chi',
                'shelf_life_days' => 5,
                'description' => 'Dưa leo giòn, ăn sống được.',
            ],
            [
                'category_id' => $categoryIds['gia-vi'],
                'name'        => 'Hành lá',
                'sku'         => 'HANH-LA',
                'unit'        => 'bó',
                'price'       => 8000,
                'origin'      => 'Nông trại Củ Chi',
                'shelf_life_days' => 3,
                'description' => 'Hành lá tươi thơm.',
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                array_merge($product, [
                    'slug'      => Str::slug($product['name']),
                    'is_active' => true,
                ]),
            );
        }
    }
}
