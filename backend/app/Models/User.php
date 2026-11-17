<?php

/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Model Người dùng.
 *           - Kế thừa Authenticatable để dùng chung cho cả web (session)
 *             và API (JWT qua jwt.auth middleware).
 *           - Cung cấp các hàm tiện ích kiểm tra vai trò: isAdmin(),
 *             isFarmStaff()... để Controller đọc dễ hiểu.
 *           - Định nghĩa quan hệ với đơn hàng, địa chỉ, gói định kỳ, điểm.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    // Các vai trò hợp lệ, khai báo hằng số để tránh gõ sai chuỗi
    public const ROLE_CUSTOMER        = 'customer';
    public const ROLE_FARM_STAFF      = 'farm_staff';
    public const ROLE_WAREHOUSE_STAFF = 'warehouse_staff';
    public const ROLE_SALES           = 'sales';
    public const ROLE_ADMIN           = 'admin';

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password'       => 'hashed',
            'loyalty_points' => 'integer',
        ];
    }

    // ------------------------------------------------------------------
    // Phần bắt buộc của gói JWT: cho biết dữ liệu nào được nhúng vào token
    // ------------------------------------------------------------------

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
        ];
    }

    // ------------------------------------------------------------------
    // Hàm kiểm tra vai trò - dùng trong Controller và Service
    // ------------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isSales(): bool
    {
        return $this->role === self::ROLE_SALES;
    }

    // ------------------------------------------------------------------
    // Quan hệ
    // ------------------------------------------------------------------

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CustomerSubscription::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }
}
