<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thanh Liêm (0306241214)
 * CHỨC NĂNG: Controller truy xuất nguồn gốc qua mã QR (CÔNG KHAI).
 *
 *   show()   : Khách quét QR -> mở link /traceability/{token}.
 *              Trả về toàn bộ hồ sơ của lô: sản phẩm, nông trại,
 *              ngày gieo trồng, ngày thu hoạch, hạn sử dụng,
 *              dòng thời gian nhật ký canh tác kèm ảnh/video,
 *              chứng nhận và người phụ trách.
 *
 *   qrCode() : Sinh ảnh QR dạng SVG cho lô (dùng cho màn hình in tem).
 *
 *   Không cần JWT vì đây là trang công khai - người tiêu dùng chỉ cần
 *   quét mã là xem được, đúng mục tiêu minh bạch của đề tài.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\TraceabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TraceabilityController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TraceabilityService $traceabilityService,
    ) {
    }

    /**
     * HỒ SƠ TRUY XUẤT NGUỒN GỐC CỦA MỘT LÔ.
     */
    public function show(string $token): JsonResponse
    {
        $batch = $this->traceabilityService->findByToken($token);

        if ($batch === null) {
            return $this->error('Mã QR không hợp lệ hoặc lô hàng không còn tồn tại.', 404);
        }

        $product = $batch->product;

        return $this->success([
            'batch' => [
                'batch_code'      => $batch->batch_code,
                'planted_date'    => $batch->planted_date?->format('d/m/Y'),
                'harvest_date'    => $batch->harvest_date?->format('d/m/Y'),
                'expiry_date'     => $batch->expiry_date?->format('d/m/Y'),
                'days_until_expiry' => $batch->daysUntilExpiry(),
                'quality_note'    => $batch->quality_note,
                'status'          => $batch->status,
            ],
            'product' => [
                'name'        => $product?->name,
                'unit'        => $product?->unit,
                'origin'      => $product?->origin,
                'thumbnail'   => $product?->thumbnail_url,
                'certification' => $product?->certification_summary,
                'category'    => $product?->category?->name,
            ],
            'farmer' => [
                'full_name' => $batch->farmStaff?->full_name,
            ],
            // Dòng thời gian nhật ký canh tác - phần quan trọng nhất của trang này.
            'timeline' => $batch->farmingLogs->map(fn ($log) => [
                'activity_type' => $log->activity_type,
                'activity_date' => $log->activity_date?->format('d/m/Y'),
                'title'         => $log->title,
                'description'   => $log->description,
                'media'         => $log->media ?? [],
                'author'        => $log->author?->full_name,
            ])->toArray(),
        ], 'Lấy thông tin truy xuất nguồn gốc thành công.');
    }

    /**
     * SINH ẢNH QR DẠNG SVG.
     * Frontend nhận chuỗi SVG này để hiển thị hoặc in tem.
     */
    public function qrCode(string $token): Response
    {
        $batch = $this->traceabilityService->findByToken($token);

        if ($batch === null) {
            return response('Không tìm thấy lô hàng.', 404);
        }

        $svg = $this->traceabilityService->qrSvg($batch);

        return response($svg, 200, [
            'Content-Type'        => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="qr-' . $batch->batch_code . '.svg"',
        ]);
    }
}
