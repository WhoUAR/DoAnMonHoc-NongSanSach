<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Controller thanh toán VNPay.
 *
 *   createUrl() : tạo đường dẫn thanh toán cho một đơn hàng.
 *   returnUrl() : VNPay gọi về sau khi khách thanh toán (trình duyệt khách).
 *   simulate()  : endpoint mô phỏng dùng khi CHƯA có API key VNPay.
 *
 *   Nhờ endpoint simulate, luồng đặt hàng - thanh toán vẫn demo trọn vẹn
 *   khi bảo vệ đồ án dù mạng hoặc cổng VNPay sandbox gặp sự cố.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\VNPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly VNPayService $vnPayService,
    ) {
    }

    /**
     * TẠO ĐƯỜNG DẪN THANH TOÁN CHO MỘT ĐƠN.
     */
    public function createUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ], [
            'order_id.required' => 'Vui lòng chọn đơn hàng cần thanh toán.',
        ]);

        $order = Order::findOrFail($validated['order_id']);

        if ($order->payment_status === 'paid') {
            return $this->error('Đơn hàng này đã được thanh toán.', 422);
        }

        $paymentUrl = $this->vnPayService->createPaymentUrl(
            $order,
            $request->ip() ?? '127.0.0.1',
        );

        return $this->success([
            'payment_url' => $paymentUrl,
            'is_simulation' => ! $this->vnPayService->isConfigured(),
        ], 'Tạo đường dẫn thanh toán thành công.');
    }

    /**
     * VNPAY GỌI VỀ SAU KHI KHÁCH THANH TOÁN.
     * VNPay chuyển hướng trình duyệt của khách về đây kèm nhiều tham số.
     */
    public function returnUrl(Request $request)
    {
        $payload = $request->query();

        if (! $this->vnPayService->verifySignature($payload)) {
            return $this->error('Chữ ký VNPay không hợp lệ. Giao dịch bị từ chối.', 400);
        }

        $order = $this->vnPayService->handleReturn($payload);

        if ($order === null) {
            return $this->error('Không tìm thấy giao dịch tương ứng.', 404);
        }

        // Đưa khách quay lại trang kết quả của Frontend.
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $status = $order->payment_status === 'paid' ? 'success' : 'failed';

        return redirect()->away(
            "{$frontendUrl}/orders/{$order->id}?payment={$status}"
        );
    }

    /**
     * MÔ PHỎNG THANH TOÁN (khi chưa cấu hình VNPay thật).
     * Đánh dấu đơn đã thanh toán rồi đưa khách về trang đơn hàng.
     */
    public function simulate(Request $request)
    {
        $orderCode = $request->query('order_code');
        $signature = (string) $request->query('sign', '');

        // Chặn người lạ: chỉ URL do createPaymentUrl() sinh ra mới có chữ ký đúng.
        if (! $this->vnPayService->verifySimulateSignature((string) $orderCode, $signature)) {
            return $this->error('Đường dẫn thanh toán không hợp lệ.', 403);
        }

        $order = Order::where('order_code', $orderCode)->first();

        if ($order === null) {
            return $this->error('Không tìm thấy đơn hàng cần thanh toán.', 404);
        }

        // Gọi lại lần nữa trên đơn đã trả tiền thì không tạo thêm bản ghi.
        if ($order->payment_status === 'paid') {
            return redirect()->away(
                rtrim((string) config('app.frontend_url'), '/')
                . "/orders/{$order->id}?payment=success"
            );
        }

        $order->payment_status = 'paid';
        $order->save();

        $order->payments()->create([
            'method'  => Order::PAYMENT_VNPAY,
            'amount'  => $order->total_amount,
            'status'  => 'success',
            'paid_at' => now(),
            'vnp_response_code' => '00',
            'raw_response'      => ['note' => 'Giao dịch mô phỏng khi chưa cấu hình VNPay sandbox.'],
        ]);

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return redirect()->away("{$frontendUrl}/orders/{$order->id}?payment=success");
    }
}
