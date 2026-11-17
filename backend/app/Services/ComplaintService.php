<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Service xử lý KHIẾU NẠI / ĐỔI TRẢ.
 *
 *   createComplaint(): kiểm tra 2 điều kiện bắt buộc trước khi cho gửi:
 *        1. Đơn phải đã giao và còn trong hạn 24 giờ.
 *        2. Phải có ít nhất một ảnh/video minh chứng.
 *      Đây chính là "luồng ngoại lệ" được chấm điểm trong đặc tả Tuần 2.
 *
 *   handle(): Sales duyệt hoàn tiền hoặc giao bù.
 */

namespace App\Services;

use App\Models\Complaint;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComplaintService
{
    /**
     * Số giờ tối đa được phép khiếu nại sau khi nhận hàng.
     */
    private const COMPLAINT_WINDOW_HOURS = 24;

    /**
     * KHÁCH GỬI KHIẾU NẠI.
     *
     * @param  array<int, string>  $evidenceMedia  Danh sách URL ảnh/video trên Cloudinary.
     *
     * @throws ValidationException khi vi phạm quy tắc nghiệp vụ.
     */
    public function createComplaint(
        User $customer,
        Order $order,
        string $reason,
        ?string $description,
        array $evidenceMedia
    ): Complaint {
        // --- Quy tắc 1: đơn hàng phải thuộc về chính khách này ---
        if ($order->user_id !== $customer->id) {
            throw ValidationException::withMessages([
                'order_id' => 'Bạn không có quyền khiếu nại đơn hàng này.',
            ]);
        }

        // --- Quy tắc 2: đơn đã giao và chưa quá 24 giờ ---
        if ($order->status !== Order::STATUS_DELIVERED) {
            throw ValidationException::withMessages([
                'order_id' => 'Chỉ có thể khiếu nại đơn hàng đã được giao.',
            ]);
        }

        if ($order->delivered_at === null
            || $order->delivered_at->copy()->addHours(self::COMPLAINT_WINDOW_HOURS)->isPast()) {
            throw ValidationException::withMessages([
                'order_id' => 'Đã quá 24 giờ kể từ khi nhận hàng nên không thể gửi khiếu nại.',
            ]);
        }

        // --- Quy tắc 3: bắt buộc có ảnh/video minh chứng ---
        if (empty($evidenceMedia)) {
            throw ValidationException::withMessages([
                'evidence_media' => 'Vui lòng đính kèm ít nhất một ảnh hoặc video làm minh chứng.',
            ]);
        }

        // --- Quy tắc 4: mỗi đơn chỉ mở một khiếu nại đang chờ xử lý ---
        $existing = Complaint::where('order_id', $order->id)
            ->whereIn('status', [Complaint::STATUS_PENDING])
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'order_id' => 'Đơn hàng này đã có một khiếu nại đang chờ xử lý.',
            ]);
        }

        return Complaint::create([
            'complaint_code' => $this->generateComplaintCode(),
            'order_id'       => $order->id,
            'user_id'        => $customer->id,
            'reason'         => $reason,
            'description'    => $description,
            'evidence_media' => $evidenceMedia,
            'status'         => Complaint::STATUS_PENDING,
        ]);
    }

    /**
     * SALES XỬ LÝ KHIẾU NẠI.
     *
     * @param  string  $decision  approved_refund | approved_replace | rejected
     */
    public function handle(
        Complaint $complaint,
        User $sales,
        string $decision,
        float $refundAmount = 0,
        ?string $staffNote = null
    ): Complaint {
        $allowed = [
            Complaint::STATUS_APPROVED_REFUND,
            Complaint::STATUS_APPROVED_REPLACE,
            Complaint::STATUS_REJECTED,
        ];

        if (! in_array($decision, $allowed, true)) {
            throw ValidationException::withMessages([
                'decision' => 'Quyết định xử lý không hợp lệ.',
            ]);
        }

        if ($complaint->status !== Complaint::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Khiếu nại này đã được xử lý trước đó.',
            ]);
        }

        return DB::transaction(function () use ($complaint, $sales, $decision, $refundAmount, $staffNote) {
            $complaint->status     = $decision;
            $complaint->staff_note = $staffNote;
            $complaint->handled_by = $sales->id;
            $complaint->handled_at = now();

            // Nếu duyệt hoàn tiền thì ghi nhận số tiền và cập nhật thanh toán.
            if ($decision === Complaint::STATUS_APPROVED_REFUND) {
                $complaint->refund_amount = $refundAmount > 0
                    ? $refundAmount
                    : (float) $complaint->order->total_amount;

                $payment = Payment::where('order_id', $complaint->order_id)->first();

                if ($payment !== null) {
                    $payment->status = Payment::STATUS_REFUNDED;
                    $payment->save();
                }

                $complaint->order->payment_status = 'refunded';
                $complaint->order->save();
            }

            $complaint->save();

            return $complaint->fresh(['order']);
        });
    }

    private function generateComplaintCode(): string
    {
        $prefix = 'CMP-' . now()->format('Ymd') . '-';

        $todayCount = Complaint::whereDate('created_at', now()->toDateString())->count() + 1;

        return $prefix . str_pad((string) $todayCount, 3, '0', STR_PAD_LEFT);
    }
}
