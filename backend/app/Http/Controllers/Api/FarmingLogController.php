<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thanh Liêm (0306241214)
 * CHỨC NĂNG: Controller nhật ký canh tác (dành cho Farm Staff).
 *
 *   store()   : thêm một mốc vào dòng thời gian của lô
 *               (gieo hạt, tưới nước, bón phân, thu hoạch...).
 *               Ảnh/video đã được upload lên Cloudinary từ trước,
 *               API này chỉ nhận danh sách URL.
 *   index()   : xem toàn bộ nhật ký của một lô.
 *   destroy() : xoá một mốc ghi nhầm (chỉ trong 24 giờ đầu).
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\FarmingLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FarmingLogController extends Controller
{
    use ApiResponse;

    /**
     * DANH SÁCH NHẬT KÝ CỦA MỘT LÔ.
     */
    public function index(int $batchId): JsonResponse
    {
        $batch = Batch::with('product:id,name')->find($batchId);

        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        $logs = FarmingLog::query()
            ->with('author:id,full_name')
            ->where('batch_id', $batchId)
            ->orderBy('activity_date')
            ->orderBy('id')
            ->get();

        return $this->success([
            'batch' => $batch,
            'logs'  => $logs,
        ], 'Lấy nhật ký canh tác thành công.');
    }

    /**
     * THÊM MỘT MỐC NHẬT KÝ.
     */
    public function store(Request $request, int $batchId): JsonResponse
    {
        $batch = Batch::find($batchId);

        if ($batch === null) {
            return $this->error('Không tìm thấy lô hàng.', 404);
        }

        $validated = $request->validate([
            'activity_type' => ['required', 'in:planting,watering,fertilizing,spraying,inspection,harvesting'],
            'activity_date' => ['required', 'date', 'before_or_equal:today'],
            'title'         => ['required', 'string', 'max:150'],
            'description'   => ['nullable', 'string', 'max:2000'],

            // Danh sách URL ảnh/video đã upload lên Cloudinary.
            'media'         => ['nullable', 'array', 'max:10'],
            'media.*'       => ['url', 'max:500'],
        ], [
            'activity_type.required' => 'Vui lòng chọn loại hoạt động.',
            'activity_date.required' => 'Vui lòng nhập ngày thực hiện.',
            'title.required'         => 'Vui lòng nhập tiêu đề hoạt động.',
            'media.max'              => 'Mỗi mốc nhật ký chỉ được đính kèm tối đa 10 tệp.',
            'media.*.url'            => 'Đường dẫn ảnh/video không hợp lệ.',
        ]);

        $log = FarmingLog::create([
            'batch_id'      => $batchId,
            'created_by'    => Auth::guard('api')->id(),
            'activity_type' => $validated['activity_type'],
            'activity_date' => $validated['activity_date'],
            'title'         => $validated['title'],
            'description'   => $validated['description'] ?? null,
            'media'         => $validated['media'] ?? [],
        ]);

        return $this->success($log->load('author:id,full_name'), 'Đã thêm nhật ký canh tác.', 201);
    }

    /**
     * XOÁ MỘT MỐC NHẬT KÝ GHI NHẦM.
     * Chỉ cho phép trong 24 giờ đầu để dữ liệu truy xuất không bị sửa về sau.
     */
    public function destroy(int $batchId, int $logId): JsonResponse
    {
        $log = FarmingLog::where('batch_id', $batchId)->find($logId);

        if ($log === null) {
            return $this->error('Không tìm thấy nhật ký.', 404);
        }

        if ($log->created_at->copy()->addHours(24)->isPast()) {
            return $this->error('Chỉ có thể xoá nhật ký trong vòng 24 giờ sau khi tạo.', 422);
        }

        $log->delete();

        return $this->success(null, 'Đã xoá nhật ký canh tác.');
    }
}
