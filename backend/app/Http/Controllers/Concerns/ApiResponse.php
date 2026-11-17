<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Trait trả về JSON thống nhất cho toàn bộ API.
 *
 *   Mọi endpoint đều trả cùng một cấu trúc, Frontend chỉ cần đọc 3 khoá:
 *       { success: bool, message: string, data: mixed }
 *
 *   Nhờ vậy phía React chỉ cần một chỗ xử lý lỗi là dùng được cho tất cả API.
 */

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    /**
     * Trả về kết quả thành công.
     */
    protected function success(mixed $data = null, string $message = 'Thành công', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Trả về lỗi nghiệp vụ.
     */
    protected function error(string $message, int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $statusCode);
    }

    /**
     * Trả về danh sách có phân trang.
     */
    protected function paginated($paginator, string $message = 'Thành công'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }
}
