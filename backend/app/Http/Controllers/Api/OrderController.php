<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Controller đơn hàng - phần khách hàng tự thao tác.
 *
 *   store()   : đặt hàng (hỗ trợ cả khách vãng lai lẫn khách thành viên).
 *   index()   : "Đơn hàng của tôi".
 *   show()    : chi tiết một đơn (kèm bằng chứng phân bổ lô FEFO).
 *   cancel()  : khách tự huỷ đơn khi còn ở trạng thái chờ duyệt.
 *
 *   Toàn bộ logic tính tiền / trừ kho nằm trong OrderService để Controller
 *   chỉ còn nhiệm vụ nhận dữ liệu và trả phản hồi - dễ đọc khi bảo vệ.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\VNPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly VNPayService $vnPayService,
    ) {
    }

    /**
     * ĐẶT HÀNG.
     * Không bắt buộc đăng nhập => khách vãng lai (Guest) vẫn mua được.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receiver_name'    => ['required', 'string', 'max:100'],
            'receiver_phone'   => ['required', 'string', 'max:20'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'note'             => ['nullable', 'string', 'max:500'],
            'payment_method'   => ['required', 'in:cod,vnpay'],
            'points_used'      => ['nullable', 'integer', 'min:0'],

            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'numeric', 'min:0.001'],
        ], [
            'receiver_name.required'    => 'Vui lòng nhập tên người nhận.',
            'receiver_phone.required'   => 'Vui lòng nhập số điện thoại.',
            'shipping_address.required' => 'Vui lòng nhập địa chỉ giao hàng.',
            'payment_method.required'   => 'Vui lòng chọn phương thức thanh toán.',
            'items.required'            => 'Giỏ hàng đang trống.',
            'items.min'                 => 'Giỏ hàng phải có ít nhất một sản phẩm.',
        ]);

        /** @var \App\Models\User|null $customer */
        $customer = Auth::guard('api')->user();

        $order = $this->orderService->placeOrder($validated, $customer);

        $response = [
            'order' => $order->load('items'),
        ];

        // Nếu chọn VNPay thì tạo luôn đường dẫn thanh toán để Frontend chuyển trang.
        if ($order->payment_method === Order::PAYMENT_VNPAY) {
            $response['payment_url'] = $this->vnPayService->createPaymentUrl(
                $order,
                $request->ip() ?? '127.0.0.1',
            );
        }

        return $this->success($response, 'Đặt hàng thành công. Vui lòng chờ nhân viên duyệt đơn.', 201);
    }

    /**
     * DANH SÁCH ĐƠN CỦA TÔI.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->withCount('items')
            ->where('user_id', Auth::guard('api')->id())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(max(1, (int) $request->input('per_page', 10)));

        return $this->paginated($orders, 'Lấy danh sách đơn hàng thành công.');
    }

    /**
     * CHI TIẾT ĐƠN HÀNG.
     * Bao gồm cả các dòng phân bổ lô - đây là bằng chứng FEFO.
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::query()
            ->with([
                'items',
                'batchAllocations.batch:id,batch_code,expiry_date',
                'payments',
                'complaint',
            ])
            ->where('user_id', Auth::guard('api')->id())
            ->find($id);

        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        return $this->success($order, 'Lấy chi tiết đơn hàng thành công.');
    }

    /**
     * KHÁCH TỰ HUỶ ĐƠN.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Vui lòng cho biết lý do huỷ đơn.',
        ]);

        $order = Order::where('user_id', Auth::guard('api')->id())->find($id);

        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng.', 404);
        }

        if (! $order->canBeCancelled()) {
            return $this->error('Đơn hàng đã được duyệt nên không thể tự huỷ. Vui lòng liên hệ CSKH.', 422);
        }

        $order = $this->orderService->cancelOrder($order, Auth::guard('api')->user(), $validated['reason']);

        return $this->success($order, 'Đã huỷ đơn hàng.');
    }
}
