<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thanh Liêm (0306241214)
 * CHỨC NĂNG: Model Nhật ký canh tác.
 *           Mỗi dòng là một hoạt động của Nhân viên nông trại trên một Lô.
 *           Toàn bộ các dòng này được gom lại thành dòng thời gian
 *           hiển thị khi khách quét mã QR truy xuất nguồn gốc.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmingLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'created_by',
        'activity_type',
        'activity_date',
        'title',
        'description',
        'media',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'media'         => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
