<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Cấu hình cổng thanh toán VNPay (môi trường sandbox).
 *           Để trống tmn_code và hash_secret thì hệ thống chạy chế độ
 *           mô phỏng (dummy data) - không cần API key vẫn demo được.
 */

return [
    'tmn_code'    => env('VNPAY_TMN_CODE', ''),
    'hash_secret' => env('VNPAY_HASH_SECRET', ''),
    'url'         => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
    'return_url'  => env('VNPAY_RETURN_URL', 'http://localhost:8000/api/payments/vnpay/return'),
];
