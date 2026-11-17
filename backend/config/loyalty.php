<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Cấu hình quy đổi điểm tích luỹ.
 *           - earn_divisor: mua bao nhiêu đồng thì được 1 điểm.
 *           - point_value : 1 điểm khi dùng để trả đơn trị giá bao nhiêu đồng.
 */

return [
    'earn_divisor' => (int) env('LOYALTY_EARN_DIVISOR', 10000),
    'point_value'  => (int) env('LOYALTY_POINT_VALUE', 1000),
];
