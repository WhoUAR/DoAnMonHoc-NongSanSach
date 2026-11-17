<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Controller khiếu nại / đổi trả.
 *
 *   CỦA KHÁCH HÀNG:
 *     store()          : gửi khiếu nại cho một đơn đã giao (kèm ảnh/video).
 *     myComplaints()   : danh sách khiếu nại của chính khách.
 *
 *   CỦA SALES / CSKH:
 *     index()          : danh sách khiếu nại cần xử lý.
 *     handle()         : duyệt hoàn tiền / giao bù / từ chối.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\Order;
use App\Services\ComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComplaintController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ComplaintService $complaintService,
    ) {
    }

    /**
     * KHÁCH GỬI KHIẾU NẠI.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id'       => ['required', 'integer', 'exists:orders,id'],
            'reason'         => ['required', 'in:damaged,rotten,missing_item,wrong_item,other'],
            'description'    => ['nullable', 'string', 'max:2000'],

            // Bắt buộc có ít nhất 1 ảnh/video minh chứng.
            'evidence_media'   => ['required', 'array', 'min:1', 'max:5'],
            'evidence_media.*' => ['url', 'max:500'],
        ], [
            'order_id.required'        => 'Vui lòng chọn đơn hàng cần khiếu nại.',
            'reason.required'          => 'Vui lòng chọn lý do khiếu nại.',
            'evidence_media.required'  => 'Vui lòng đính kèm ảnh hoặc video minh chứng.',
            'evidence_media.min'       => 'Cần ít nhất một ảnh hoặc video minh chứng.',
            'evidence_media.max'       => 'Chỉ được đính kèm tối đa 5 tệp.',
            'evidence_media.*.url'     => 'Đường dẫn ảnh/video không hợp lệ.',
        ]);

        $order = Order::find($validated['order_id']);

        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        $complaint = $this->complaintService->createComplaint(
            Auth::guard('api')->user(),
            $order,
            $validated['reason'],
            $validated['description'] ?? null,
            $validated['evidence_media'],
        );

        return $this->success($complaint, 'Đã gửi khiếu nại. Bộ phận CSKH sẽ liên hệ trong 24 giờ.', 201);
    }

    /**
     * DANH SÁCH KHIẾU NẠI CỦA CHÍNH KHÁCH.
     */
    public function myComplaints(): JsonResponse
    {
        $complaints = Complaint::query()
            ->with('order:id,order_code,total_amount,delivered_at')
            ->where('user_id', Auth::guard('api')->id())
            ->latest()
            ->get();

        return $this->success($complaints, 'Lấy danh sách khiếu nại thành công.');
    }

    /**
     * DANH SÁCH KHIẾU NẠI CHO SALES.
     */
    public function index(Request $request): JsonResponse
    {
        $complaints = Complaint::query()
            ->with([
                'order:id,order_code,total_amount,delivered_at',
                'customer:id,full_name,email,phone',
            ])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(max(1, (int) $request->input('per_page', 15)));

        return $this->paginated($complaints, 'Lấy danh sách khiếu nại thành công.');
    }

    /**
     * SALES XỬ LÝ KHIẾU NẠI.
     */
    public function handle(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'decision'      => ['required', 'in:approved_refund,approved_replace,rejected'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'staff_note'    => ['nullable', 'string', 'max:500'],
        ], [
            'decision.required' => 'Vui lòng chọn cách xử lý khiếu nại.',
        ]);

        $complaint = Complaint::find($id);

        if ($complaint === null) {
            return $this->error('Không tìm thấy khiếu nại.', 404);
        }

        $complaint = $this->complaintService->handle(
            $complaint,
            Auth::guard('api')->user(),
            $validated['decision'],
            (float) ($validated['refund_amount'] ?? 0),
            $validated['staff_note'] ?? null,
        );

        return $this->success($complaint, 'Đã xử lý khiếu nại.');
    }
}
