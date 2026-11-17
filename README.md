# Nông Sản Sạch — Website TMĐT nông sản

Đồ án môn học · Nhóm 8 · CĐ CNTT 24WebC · Trường Cao đẳng Kỹ thuật Cao Thắng

Nền tảng thương mại điện tử khép kín cho **một nông trại**: bán rau củ tươi trực tiếp
đến người tiêu dùng TP.HCM, quản lý tồn kho theo **lô có hạn sử dụng**, xuất kho theo
nguyên tắc **FEFO**, và cho khách **quét mã QR** để xem toàn bộ nhật ký canh tác.

---

## Công nghệ

| Lớp | Công nghệ |
|---|---|
| Backend | Laravel 12 (PHP 8.2+) · REST API · JWT |
| Frontend | React 19 · Vite · React Router |
| Database | MySQL 8.4 (utf8mb4) |
| Thanh toán | VNPay sandbox (có chế độ mô phỏng khi chưa có API key) |
| Lưu trữ ảnh | Cloudinary (nhận URL, upload chưa nối) |

---

## Chạy nhanh

**Backend**

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Sửa `.env` cho khớp MySQL của bạn:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nongsan_sach
DB_USERNAME=root
DB_PASSWORD=
```

Rồi:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

**Frontend**

```bash
cd frontend
npm install
npm run dev
```

Mở `http://localhost:5173`.

### Tài khoản mẫu (mật khẩu `12345678`)

| Email | Vai trò |
|---|---|
| `admin@nongsan.vn` | Quản trị viên |
| `kho@nongsan.vn` | Nhân viên kho |
| `sales@nongsan.vn` | Nhân viên bán hàng |
| `nongtrai@nongsan.vn` | Nhân viên nông trại |
| `khachhang@nongsan.vn` | Khách hàng (120 điểm) |

---

## Thử thuật toán FEFO trong 3 phút

Dữ liệu mẫu đã dựng sẵn hai lô cà chua bi theo đúng ví dụ trong tài liệu Tuần 2:

- `LO-…-001`: **2 kg**, còn 2 ngày
- `LO-…-002`: **10 kg**, còn 7 ngày

1. Mở `http://localhost:5173/san-pham/ca-chua-bi` → đặt **5 kg**, thanh toán khi nhận.
2. Đăng nhập `sales@nongsan.vn` → vào **Đơn hàng** → bấm **Duyệt đơn**.
3. Màn hình báo duyệt thành công. Kiểm tra database:

```sql
SELECT batch_id, quantity FROM order_batch_allocations WHERE order_id = 1;
```

Kết quả phải là **2 kg ở lô 001** và **3 kg ở lô 002** — hệ thống tự lấy lô cận hạn
trước, rồi chia sang lô kế tiếp cho đủ.

4. Xem lại lô 001: đã về 0 và chuyển trạng thái `depleted`.

---

## Cấu trúc nghiệp vụ lõi

```
Đặt hàng        OrderController → OrderService::placeOrder()
                  → kiểm tra tồn kho khả dụng → tính phí ship → lưu đơn (pending)

Duyệt đơn       SalesOrderController → OrderService::confirmOrder()
                  → InventoryService::planFefoAllocation()   [chọn lô, có thể chia nhiều lô]
                  → InventoryService::decreaseStock()        [trừ kho + ghi thẻ kho]
                  → lưu order_batch_allocations              [bằng chứng FEFO]

Giao hàng       OrderController → OrderService::markDelivered()
                  → cộng điểm tích luỹ → đơn COD chuyển paid

Xuất hủy        BatchController → InventoryService::disposeStock()
                  → trừ kho + ghi thẻ kho loại dispose → tính tỷ lệ hao hụt

Truy xuất QR    TraceabilityController → TraceabilityService::findByToken()
                  → trả hồ sơ lô + toàn bộ dòng thời gian nhật ký (công khai)
```

---

## Những phần chưa làm

Ghi rõ để không ai tưởng là đã xong:

- **Upload Cloudinary**: hiện nhận URL ảnh do người dùng dán vào, chưa có nút chọn tệp.
- **Lệnh tự sinh đơn gói định kỳ**: `SubscriptionService::generateDueOrders()` đã viết,
  chưa có Artisan Command và lịch chạy.
- **Màn quản lý người dùng của Admin**: API có, chưa có giao diện.
- **Màn khiếu nại cho Sales**: API có, chưa có giao diện.
- **Biểu đồ doanh thu**: API `reports/revenue` có, màn Admin mới hiện số tổng.
