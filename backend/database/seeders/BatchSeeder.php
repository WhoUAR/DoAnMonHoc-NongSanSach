<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Seeder tạo LÔ HÀNG MẪU để demo thuật toán FEFO.
 *
 *   Dữ liệu được dựng đúng theo ví dụ trong tài liệu Tuần 2:
 *     Cà chua bi - Lô 1: 2kg, hạn còn 2 ngày  (sắp hết hạn)
 *     Cà chua bi - Lô 2: 10kg, hạn còn 7 ngày
 *   Khách đặt 5kg => hệ thống phải lấy 2kg ở Lô 1 và 3kg ở Lô 2.
 *
 *   Ngoài ra còn một lô "sắp hết hạn 2 ngày" để minh hoạ cảnh báo xả hàng.
 */

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\FarmingLog;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use App\Services\TraceabilityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BatchSeeder extends Seeder
{
    public function run(): void
    {
        $farmStaff = User::where('role', User::ROLE_FARM_STAFF)->first();
        $warehouse = User::where('role', User::ROLE_WAREHOUSE_STAFF)->first();
        $traceabilityService = app(TraceabilityService::class);

        // Cấu hình từng lô mẫu: [sku sản phẩm, số lượng, số ngày còn lại đến hạn]
        $batchTemplates = [
            // Cà chua bi: lô cận hạn 2kg + lô còn nhiều 10kg => demo FEFO chia lô
            ['sku' => 'CA-CHUA-BI', 'quantity' => 2,   'days_until_expiry' => 2],
            ['sku' => 'CA-CHUA-BI', 'quantity' => 10,  'days_until_expiry' => 7],

            // Các sản phẩm khác để trang danh sách có hàng
            ['sku' => 'RAU-MUONG',  'quantity' => 30,  'days_until_expiry' => 3],
            ['sku' => 'CAI-NGOT',   'quantity' => 18,  'days_until_expiry' => 4],
            ['sku' => 'CA-ROT',     'quantity' => 25,  'days_until_expiry' => 12],
            ['sku' => 'DUA-LEO',    'quantity' => 15,  'days_until_expiry' => 5],
            ['sku' => 'HANH-LA',    'quantity' => 40,  'days_until_expiry' => 3],
        ];

        foreach ($batchTemplates as $index => $template) {
            $product = Product::where('sku', $template['sku'])->first();

            if ($product === null) {
                continue;
            }

            $expiryDate  = now()->addDays($template['days_until_expiry'])->startOfDay();
            $harvestDate = $expiryDate->copy()->subDays($product->shelf_life_days);
            $plantedDate = $harvestDate->copy()->subDays(30);

            $batch = Batch::create([
                'product_id'         => $product->id,
                'batch_code'         => 'LO-' . now()->format('Ymd') . '-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'traceability_token' => $traceabilityService->generateToken(),
                'farm_staff_id'      => $farmStaff?->id,
                'planted_date'       => $plantedDate,
                'harvest_date'       => $harvestDate,
                'expiry_date'        => $expiryDate,
                'quantity_imported'  => $template['quantity'],
                'quantity_available' => $template['quantity'],
                'status'             => Batch::STATUS_AVAILABLE,
                'quality_note'       => 'Lô mẫu phục vụ demo đồ án.',
            ]);

            // Ghi thẻ kho nhập hàng.
            InventoryTransaction::create([
                'batch_id'      => $batch->id,
                'type'          => InventoryTransaction::TYPE_IMPORT,
                'quantity'      => $template['quantity'],
                'balance_after' => $template['quantity'],
                'created_by'    => $warehouse?->id ?? $farmStaff?->id,
                'note'          => 'Nhập kho từ dữ liệu mẫu.',
            ]);

            // Chỉ tạo nhật ký canh tác chi tiết cho 2 lô đầu tiên
            // để trang truy xuất nguồn gốc có dòng thời gian đẹp khi demo.
            if ($index < 2) {
                $this->createFarmingLogs($batch, $farmStaff, $plantedDate);
            }
        }
    }

    /**
     * Tạo dòng thời gian nhật ký canh tác mẫu cho một lô.
     */
    private function createFarmingLogs(Batch $batch, ?User $farmStaff, \Carbon\Carbon $plantedDate): void
    {
        if ($farmStaff === null) {
            return;
        }

        $logs = [
            [
                'activity_type' => 'planting',
                'activity_date' => $plantedDate->copy(),
                'title'         => 'Gieo hạt giống',
                'description'   => 'Gieo hạt trong nhà lưới, giá thể hữu cơ đã khử trùng.',
            ],
            [
                'activity_type' => 'watering',
                'activity_date' => $plantedDate->copy()->addDays(5),
                'title'         => 'Tưới nước hệ thống nhỏ giọt',
                'description'   => 'Tưới 2 lần/ngày, mỗi lần 20 phút.',
            ],
            [
                'activity_type' => 'fertilizing',
                'activity_date' => $plantedDate->copy()->addDays(12),
                'title'         => 'Bón phân hữu cơ',
                'description'   => 'Bón phân trùn quế, không dùng phân hóa học.',
            ],
            [
                'activity_type' => 'inspection',
                'activity_date' => $plantedDate->copy()->addDays(20),
                'title'         => 'Kiểm tra sâu bệnh',
                'description'   => 'Không phát hiện sâu bệnh, cây sinh trưởng tốt.',
            ],
            [
                'activity_type' => 'harvesting',
                'activity_date' => $batch->harvest_date?->copy() ?? now(),
                'title'         => 'Thu hoạch',
                'description'   => 'Thu hoạch vào sáng sớm, đóng gói ngay để giữ độ tươi.',
            ],
        ];

        foreach ($logs as $log) {
            FarmingLog::create([
                'batch_id'      => $batch->id,
                'created_by'    => $farmStaff->id,
                'activity_type' => $log['activity_type'],
                'activity_date' => $log['activity_date'],
                'title'         => $log['title'],
                'description'   => $log['description'],
                'media'         => [],
            ]);
        }
    }
}
