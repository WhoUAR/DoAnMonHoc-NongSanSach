<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Controller GÓI ĐỊNH KỲ (Subscription).
 *
 *   plans()     : danh sách gói đang bán (công khai).
 *   subscribe() : khách đăng ký một gói.
 *   mySubs()    : danh sách gói của chính khách.
 *   pause()     : tạm dừng nhận hàng.
 *   skip()      : Sales dời một kỳ giao do thiếu hàng (luồng ngoại lệ).
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomerSubscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {
    }

    /**
     * DANH SÁCH GÓI ĐANG BÁN (công khai, không cần đăng nhập).
     */
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->with('items.product:id,name,unit,thumbnail_url,price')
            ->where('is_active', true)
            ->get();

        return $this->success($plans, 'Lấy danh sách gói định kỳ thành công.');
    }

    /**
     * KHÁCH ĐĂNG KÝ GÓI.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'start_date'           => ['nullable', 'date', 'after_or_equal:today'],
            'delivery_weekdays'    => ['nullable', 'array', 'min:1'],
            'delivery_weekdays.*'  => ['integer', 'between:1,7'],
            'receiver_name'        => ['required', 'string', 'max:100'],
            'receiver_phone'       => ['required', 'string', 'max:20'],
            'shipping_address'     => ['required', 'string', 'max:255'],
            'payment_method'       => ['nullable', 'in:cod,vnpay'],
            'note'                 => ['nullable', 'string', 'max:500'],
        ], [
            'subscription_plan_id.required' => 'Vui lòng chọn gói định kỳ.',
            'receiver_name.required'        => 'Vui lòng nhập tên người nhận.',
            'receiver_phone.required'       => 'Vui lòng nhập số điện thoại.',
            'shipping_address.required'     => 'Vui lòng nhập địa chỉ giao hàng.',
            'delivery_weekdays.*.between'   => 'Ngày giao phải từ Thứ 2 (1) đến Chủ nhật (7).',
        ]);

        $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);

        $subscription = $this->subscriptionService->subscribe(
            Auth::guard('api')->user(),
            $plan,
            $validated,
        );

        return $this->success([
            'subscription' => $subscription->load('plan'),
        ], 'Đăng ký gói định kỳ thành công. Hệ thống sẽ tự sinh đơn theo lịch.', 201);
    }

    /**
     * DANH SÁCH GÓI CỦA CHÍNH KHÁCH.
     */
    public function mySubscriptions(): JsonResponse
    {
        $subscriptions = CustomerSubscription::query()
            ->with('plan:id,name,price,duration_days', 'orders:id,order_code,status,created_at,subscription_id')
            ->where('user_id', Auth::guard('api')->id())
            ->latest()
            ->get();

        return $this->success($subscriptions, 'Lấy danh sách gói của bạn thành công.');
    }

    /**
     * TẠM DỪNG GÓI.
     */
    public function pause(int $id): JsonResponse
    {
        $subscription = CustomerSubscription::where('user_id', Auth::guard('api')->id())->find($id);

        if ($subscription === null) {
            return $this->error('Không tìm thấy gói định kỳ.', 404);
        }

        if ($subscription->status !== CustomerSubscription::STATUS_ACTIVE) {
            return $this->error('Chỉ có thể tạm dừng gói đang hoạt động.', 422);
        }

        $subscription->status = CustomerSubscription::STATUS_PAUSED;
        $subscription->save();

        return $this->success($subscription, 'Đã tạm dừng gói định kỳ.');
    }

    /**
     * SALES DỜI MỘT KỲ GIAO (thiếu hàng / mất mùa).
     */
    public function skip(int $id): JsonResponse
    {
        $subscription = CustomerSubscription::find($id);

        if ($subscription === null) {
            return $this->error('Không tìm thấy gói định kỳ.', 404);
        }

        $subscription = $this->subscriptionService->skipDelivery(
            $subscription,
            Auth::guard('api')->user(),
        );

        return $this->success($subscription, 'Đã dời kỳ giao sang ngày kế tiếp.');
    }
}
