<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Service xử lý đơn hàng - nơi ghép mọi nghiệp vụ lại với nhau.
 *
 *   placeOrder()  : khách (hoặc khách vãng lai) đặt hàng.
 *                   Chỉ TÍNH TIỀN và LƯU đơn ở trạng thái pending.
 *                   Chưa trừ kho - vì đơn chưa được Sales duyệt.
 *   confirmOrder(): Sales duyệt đơn => gọi InventoryService để trừ kho FEFO.
 *                   Nếu không đủ hàng, đơn bị từ chối và báo lỗi rõ ràng.
 *   cancelOrder() : huỷ đơn. Nếu đơn đã trừ kho thì hoàn hàng về đúng lô cũ.
 *   markDelivered(): giao thành công => cộng điểm tích luỹ, đánh dấu COD.
 */

namespace App\Services;

use App\Models\Batch;
use App\Models\InventoryTransaction;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\OrderBatchAllocation;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ShippingService $shippingService,
    ) {
    }

    /**
     * TẠO ĐƠN HÀNG MỚI.
     *
     * @param  array{
     *     user_id?: int|null,
     *     receiver_name: string,
     *     receiver_phone: string,
     *     shipping_address: string,
     *     note?: string|null,
     *     payment_method: string,
     *     points_used?: int,
     *     items: array<int, array{product_id: int, quantity: float}>
     * }  $data
     *
     * @throws ValidationException khi sản phẩm không tồn tại hoặc không đủ tồn kho.
     */
    public function placeOrder(array $data, ?User $customer = null): Order
    {
        return DB::transaction(function () use ($data, $customer) {
            $subtotal = 0.0;
            $preparedItems = [];

            // --- Bước 1: kiểm tra từng sản phẩm và tính tiền tạm tính ---
            foreach ($data['items'] as $line) {
                $product = Product::whereKey($line['product_id'])
                    ->where('is_active', true)
                    ->first();

                if ($product === null) {
                    throw ValidationException::withMessages([
                        'items' => "Sản phẩm #{$line['product_id']} không tồn tại hoặc đã ngừng bán.",
                    ]);
                }

                $quantity = (float) $line['quantity'];

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => "Số lượng của {$product->name} phải lớn hơn 0.",
                    ]);
                }

                // Kiểm tra tồn kho NGAY khi đặt để báo sớm cho khách.
                // Tồn kho thật vẫn được trừ lại một lần nữa lúc Sales duyệt đơn.
                $available = $product->totalAvailableQuantity();

                if ($available < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "{$product->name} chỉ còn {$available} {$product->unit}, "
                            . "không đủ {$quantity} {$product->unit}.",
                    ]);
                }

                $unitPrice  = $product->currentPrice();
                $lineTotal  = round($unitPrice * $quantity, 0);
                $subtotal  += $lineTotal;

                $preparedItems[] = [
                    'product'    => $product,
                    'quantity'   => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            // --- Bước 2: tính phí giao hàng ---
            $shippingFee = $this->shippingService->calculateFee($data['shipping_address'], $subtotal);

            // --- Bước 3: trừ điểm tích luỹ (nếu khách chọn dùng) ---
            $pointsUsed     = 0;
            $discountAmount = 0.0;

            if ($customer !== null && ! empty($data['points_used'])) {
                [$pointsUsed, $discountAmount] = $this->redeemPoints(
                    $customer,
                    (int) $data['points_used'],
                    $subtotal + $shippingFee,
                );
            }

            $totalAmount = max(0, round($subtotal + $shippingFee - $discountAmount, 0));

            // --- Bước 4: lưu đơn hàng ---
            $order = Order::create([
                'order_code'       => $this->generateOrderCode(),
                'user_id'          => $customer?->id,
                'subscription_id'  => $data['subscription_id'] ?? null,
                'receiver_name'    => $data['receiver_name'],
                'receiver_phone'   => $data['receiver_phone'],
                'shipping_address' => $data['shipping_address'],
                'note'             => $data['note'] ?? null,
                'subtotal'         => $subtotal,
                'shipping_fee'     => $shippingFee,
                'discount_amount'  => $discountAmount,
                'points_used'      => $pointsUsed,
                'total_amount'     => $totalAmount,
                'payment_method'   => $data['payment_method'],
                'payment_status'   => 'unpaid',
                'status'           => Order::STATUS_PENDING,
            ]);

            // --- Bước 5: lưu chi tiết đơn (ảnh chụp tên + giá) ---
            foreach ($preparedItems as $item) {
                OrderItem::create([
                    'order_id'       => $order->id,
                    'product_id'     => $item['product']->id,
                    'product_name'   => $item['product']->name,
                    'product_unit'   => $item['product']->unit,
                    'unit_price'     => $item['unit_price'],
                    'quantity'       => $item['quantity'],
                    'line_total'     => $item['line_total'],
                ]);
            }

            return $order->load('items');
        });
    }

    /**
     * SALES DUYỆT ĐƠN - đây là lúc thuật toán FEFO thực sự chạy.
     *
     * @throws ValidationException khi tổng tồn kho không đủ.
     */
    public function confirmOrder(Order $order, User $sales): Order
    {
        if ($order->status !== Order::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Chỉ có thể duyệt đơn đang ở trạng thái chờ xử lý.',
            ]);
        }

        return DB::transaction(function () use ($order, $sales) {
            $order->load('items.product');

            // Duyệt từng dòng hàng: chia lô theo FEFO rồi trừ kho.
            foreach ($order->items as $item) {
                $product = $item->product;
                $need    = (float) $item->quantity;

                try {
                    // Bước A: lên kế hoạch lấy hàng từ những lô nào.
                    $allocation = $this->inventoryService->planFefoAllocation($product, $need);

                    // Bước B: trừ kho thật và ghi thẻ kho.
                    $applied = $this->inventoryService->decreaseStock($allocation, $order->id, $sales);
                } catch (RuntimeException $exception) {
                    // Thiếu hàng => huỷ toàn bộ transaction, đơn giữ nguyên pending.
                    throw ValidationException::withMessages([
                        'inventory' => $exception->getMessage(),
                    ]);
                }

                // Bước C: lưu lại bằng chứng FEFO cho từng lô đã lấy.
                foreach ($applied as $batchId => $detail) {
                    OrderBatchAllocation::create([
                        'order_id'      => $order->id,
                        'order_item_id' => $item->id,
                        'batch_id'      => $batchId,
                        'quantity'      => $detail['quantity'],
                    ]);
                }

                // Bước D: đánh dấu dòng hàng đã chuẩn bị đủ.
                $item->quantity_prepared = $need;
                $item->save();
            }

            $order->status       = Order::STATUS_CONFIRMED;
            $order->confirmed_at = now();
            $order->save();

            return $order->fresh(['items', 'batchAllocations.batch']);
        });
    }

    /**
     * HUỶ ĐƠN HÀNG.
     * Nếu đơn đã trừ kho rồi thì phải HOÀN HÀNG về đúng lô đã lấy.
     */
    public function cancelOrder(Order $order, User $actor, string $reason): Order
    {
        if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Đơn đã giao hoặc đã huỷ thì không thể huỷ thêm.',
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $reason) {
            // Nếu đơn đã được duyệt (đã trừ kho) thì hoàn hàng lại.
            if ($order->status === Order::STATUS_CONFIRMED) {
                $this->restoreAllocatedStock($order, $actor);
            }

            $order->status        = Order::STATUS_CANCELLED;
            $order->cancelled_at  = now();
            $order->cancel_reason = $reason;
            $order->save();

            // Hoàn lại điểm đã dùng cho đơn này.
            if ($order->points_used > 0 && $order->user_id !== null) {
                $customer = User::find($order->user_id);

                if ($customer !== null) {
                    $customer->increment('loyalty_points', $order->points_used);

                    LoyaltyTransaction::create([
                        'user_id'     => $customer->id,
                        'type'        => LoyaltyTransaction::TYPE_ADJUST,
                        'points'      => $order->points_used,
                        'order_id'    => $order->id,
                        'description' => 'Hoàn điểm do huỷ đơn hàng.',
                    ]);
                }
            }

            return $order->fresh();
        });
    }

    /**
     * GIAO HÀNG THÀNH CÔNG - điểm tích luỹ được cộng ở đây.
     */
    public function markDelivered(Order $order, User $sales): Order
    {
        return DB::transaction(function () use ($order, $sales) {
            $order->status       = Order::STATUS_DELIVERED;
            $order->delivered_at = now();
            $order->save();

            // Đơn COD coi như thanh toán xong khi giao thành công.
            if ($order->payment_method === Order::PAYMENT_COD) {
                $order->payment_status = 'paid';
                $order->save();

                Payment::create([
                    'order_id' => $order->id,
                    'method'   => Order::PAYMENT_COD,
                    'amount'   => $order->total_amount,
                    'status'   => Payment::STATUS_SUCCESS,
                    'paid_at'  => now(),
                ]);
            }

            $this->awardLoyaltyPoints($order);

            return $order->fresh();
        });
    }

    /**
     * CỘNG ĐIỂM TÍCH LUỸ sau khi đơn giao thành công.
     * Quy tắc: mỗi 10.000đ được 1 điểm (đọc từ config).
     */
    private function awardLoyaltyPoints(Order $order): void
    {
        if ($order->user_id === null) {
            return; // Khách vãng lai không có tài khoản để cộng điểm.
        }

        $divisor = (int) config('loyalty.earn_divisor', 10000);
        $points  = (int) floor((float) $order->subtotal / $divisor);

        if ($points <= 0) {
            return;
        }

        $customer = User::find($order->user_id);
        $customer->increment('loyalty_points', $points);

        LoyaltyTransaction::create([
            'user_id'     => $customer->id,
            'type'        => LoyaltyTransaction::TYPE_EARN,
            'points'      => $points,
            'order_id'    => $order->id,
            'description' => "Tích điểm từ đơn {$order->order_code}.",
        ]);
    }

    /**
     * TRỪ ĐIỂM khi khách dùng điểm giảm giá.
     *
     * @return array{0: int, 1: float}  [số điểm dùng, số tiền được giảm]
     */
    private function redeemPoints(User $customer, int $requestedPoints, float $maxPayable): array
    {
        $pointValue = (int) config('loyalty.point_value', 1000);

        // Không cho dùng quá số điểm đang có.
        $pointsUsed = min($requestedPoints, $customer->loyalty_points);

        // Không cho giảm quá giá trị đơn hàng.
        $maxByAmount    = (int) floor($maxPayable / $pointValue);
        $pointsUsed     = max(0, min($pointsUsed, $maxByAmount));
        $discountAmount = (float) ($pointsUsed * $pointValue);

        if ($pointsUsed > 0) {
            $customer->decrement('loyalty_points', $pointsUsed);

            LoyaltyTransaction::create([
                'user_id'     => $customer->id,
                'type'        => LoyaltyTransaction::TYPE_REDEEM,
                'points'      => -$pointsUsed,
                'description' => "Dùng {$pointsUsed} điểm để giảm giá đơn hàng.",
            ]);
        }

        return [$pointsUsed, $discountAmount];
    }

    /**
     * HOÀN HÀNG VỀ LÔ khi huỷ đơn đã duyệt.
     */
    private function restoreAllocatedStock(Order $order, User $actor): void
    {
        $allocations = OrderBatchAllocation::where('order_id', $order->id)->get();

        foreach ($allocations as $allocation) {
            $batch = Batch::whereKey($allocation->batch_id)->lockForUpdate()->first();

            if ($batch === null) {
                continue;
            }

            $batch->quantity_available = round(
                (float) $batch->quantity_available + (float) $allocation->quantity,
                3
            );

            // Lô trước đó bị đánh dấu hết hàng thì mở lại để bán tiếp.
            if ($batch->status === Batch::STATUS_DEPLETED) {
                $batch->status = Batch::STATUS_AVAILABLE;
            }

            $batch->save();

            InventoryTransaction::create([
                'batch_id'      => $batch->id,
                'type'          => InventoryTransaction::TYPE_ADJUST,
                'quantity'      => $allocation->quantity,
                'balance_after' => $batch->quantity_available,
                'order_id'      => $order->id,
                'created_by'    => $actor->id,
                'note'          => "Hoàn hàng do huỷ đơn {$order->order_code}.",
            ]);
        }
    }

    /**
     * Sinh mã đơn dạng ORD-20261115-0001, dễ đọc khi bảo vệ đồ án.
     */
    private function generateOrderCode(): string
    {
        $prefix = 'ORD-' . now()->format('Ymd') . '-';

        $todayCount = Order::whereDate('created_at', now()->toDateString())->count() + 1;

        return $prefix . str_pad((string) $todayCount, 4, '0', STR_PAD_LEFT);
    }
}
