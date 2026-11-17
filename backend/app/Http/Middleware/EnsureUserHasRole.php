<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Middleware PHÂN QUYỀN THEO VAI TRÒ (Role-based).
 *
 *   Đây là yêu cầu Phi chức năng về Bảo mật ở Tuần 2:
 *   "Mọi API cập nhật đơn hàng/kho phải được bảo vệ bằng JWT và phân quyền chặt".
 *
 *   Cách dùng trong routes/api.php:
 *       Route::middleware(['jwt.auth', 'role:admin,sales'])->group(...)
 *
 *   Nếu người dùng đã đăng nhập nhưng sai vai trò, trả về 403 kèm thông báo
 *   tiếng Việt để dễ trình bày khi bảo vệ.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  string  ...$roles  Danh sách vai trò được phép truy cập.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn cần đăng nhập để thực hiện thao tác này.',
            ], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền truy cập chức năng này.',
                'your_role' => $user->role,
                'required_roles' => $roles,
            ], 403);
        }

        if ($user->status === 'locked') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị khoá. Vui lòng liên hệ quản trị viên.',
            ], 403);
        }

        return $next($request);
    }
}
