<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thanh Liêm (0306241214)
 * CHỨC NĂNG: Service sinh mã QR truy xuất nguồn gốc cho một Lô hàng.
 *
 *   - Mỗi lô có sẵn một chuỗi bí mật `traceability_token`.
 *   - QR chứa đường dẫn công khai dạng:
 *         {FRONTEND_URL}/traceability/{token}
 *   - Người dùng quét mã bằng điện thoại sẽ mở trang công khai,
 *     không cần đăng nhập, và xem được toàn bộ nhật ký canh tác.
 *
 *   Dùng thư viện bacon/bacon-qr-code để render ảnh SVG.
 */

namespace App\Services;

use App\Models\Batch;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

class TraceabilityService
{
    /**
     * Đường dẫn công khai mà mã QR sẽ trỏ tới.
     */
    public function publicUrl(Batch $batch): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');

        return $frontendUrl . '/traceability/' . $batch->traceability_token;
    }

    /**
     * Sinh ảnh QR dạng chuỗi SVG.
     * Frontend có thể nhận chuỗi này và chèn thẳng vào thẻ <img src="data:image/svg+xml...">.
     */
    public function qrSvg(Batch $batch, int $size = 320): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd(),
        );

        $writer = new Writer($renderer);

        return $writer->writeString($this->publicUrl($batch));
    }

    /**
     * Sinh token bí mật cho một lô mới.
     * Tách thành hàm riêng để BatchController gọi khi tạo lô.
     */
    public function generateToken(): string
    {
        return Str::random(48);
    }

    /**
     * TÌM LÔ THEO TOKEN - dùng cho trang tra cứu công khai.
     * Trả về null nếu token không tồn tại (để Controller trả 404 thay vì lỗi 500).
     */
    public function findByToken(string $token): ?Batch
    {
        return Batch::where('traceability_token', $token)
            ->with([
                'product.category',
                'farmStaff:id,full_name',
                'farmingLogs.author:id,full_name',
            ])
            ->first();
    }
}
