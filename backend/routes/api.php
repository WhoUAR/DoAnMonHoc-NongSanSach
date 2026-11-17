<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Khai báo toàn bộ đường dẫn API của hệ thống.
 *
 *   Cấu trúc nhóm route, đọc từ trên xuống:
 *     1. CÔNG KHAI       - ai cũng gọi được, không cần đăng nhập
 *     2. ĐÃ ĐĂNG NHẬP    - chỉ cần JWT hợp lệ
 *     3. THEO VAI TRÒ    - dùng middleware role:... để giới hạn
 *
 *   Middleware 'jwt.auth' kiểm tra token.
 *   Middleware 'role:admin' kiểm tra vai trò (file EnsureUserHasRole).
 */

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\FarmingLogController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalesOrderController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TraceabilityController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 1. CÔNG KHAI - KHÔNG CẦN ĐĂNG NHẬP
|--------------------------------------------------------------------------
*/

// Xác thực. Giới hạn 5 lần/phút mỗi IP để chặn dò mật khẩu.
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// Sản phẩm (khách vãng lai vẫn xem được)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{slugOrId}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);

// Bảng lô đang bán - hiển thị ở trang chủ, không lộ thông tin nội bộ
Route::get('/available-batches', [ProductController::class, 'availableBatches']);

// Gói định kỳ đang bán
Route::get('/subscription-plans', [SubscriptionController::class, 'plans']);

// Truy xuất nguồn gốc: khách quét QR là xem được ngay
Route::get('/traceability/{token}', [TraceabilityController::class, 'show']);
Route::get('/traceability/{token}/qr.svg', [TraceabilityController::class, 'qrCode']);

// VNPay gọi về (không thể gắn JWT vì đây là trình duyệt của khách).
// Route simulate tự kiểm chữ ký HMAC sinh từ APP_KEY, xem PaymentController.
Route::get('/payments/vnpay/return', [PaymentController::class, 'returnUrl']);
Route::get('/payments/vnpay/simulate', [PaymentController::class, 'simulate']);

/*
|--------------------------------------------------------------------------
| 2. ĐÃ ĐĂNG NHẬP - MỌI VAI TRÒ ĐỀU GỌI ĐƯỢC
|--------------------------------------------------------------------------
*/
Route::middleware('jwt.auth')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Đơn hàng của chính khách
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);

    // Gói định kỳ của chính khách
    Route::get('/my-subscriptions', [SubscriptionController::class, 'mySubscriptions']);
    Route::post('/subscriptions/{id}/pause', [SubscriptionController::class, 'pause']);

    // Khiếu nại của chính khách
    Route::get('/my-complaints', [ComplaintController::class, 'myComplaints']);
    Route::post('/complaints', [ComplaintController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| 3A. ĐẶT HÀNG - CHO PHÉP CẢ KHÁCH VÃNG LAI (GUEST)
|--------------------------------------------------------------------------
| Route này KHÔNG bắt buộc JWT. Nếu có token hợp lệ, hệ thống vẫn nhận ra
| khách thành viên; nếu không có, đơn được ghi nhận là đơn của Guest.
*/
Route::middleware('jwt.optional')->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| 3B. KHÁCH HÀNG ĐĂNG KÝ GÓI ĐỊNH KỲ
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth', 'role:customer'])->group(function () {
    Route::post('/subscriptions', [SubscriptionController::class, 'subscribe']);
});

/*
|--------------------------------------------------------------------------
| 4. NHÂN VIÊN BÁN HÀNG / CSKH (SALES)
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth', 'role:sales,admin'])->group(function () {
    // Duyệt đơn, điều phối giao hàng
    Route::get('/sales/orders', [SalesOrderController::class, 'index']);
    Route::post('/sales/orders/{id}/confirm', [SalesOrderController::class, 'confirm']);
    Route::post('/sales/orders/{id}/ship', [SalesOrderController::class, 'ship']);
    Route::post('/sales/orders/{id}/deliver', [SalesOrderController::class, 'deliver']);
    Route::post('/sales/orders/{id}/cancel', [SalesOrderController::class, 'cancel']);

    // Xử lý khiếu nại
    Route::get('/sales/complaints', [ComplaintController::class, 'index']);
    Route::post('/sales/complaints/{id}/handle', [ComplaintController::class, 'handle']);

    // Dời kỳ giao khi thiếu hàng
    Route::post('/sales/subscriptions/{id}/skip', [SubscriptionController::class, 'skip']);
});

/*
|--------------------------------------------------------------------------
| 5. NHÂN VIÊN NÔNG TRẠI + NHÂN VIÊN KHO
|--------------------------------------------------------------------------
| Hai vai trò này cùng thao tác trên Lô hàng nhưng khác quyền:
|   - farm_staff : tạo lô, ghi nhật ký canh tác
|   - warehouse_staff : thu hoạch nhập kho, xuất hủy, xem cảnh báo
*/
Route::middleware(['jwt.auth', 'role:farm_staff,warehouse_staff,admin'])->group(function () {
    Route::get('/batches', [BatchController::class, 'index']);
    Route::get('/batches/{id}/transactions', [BatchController::class, 'transactions']);
    Route::get('/batches-expiring-soon', [BatchController::class, 'expiringSoon']);
});

// Chỉ Nhân viên nông trại mới tạo lô và ghi nhật ký
Route::middleware(['jwt.auth', 'role:farm_staff,admin'])->group(function () {
    Route::post('/batches', [BatchController::class, 'store']);

    Route::get('/batches/{batchId}/farming-logs', [FarmingLogController::class, 'index']);
    Route::post('/batches/{batchId}/farming-logs', [FarmingLogController::class, 'store']);
    Route::delete('/batches/{batchId}/farming-logs/{logId}', [FarmingLogController::class, 'destroy']);
});

// Chỉ Nhân viên kho mới nhập kho và xuất hủy
Route::middleware(['jwt.auth', 'role:warehouse_staff,admin'])->group(function () {
    Route::post('/batches/{id}/harvest', [BatchController::class, 'harvest']);
    Route::post('/batches/{id}/dispose', [BatchController::class, 'dispose']);
});

/*
|--------------------------------------------------------------------------
| 6. QUẢN TRỊ VIÊN (ADMIN)
|--------------------------------------------------------------------------
*/
Route::middleware(['jwt.auth', 'role:admin'])->group(function () {
    // Người dùng và phân quyền
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::put('/admin/users/{id}/role', [UserController::class, 'updateRole']);
    Route::post('/admin/users/{id}/toggle-status', [UserController::class, 'toggleStatus']);

    // Báo cáo thống kê
    Route::get('/admin/reports/overview', [ReportController::class, 'overview']);
    Route::get('/admin/reports/revenue', [ReportController::class, 'revenue']);
    Route::get('/admin/reports/inventory', [ReportController::class, 'inventory']);
    Route::get('/admin/reports/top-products', [ReportController::class, 'topProducts']);
});
