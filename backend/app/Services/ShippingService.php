<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Tính phí giao hàng theo khoảng cách.
 *
 *   Đề bài yêu cầu phí ship động theo km. Vì API bản đồ (Google Maps) là
 *   tính năng Could-have và có thể bị lỗi/chưa duyệt API key, service này
 *   dùng một BẢNG KHOẢNG CÁCH NỘI THÀNH cấu hình sẵn (dummy data), đúng
 *   phương án dự phòng đã ghi trong kế hoạch rủi ro của đồ án.
 *
 *   Khi nào nâng cấp: đổi estimateDistanceKm() sang gọi Google Distance
 *   Matrix API thật, phần còn lại giữ nguyên.
 */

namespace App\Services;

use App\Models\SystemSetting;

class ShippingService
{
    /**
     * Phí cơ bản cho 3km đầu tiên.
     */
    private const BASE_FEE = 15000;

    /**
     * Phí cộng thêm cho mỗi km tiếp theo.
     */
    private const FEE_PER_KM = 4000;

    /**
     * Khoảng cách tối đa phục vụ (km). Xa hơn thì không nhận đơn.
     */
    private const MAX_DISTANCE_KM = 25;

    /**
     * Đơn hàng từ mức này trở lên được miễn phí giao hàng.
     */
    private const FREE_SHIPPING_THRESHOLD = 300000;

    /**
     * Bảng khoảng cách giả lập từ nông trại (Củ Chi) đến các quận nội thành.
     * Dùng làm phương án dự phòng khi chưa có Google Maps API key.
     */
    private const DISTANCE_MAP = [
        'củ chi'        => 8,
        'hóc môn'       => 12,
        'quận 12'       => 15,
        'bình thạnh'    => 22,
        'phú nhuận'     => 24,
        'gò vấp'        => 20,
        'tân bình'      => 23,
        'tân phú'       => 19,
        'quận 1'        => 26,
        'quận 3'        => 25,
        'quận 10'       => 23,
    ];

    /**
     * Khoảng cách mặc định khi không nhận ra địa chỉ.
     */
    private const DEFAULT_DISTANCE_KM = 15;

    /**
     * TÍNH PHÍ GIAO HÀNG.
     *
     * @param  string  $shippingAddress  Địa chỉ đầy đủ khách nhập.
     * @param  float   $subtotal         Tiền hàng để xét miễn phí ship.
     */
    public function calculateFee(string $shippingAddress, float $subtotal): float
    {
        // Đơn lớn thì miễn phí giao hàng để khuyến khích mua nhiều.
        $threshold = (float) SystemSetting::get('shipping.free_threshold', self::FREE_SHIPPING_THRESHOLD);

        if ($subtotal >= $threshold) {
            return 0;
        }

        $distanceKm = $this->estimateDistanceKm($shippingAddress);

        if ($distanceKm > self::MAX_DISTANCE_KM) {
            return (float) (self::BASE_FEE + (self::MAX_DISTANCE_KM - 3) * self::FEE_PER_KM);
        }

        if ($distanceKm <= 3) {
            return (float) self::BASE_FEE;
        }

        $extraKm = $distanceKm - 3;

        return (float) (self::BASE_FEE + $extraKm * self::FEE_PER_KM);
    }

    /**
     * ƯỚC LƯỢNG KHOẢNG CÁCH từ chuỗi địa chỉ.
     *
     * Cách làm: tìm tên quận trong địa chỉ, tra bảng DISTANCE_MAP.
     * Đây là dummy data - xem ghi chú đầu file.
     */
    public function estimateDistanceKm(string $shippingAddress): int
    {
        $normalized = mb_strtolower($shippingAddress);

        foreach (self::DISTANCE_MAP as $district => $km) {
            if (str_contains($normalized, $district)) {
                return $km;
            }
        }

        return self::DEFAULT_DISTANCE_KM;
    }
}
