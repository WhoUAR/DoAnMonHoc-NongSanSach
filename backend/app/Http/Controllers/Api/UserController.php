<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Controller quản lý người dùng dành cho Admin.
 *
 *   index()       : danh sách tài khoản, lọc theo vai trò.
 *   updateRole()  : đổi vai trò của một tài khoản.
 *   toggleStatus(): khoá / mở khoá tài khoản.
 *
 *   Admin là vai trò duy nhất được gọi các API này (xem routes/api.php).
 *   Tài khoản của Admin đang đăng nhập không thể tự khoá chính mình.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use ApiResponse;

    /**
     * DANH SÁCH NGƯỜI DÙNG.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->input('role')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $keyword = $request->string('search')->toString();

                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('full_name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->latest()
            ->paginate(max(1, (int) $request->input('per_page', 20)));

        return $this->paginated($users, 'Lấy danh sách người dùng thành công.');
    }

    /**
     * ĐỔI VAI TRÒ.
     */
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:customer,farm_staff,warehouse_staff,sales,admin'],
        ], [
            'role.required' => 'Vui lòng chọn vai trò mới.',
            'role.in'       => 'Vai trò không hợp lệ.',
        ]);

        $user = User::find($id);

        if ($user === null) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        if ($user->id === Auth::guard('api')->id()) {
            return $this->error('Bạn không thể tự đổi vai trò của chính mình.', 422);
        }

        $user->role = $validated['role'];
        $user->save();

        return $this->success($user, 'Đã cập nhật vai trò người dùng.');
    }

    /**
     * KHOÁ / MỞ KHOÁ TÀI KHOẢN.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $user = User::find($id);

        if ($user === null) {
            return $this->error('Không tìm thấy người dùng.', 404);
        }

        if ($user->id === Auth::guard('api')->id()) {
            return $this->error('Bạn không thể tự khoá tài khoản của chính mình.', 422);
        }

        $user->status = $user->status === 'active' ? 'locked' : 'active';
        $user->save();

        return $this->success($user, 'Đã cập nhật trạng thái tài khoản.');
    }
}
