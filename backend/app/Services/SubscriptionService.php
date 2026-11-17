<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Service xử lý GÓI ĐỊNH KỲ (Subscription).
 *
 *   subscribe()          : khách đăng ký một gói tháng.
 *                          Tính ngày giao đầu tiên dựa trên các thứ khách chọn.
 *   generateDueOrders()  : quét mọi gói đang chạy, gói nào tới ngày giao thì
 *                          TỰ SINH một đơn hàng con. Được gọi bởi lệnh
 *                          `php artisan subscription:generate-orders`.
 *   skipDelivery()       : khi nông trại mất mùa, Sales bỏ một kỳ giao
 *                          và dời ngày giao sang kỳ kế tiếp (luồng ngoại lệ
 *                          được yêu cầu riêng trong đặc tả Tuần 2).
 */

namespace App\Services;

use App\Models\CustomerSubscription;
use App\Models\Order;
use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * ĐĂNG KÝ GÓI ĐỊNH KỲ.
     */
    public function subscribe(User $customer, SubscriptionPlan $plan, array $data): CustomerSubscription
    {
        $startDate = Carbon::parse($data['start_date'] ?? now())->startOfDay();
        $endDate   = $startDate->copy()->addDays($plan->duration_days);

        // Dùng lịch giao riêng của khách nếu có, không thì lấy của gói.
        $weekdays = $data['delivery_weekdays'] ?? $plan->delivery_weekdays;

        if (empty($weekdays)) {
            throw ValidationException::withMessages([
                'delivery_weekdays' => 'Gói này chưa cấu hình ngày giao hàng.',
            ]);
        }

        $firstDelivery = $this->findNextDeliveryDate($startDate, $weekdays, $endDate);

        return DB::transaction(function () use ($customer, $plan, $data, $startDate, $endDate, $weekdays, $firstDelivery) {
            return CustomerSubscription::create([
                'subscription_code'    => $this->generateSubscriptionCode(),
                'user_id'              => $customer->id,
                'subscription_plan_id' => $plan->id,
                'start_date'           => $startDate,
                'end_date'             => $endDate,
                'delivery_weekdays'    => $weekdays,
                'receiver_name'        => $data['receiver_name'],
                'receiver_phone'       => $data['receiver_phone'],
                'shipping_address'     => $data['shipping_address'],
                'total_paid'           => $plan->price,
                'payment_method'       => $data['payment_method'] ?? 'vnpay',
                'payment_status'       => 'unpaid',
                'status'               => CustomerSubscription::STATUS_ACTIVE,
                'next_delivery_date'   => $firstDelivery,
                'note'                 => $data['note'] ?? null,
            ]);
        });
    }

    /**
     * TỰ SINH ĐƠN CHO CÁC GÓI TỚI NGÀY GIAO.
     *
     * @return array<int, Order>  Danh sách đơn vừa sinh ra.
     */
    public function generateDueOrders(): array
    {
        // Chạy hai tiến trình cùng lúc (cron chồng nhau) sẽ tạo đơn trùng cho
        // cùng một kỳ giao. Khoá lại để chỉ một tiến trình xử lý mỗi lần.
        return Cache::lock('subscription-generate-orders', 60)
            ->block(5, fn () => $this->createDueOrders());
    }

    /**
     * Phần thân của generateDueOrders(), tách ra để bọc trong khoá.
     *
     * @return array<int, Order>
     */
    private function createDueOrders(): array
    {
        $today = now()->startOfDay();

        /** @var \Illuminate\Database\Eloquent\Collection<int, CustomerSubscription> $dueSubscriptions */
        $dueSubscriptions = CustomerSubscription::query()
            ->where('status', CustomerSubscription::STATUS_ACTIVE)
            ->whereDate('end_date', '>=', $today->toDateString())
            ->whereDate('next_delivery_date', '<=', $today->toDateString())
            ->with('plan.items.product')
            ->get();

        $createdOrders = [];

        foreach ($dueSubscriptions as $subscription) {
            // Có sản phẩm nào trong gói không đủ hàng thì bỏ qua kỳ này.
            // Sales sẽ nhận cảnh báo riêng để liên hệ khách đổi sản phẩm.
            if (! $this->hasEnoughStock($subscription)) {
                $subscription->note = 'Kỳ giao ' . $today->format('d/m/Y')
                    . ' bị bỏ qua do thiếu hàng. Cần Sales liên hệ khách.';
                $subscription->save();
                continue;
            }

            $order = $this->createOrderForSubscription($subscription, $today);
            $createdOrders[] = $order;

            // Dời ngày giao sang kỳ kế tiếp.
            $subscription->next_delivery_date = $this->findNextDeliveryDate(
                $today->copy()->addDay(),
                $subscription->delivery_weekdays,
                $subscription->end_date,
            );
            $subscription->save();
        }

        return $createdOrders;
    }

    /**
     * BỎ MỘT KỲ GIAO (luồng ngoại lệ: mất mùa / thiếu hàng).
     * Chỉ dời ngày giao, không huỷ gói của khách.
     */
    public function skipDelivery(CustomerSubscription $subscription, User $sales): CustomerSubscription
    {
        $currentDate = $subscription->next_delivery_date ?? now();

        $subscription->next_delivery_date = $this->findNextDeliveryDate(
            Carbon::parse($currentDate)->addDay(),
            $subscription->delivery_weekdays,
            $subscription->end_date,
        );

        $subscription->note = "Sales {$sales->full_name} đã dời kỳ giao ngày "
            . Carbon::parse($currentDate)->format('d/m/Y') . ' do thiếu hàng.';

        $subscription->save();

        return $subscription->fresh();
    }

    /**
     * TẠO ĐƠN HÀNG CON từ một gói định kỳ.
     * Tận dụng lại OrderService để không lặp logic tính tiền.
     */
    private function createOrderForSubscription(CustomerSubscription $subscription, Carbon $deliveryDate): Order
    {
        $items = [];

        foreach ($subscription->plan->items as $planItem) {
            $items[] = [
                'product_id' => $planItem->product_id,
                'quantity'   => (float) $planItem->quantity,
            ];
        }

        // Đơn của gói định kỳ đã trả trước nên tổng tiền = 0,
        // khách không bị tính thêm tiền hàng lần nữa.
        $order = $this->orderService->placeOrder([
            'user_id'          => $subscription->user_id,
            'subscription_id'  => $subscription->id,
            'receiver_name'    => $subscription->receiver_name,
            'receiver_phone'   => $subscription->receiver_phone,
            'shipping_address' => $subscription->shipping_address,
            'note'             => "Đơn tự sinh từ gói {$subscription->subscription_code}.",
            'payment_method'   => $subscription->payment_method,
            'items'            => $items,
        ], $subscription->user);

        // Ghi rõ đơn này không thu tiền vì đã trả trước cả gói.
        $order->subtotal        = 0;
        $order->shipping_fee    = 0;
        $order->total_amount    = 0;
        $order->payment_status  = $subscription->payment_status;
        $order->save();

        return $order->fresh('items');
    }

    /**
     * Kiểm tra gói có đủ tồn kho cho một kỳ giao hay không.
     */
    private function hasEnoughStock(CustomerSubscription $subscription): bool
    {
        foreach ($subscription->plan->items as $planItem) {
            $product = $planItem->product;

            if ($product === null) {
                return false;
            }

            if ($product->totalAvailableQuantity() < (float) $planItem->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * TÌM NGÀY GIAO KẾ TIẾP.
     *
     * Duyệt từng ngày kể từ $from, ngày nào rơi vào một trong các thứ
     * trong $weekdays thì chọn. Giới hạn 14 ngày để tránh vòng lặp vô hạn.
     *
     * Carbon dayOfWeek: 1 = Thứ 2, ..., 7 = Chủ nhật.
     */
    public function findNextDeliveryDate(Carbon $from, array $weekdays, Carbon $endDate): ?Carbon
    {
        $weekdays = array_map('intval', $weekdays);
        $cursor   = $from->copy()->startOfDay();

        for ($i = 0; $i < 14; $i++) {
            if ($cursor->gt($endDate)) {
                return null; // Gói đã hết kỳ.
            }

            if (in_array($cursor->dayOfWeek, $weekdays, true)) {
                return $cursor->copy();
            }

            $cursor->addDay();
        }

        return null;
    }

    private function generateSubscriptionCode(): string
    {
        $prefix = 'SUB-' . now()->format('Ymd') . '-';

        $todayCount = CustomerSubscription::whereDate('created_at', now()->toDateString())->count() + 1;

        return $prefix . str_pad((string) $todayCount, 3, '0', STR_PAD_LEFT);
    }
}
