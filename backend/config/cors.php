<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Cấu hình CORS (Cross-Origin Resource Sharing).
 *
 *   Cho phép ứng dụng React chạy ở cổng 5173 gọi API Laravel ở cổng 8000.
 *   Không có file này, trình duyệt sẽ chặn mọi request từ Frontend.
 */

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Chỉ cho phép Frontend của dự án gọi API.
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Dùng Bearer Token trong header nên không cần cookie.
    'supports_credentials' => false,

];
