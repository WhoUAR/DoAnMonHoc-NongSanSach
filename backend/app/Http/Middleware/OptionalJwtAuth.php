<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Middleware "đăng nhập nếu có, không thì thôi".
 *
 *   Dùng cho API đặt hàng: khách VÃNG LAI (chưa có tài khoản) vẫn phải
 *   đặt được hàng, nhưng nếu khách đã đăng nhập thì hệ thống cần biết
 *   đó là ai để cộng điểm tích luỹ.
 *
 *   Cách làm:
 *     - Có token hợp lệ  -> đăng nhập luôn, cho đi tiếp.
 *     - Không có token   -> bỏ qua, coi như khách vãng lai.
 *     - Token hỏng/hết hạn -> trả 401 để Frontend biết cần đăng nhập lại.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OptionalJwtAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = auth('api');

        // Không gửi token => khách vãng lai, cho đi tiếp bình thường.
        if (! $request->bearerToken()) {
            return $next($request);
        }

        try {
            // Có token => thử lấy thông tin người dùng.
            $user = $guard->user();

            if ($user === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phiên đăng nhập đã hết. Vui lòng đăng nhập lại.',
                ], 401);
            }

            // Gán người dùng vào request để Controller đọc được.
            $request->setUserResolver(fn () => $user);
        } catch (TokenExpiredException|TokenInvalidException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Phiên đăng nhập đã hết. Vui lòng đăng nhập lại.',
            ], 401);
        } catch (Throwable $exception) {
            // Token hỏng hoàn toàn => vẫn cho mua với tư cách khách vãng lai.
        }

        return $next($request);
    }
}
