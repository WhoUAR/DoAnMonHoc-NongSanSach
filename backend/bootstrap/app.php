<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Cấu hình khởi động ứng dụng Laravel.
 *
 *   - withRouting(): đăng ký route web và API.
 *   - withMiddleware(): đăng ký CORS, gắn alias 'role' và 'jwt.optional'.
 *   - withExceptions(): biến mọi lỗi thành JSON thống nhất để React
 *     chỉ cần đọc một cấu trúc duy nhất.
 */

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\OptionalJwtAuth;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cho phép Frontend React gọi API từ cổng khác (CORS).
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Đăng ký alias để dùng trong routes/api.php.
        $middleware->alias([
            'role'         => EnsureUserHasRole::class,
            'jwt.optional' => OptionalJwtAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Lỗi validate => trả JSON có cấu trúc thống nhất.
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $exception->errors(),
            ], 422);
        });

        // Chưa đăng nhập hoặc token sai.
        // Bắt cả AuthenticationException của Laravel lẫn UnauthorizedHttpException
        // do gói JWT ném ra, để Frontend luôn nhận một cấu trúc JSON thống nhất.
        $exceptions->render(function (AuthenticationException|UnauthorizedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Bạn cần đăng nhập để thực hiện thao tác này.',
            ], 401);
        });

        // Đường dẫn API không tồn tại.
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tài nguyên yêu cầu.',
            ], 404);
        });
    })->create();
