<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Tạo link thanh toán VNPay và xác thực dữ liệu VNPay trả về.
 *
 *   Dự án chạy ở chế độ SANDBOX (môi trường thử nghiệm của VNPay).
 *   Nếu chưa có VNPAY_TMN_CODE / VNPAY_HASH_SECRET trong .env, hệ thống
 *   tự chuyển sang chế độ MÔ PHỎNG (dummy data) để luồng đặt hàng vẫn
 *   chạy được khi bảo vệ đồ án - đúng phương án dự phòng đã nêu.
 *
 *   Chữ ký theo chuẩn VNPay: sắp xếp tham số theo alphabet, nối bằng '&',
 *   rồi băm HMAC-SHA512 với HASH_SECRET.
 */

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;

class VNPayService
{
    /**
     * Kiểm tra đã cấu hình VNPay thật hay chưa.
     */
    public function isConfigured(): bool
    {
        return ! empty(config('vnpay.tmn_code')) && ! empty(config('vnpay.hash_secret'));
    }

    /**
     * TẠO ĐƯỜNG DẪN THANH TOÁN.
     *
     * Ở chế độ mô phỏng, trả về URL nội bộ /api/payments/vnpay/simulate
     * để Frontend vẫn chạy đúng luồng, sau này chỉ cần đổi .env là thật.
     */
    public function createPaymentUrl(Order $order, string $clientIp): string
    {
        if (! $this->isConfigured()) {
            return url(
                '/api/payments/vnpay/simulate?order_code=' . $order->order_code
                . '&sign=' . $this->simulateSignature($order->order_code)
            );
        }

        $txnRef = 'ORDER' . $order->id . '_' . Str::upper(Str::random(8));

        $params = [
            'vnp_Version'    => '2.1.0',
            'vnp_Command'    => 'pay',
            'vnp_TmnCode'    => config('vnpay.tmn_code'),
            'vnp_Locale'     => 'vn',
            'vnp_CurrCode'   => 'VND',
            'vnp_TxnRef'     => $txnRef,
            'vnp_OrderInfo'  => 'Thanh toan don hang ' . $order->order_code,
            'vnp_OrderType'  => 'other',
            'vnp_Amount'     => (int) ($order->total_amount * 100), // VNPay tính theo đơn vị cent
            'vnp_ReturnUrl'  => config('vnpay.return_url'),
            'vnp_IpAddr'     => $clientIp,
            'vnp_CreateDate' => now()->format('YmdHis'),
        ];

        ksort($params);

        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC1738);
        $secureHash  = hash_hmac('sha512', $queryString, config('vnpay.hash_secret'));

        // Lưu lại mã tham chiếu để đối soát khi VNPay gọi về.
        $order->payments()->create([
            'method'      => Order::PAYMENT_VNPAY,
            'amount'      => $order->total_amount,
            'vnp_txn_ref' => $txnRef,
            'status'      => 'pending',
        ]);

        return config('vnpay.url') . '?' . $queryString . '&vnp_SecureHash=' . $secureHash;
    }

    /**
     * CHỮ KÝ CHO CHẾ ĐỘ MÔ PHỎNG.
     *
     * Vì sao cần: trình duyệt chuyển trang sang /simulate nên không gửi được
     * header Authorization. Không có chữ ký thì ai cũng đánh dấu đơn đã trả tiền.
     * Ký bằng APP_KEY nên chỉ máy chủ này sinh ra được.
     */
    public function simulateSignature(string $orderCode): string
    {
        return hash_hmac('sha256', 'simulate:' . $orderCode, config('app.key'));
    }

    public function verifySimulateSignature(string $orderCode, string $signature): bool
    {
        return hash_equals($this->simulateSignature($orderCode), $signature);
    }

    /**
     * XÁC THỰC CHỮ KÝ dữ liệu VNPay gửi về.
     * Bỏ 2 tham số vnp_SecureHash và vnp_SecureHashType trước khi băm lại.
     */
    public function verifySignature(array $payload): bool
    {
        if (! $this->isConfigured()) {
            return true; // Chế độ mô phỏng luôn hợp lệ.
        }

        $receivedHash = $payload['vnp_SecureHash'] ?? '';

        unset($payload['vnp_SecureHash'], $payload['vnp_SecureHashType']);

        ksort($payload);

        $queryString = http_build_query($payload, '', '&', PHP_QUERY_RFC1738);
        $expectedHash = hash_hmac('sha512', $queryString, config('vnpay.hash_secret'));

        return hash_equals($expectedHash, $receivedHash);
    }

    /**
     * XỬ LÝ KẾT QUẢ VNPAY TRẢ VỀ: cập nhật trạng thái thanh toán của đơn.
     */
    public function handleReturn(array $payload): ?Order
    {
        $txnRef = $payload['vnp_TxnRef'] ?? null;

        if ($txnRef === null) {
            return null;
        }

        $payment = \App\Models\Payment::where('vnp_txn_ref', $txnRef)->first();

        if ($payment === null) {
            return null;
        }

        $order = $payment->order;

        // Mã 00 = giao dịch thành công theo tài liệu VNPay.
        $isSuccess = ($payload['vnp_ResponseCode'] ?? '99') === '00';

        $payment->status           = $isSuccess ? 'success' : 'failed';
        $payment->vnp_response_code = $payload['vnp_ResponseCode'] ?? null;
        $payment->vnp_transaction_no = $payload['vnp_TransactionNo'] ?? null;
        $payment->bank_code        = $payload['vnp_BankCode'] ?? null;
        $payment->raw_response     = $payload;
        $payment->paid_at          = $isSuccess ? now() : null;
        $payment->save();

        if ($order !== null && $isSuccess) {
            $order->payment_status = 'paid';
            $order->save();
        }

        return $order;
    }
}
