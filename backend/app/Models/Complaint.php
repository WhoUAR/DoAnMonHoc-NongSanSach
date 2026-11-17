<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Model Khiếu nại / đổi trả.
 *           `evidence_media` là mảng URL ảnh/video minh chứng trên Cloudinary.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    use HasFactory;

    public const STATUS_PENDING          = 'pending';
    public const STATUS_APPROVED_REFUND  = 'approved_refund';
    public const STATUS_APPROVED_REPLACE = 'approved_replace';
    public const STATUS_REJECTED         = 'rejected';

    protected $fillable = [
        'complaint_code',
        'order_id',
        'user_id',
        'reason',
        'description',
        'evidence_media',
        'status',
        'refund_amount',
        'staff_note',
        'handled_by',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence_media' => 'array',
            'refund_amount'  => 'decimal:0',
            'handled_at'     => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
