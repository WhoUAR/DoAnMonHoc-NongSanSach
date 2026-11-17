<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Controller báo cáo thống kê cho Admin.
 *
 *   overview()  : 4 con số tổng quan (doanh thu, đơn hàng, khách, hao hụt).
 *   revenue()   : doanh thu theo từng ngày trong khoảng thời gian.
 *   inventory() : tồn kho hiện tại theo sản phẩm và tình trạng hạn dùng.
 *   topProducts(): sản phẩm bán chạy nhất.
 *
 *   Đây là chức năng "Quản trị & Báo cáo thống kê" của nhóm trưởng.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {
    }

    /**
     * BỐN CON SỐ TỔNG QUAN CHO TRANG DASHBOARD.
     */
    public function overview(Request $request): JsonResponse
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to', now()->toDateString());

        // Doanh thu chỉ tính đơn đã giao thành công.
        $revenue = (float) Order::query()
            ->where('status', Order::STATUS_DELIVERED)
            ->whereBetween(DB::raw('DATE(delivered_at)'), [$from, $to])
            ->sum('total_amount');

        $orderCounts = Order::query()
            ->whereBetween(DB::raw('DATE(created_at)'), [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $customerCount = User::where('role', User::ROLE_CUSTOMER)->count();

        $loss = $this->inventoryService->lossRate($from, $to);

        return $this->success([
            'period'   => ['from' => $from, 'to' => $to],
            'revenue'  => $revenue,
            'orders'   => $orderCounts,
            'customers' => $customerCount,
            'loss'     => $loss,
        ], 'Lấy số liệu tổng quan thành công.');
    }

    /**
     * DOANH THU THEO NGÀY.
     */
    public function revenue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $validated['from'] ?? now()->subDays(30)->toDateString();
        $to   = $validated['to'] ?? now()->toDateString();

        $daily = Order::query()
            ->where('status', Order::STATUS_DELIVERED)
            ->whereBetween(DB::raw('DATE(delivered_at)'), [$from, $to])
            ->selectRaw('DATE(delivered_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue')
            ->groupBy(DB::raw('DATE(delivered_at)'))
            ->orderBy('date')
            ->get();

        return $this->success($daily, 'Lấy doanh thu theo ngày thành công.');
    }

    /**
     * TÌNH HÌNH TỒN KHO HIỆN TẠI.
     */
    public function inventory(): JsonResponse
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with('category:id,name')
            ->get()
            ->map(function (Product $product) {
                $availableBatches = $product->availableBatches()->get();

                return [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'unit'         => $product->unit,
                    'category'     => $product->category?->name,
                    'total_available' => round($availableBatches->sum('quantity_available'), 3),
                    'batch_count'  => $availableBatches->count(),
                    // Lô gần hết hạn nhất - cảnh báo cho Admin.
                    'nearest_expiry' => $availableBatches->first()?->expiry_date?->format('d/m/Y'),
                ];
            });

        $expiringSoon = Batch::query()
            ->available()
            ->with('product:id,name')
            ->whereDate('expiry_date', '<=', now()->addDays(3)->toDateString())
            ->fefoOrder()
            ->get()
            ->map(fn (Batch $batch) => [
                'batch_code'       => $batch->batch_code,
                'product_name'     => $batch->product?->name,
                'quantity_available' => (float) $batch->quantity_available,
                'expiry_date'      => $batch->expiry_date?->format('d/m/Y'),
                'days_until_expiry' => $batch->daysUntilExpiry(),
            ]);

        return $this->success([
            'products'      => $products,
            'expiring_soon' => $expiringSoon,
        ], 'Lấy tình hình tồn kho thành công.');
    }

    /**
     * SẢN PHẨM BÁN CHẠY.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->input('limit', 10), 50));

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', Order::STATUS_DELIVERED)
            ->selectRaw('order_items.product_id, order_items.product_name, SUM(order_items.quantity) as total_quantity, SUM(order_items.line_total) as total_revenue')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        return $this->success($topProducts, 'Lấy danh sách sản phẩm bán chạy thành công.');
    }
}
