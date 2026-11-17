<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Controller quản lý LÔ HÀNG và TỒN KHO (dành cho Warehouse Staff).
 *
 *   index()        : danh sách lô, lọc theo sản phẩm / trạng thái,
 *                    sắp xếp FEFO (hạn gần nhất lên đầu).
 *   store()        : Nhân viên nông trại tạo lô mới (lúc gieo trồng).
 *   harvest()      : Đánh dấu lô đã thu hoạch -> nhập kho, bắt đầu bán được.
 *                    Hạn sử dụng = ngày thu hoạch + số ngày bảo quản của sản phẩm.
 *   dispose()      : Ghi nhận xuất hủy / hao hụt.
 *   expiringSoon() : Danh sách lô sắp hết hạn => cảnh báo đỏ cho Sales.
 *   transactions() : Thẻ kho của một lô (mọi biến động số lượng).
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\TraceabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BatchController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly TraceabilityService $traceabilityService,
    ) {
    }

    /**
     * DANH SÁCH LÔ HÀNG.
     */
    public function index(Request $request): JsonResponse
    {
        $batches = Batch::query()
            ->with('product:id,name,unit,thumbnail_url')
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->input('product_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $keyword = $request->string('search')->toString();
                $query->where('batch_code', 'like', "%{$keyword}%");
            })
            ->fefoOrder()
            ->paginate(max(1, (int) $request->input('per_page', 15)));

        // Bổ sung số ngày còn lại đến hạn để Frontend tô màu cảnh báo.
        $batches->getCollection()->transform(function (Batch $batch) {
            $batch->days_until_expiry = $batch->daysUntilExpiry();
            return $batch;
        });

        return $this->paginated($batches, 'Lấy danh sách lô hàng thành công.');
    }

    /**
     * TẠO LÔ HÀNG MỚI (lúc bắt đầu gieo trồng).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id'   => ['required', 'integer', 'exists:products,id'],
            'planted_date' => ['required', 'date', 'before_or_equal:today'],
            'quality_note' => ['nullable', 'string', 'max:255'],
        ], [
            'product_id.required'   => 'Vui lòng chọn sản phẩm cho lô hàng.',
            'planted_date.required' => 'Vui lòng nhập ngày gieo trồng.',
            'planted_date.before_or_equal' => 'Ngày gieo trồng không được ở tương lai.',
        ]);

        $batch = Batch::create([
            'product_id'         => $validated['product_id'],
            'batch_code'         => $this->generateBatchCode(),
            'traceability_token' => $this->traceabilityService->generateToken(),
            'farm_staff_id'      => Auth::guard('api')->id(),
            'planted_date'       => $validated['planted_date'],
            'quantity_imported'  => 0,
            'quantity_available' => 0,
            'status'             => Batch::STATUS_GROWING,
            'quality_note'       => $validated['quality_note'] ?? null,
        ]);

        return $this->success([
            'batch'    => $batch->load('product'),
            'qr_url'   => $this->traceabilityService->publicUrl($batch),
        ], 'Tạo lô hàng thành công.', 201);
    }

    /**
     * THU HOẠCH - chuyển lô sang trạng thái bán được.
     *
     * Hạn sử dụng = ngày thu hoạch + shelf_life_days của sản phẩm.
     * Đây chính là dữ liệu đầu vào của thuật toán FEFO.
     */
    public function harvest(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'harvest_date'      => ['required', 'date', 'before_or_equal:today'],
            'quantity_imported' => ['required', 'numeric', 'min:0.001'],
        ], [
            'harvest_date.required'      => 'Vui lòng nhập ngày thu hoạch.',
            'quantity_imported.required' => 'Vui lòng nhập số lượng thu hoạch.',
            'quantity_imported.min'      => 'Số lượng thu hoạch phải lớn hơn 0.',
        ]);

        $batch = Batch::with('product')->find($id);

        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        if ($batch->status !== Batch::STATUS_GROWING) {
            return $this->error('Chỉ có thể thu hoạch lô đang trong giai đoạn canh tác.', 422);
        }

        DB::transaction(function () use ($batch, $validated) {
            $harvestDate = \Carbon\Carbon::parse($validated['harvest_date']);

            $batch->harvest_date      = $harvestDate;
            $batch->expiry_date       = $harvestDate->copy()->addDays($batch->product->shelf_life_days);
            $batch->quantity_imported = $validated['quantity_imported'];
            $batch->quantity_available = $validated['quantity_imported'];
            $batch->save();

            // Ghi thẻ kho loại import và đổi trạng thái sang available.
            $this->inventoryService->importStock($batch, Auth::guard('api')->user());
        });

        return $this->success(
            $batch->fresh('product'),
            "Thu hoạch thành công. Hạn sử dụng: {$batch->expiry_date->format('d/m/Y')}."
        );
    }

    /**
     * XUẤT HỦY / HAO HỤT.
     */
    public function dispose(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason'   => ['required', 'in:damaged,expired,overripe,other'],
            'note'     => ['nullable', 'string', 'max:500'],
        ], [
            'quantity.required' => 'Vui lòng nhập số lượng hủy.',
            'reason.required'   => 'Vui lòng chọn lý do hủy hàng.',
        ]);

        $batch = Batch::find($id);

        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        try {
            $transaction = $this->inventoryService->disposeStock(
                $batch,
                (float) $validated['quantity'],
                $validated['reason'],
                Auth::guard('api')->user(),
                $validated['note'] ?? null,
            );
        } catch (RuntimeException $exception) {
            // Hủy quá tồn là lỗi người dùng, không phải lỗi máy chủ.
            return $this->error($exception->getMessage(), 422);
        }

        return $this->success([
            'transaction' => $transaction,
            'batch'       => $batch->fresh(),
        ], 'Đã ghi nhận xuất hủy hàng hóa.');
    }

    /**
     * CẢNH BÁO LÔ SẮP HẾT HẠN (Flash Sale).
     * Ngưỡng số ngày lấy từ bảng system_settings.
     */
    public function expiringSoon(Request $request): JsonResponse
    {
        $withinDays = (int) $request->input('days', 3);

        $batches = $this->inventoryService->batchesExpiringSoon($withinDays);

        return $this->success(
            $batches->map(fn (Batch $batch) => [
                'id'               => $batch->id,
                'batch_code'       => $batch->batch_code,
                'product_name'     => $batch->product?->name,
                'quantity_available' => (float) $batch->quantity_available,
                'expiry_date'      => $batch->expiry_date?->format('d/m/Y'),
                'days_until_expiry' => $batch->daysUntilExpiry(),
            ]),
            "Có {$batches->count()} lô sắp hết hạn trong {$withinDays} ngày tới."
        );
    }

    /**
     * THẺ KHO CỦA MỘT LÔ.
     */
    public function transactions(int $id): JsonResponse
    {
        $batch = Batch::with('product:id,name,unit')->find($id);

        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        $transactions = InventoryTransaction::query()
            ->with('author:id,full_name')
            ->where('batch_id', $id)
            ->latest()
            ->get();

        return $this->success([
            'batch'        => $batch,
            'transactions' => $transactions,
        ], 'Lấy thẻ kho thành công.');
    }

    /**
     * Sinh mã lô dạng LO-20261115-001.
     */
    private function generateBatchCode(): string
    {
        $prefix = 'LO-' . now()->format('Ymd') . '-';

        $todayCount = Batch::whereDate('created_at', now()->toDateString())->count() + 1;

        return $prefix . str_pad((string) $todayCount, 3, '0', STR_PAD_LEFT);
    }
}
