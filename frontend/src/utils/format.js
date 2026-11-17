/**
 * NGƯỜI PHỤ TRÁCH: Đào Quốc Đạt (0306241190)
 * CHỨC NĂNG: Định dạng số liệu hiển thị theo kiểu Việt Nam.
 *
 *   Đây là các hàm dùng chung cho toàn bộ giao diện. Tách riêng để mọi nơi
 *   hiển thị tiền và số lượng giống hệt nhau - sai một chỗ là sai cả hệ thống.
 */

/** 25000 -> "25.000đ" */
export function formatMoney(value) {
  const number = Number(value) || 0

  return `${number.toLocaleString('vi-VN', { maximumFractionDigits: 0 })}đ`
}

/**
 * 2.5 -> "2,5" ; 10 -> "10"
 * Nông sản bán theo kg nên cần hiện số lẻ, nhưng bỏ ".000" thừa.
 */
export function formatQuantity(value) {
  const number = Number(value) || 0

  return number.toLocaleString('vi-VN', { maximumFractionDigits: 3 })
}

/** 5 -> "5 kg" */
export function formatQuantityWithUnit(value, unit) {
  return `${formatQuantity(value)} ${unit || ''}`.trim()
}

/** "2026-11-17" -> "17/11/2026" */
export function formatDate(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return '—'
  }

  return date.toLocaleDateString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

/** "2026-11-17T10:30:00Z" -> "17/11/2026 17:30" */
export function formatDateTime(value) {
  if (!value) {
    return '—'
  }

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return '—'
  }

  return date.toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Số ngày còn lại đến hạn sử dụng, tính từ hôm nay.
 * Trả về số âm nếu đã quá hạn.
 */
export function daysUntil(dateValue) {
  if (!dateValue) {
    return null
  }

  const today = new Date()
  today.setHours(0, 0, 0, 0)

  const target = new Date(dateValue)
  target.setHours(0, 0, 0, 0)

  return Math.round((target - today) / 86400000)
}

/**
 * Trả về mức độ cảnh báo hạn dùng để chọn màu tem.
 *
 *   overdue / critical (<=2 ngày)  -> đỏ
 *   warning  (<=5 ngày)            -> vàng
 *   good     (còn xa)              -> xanh
 */
export function expiryLevel(dateValue) {
  const days = daysUntil(dateValue)

  if (days === null) {
    return 'good'
  }

  if (days <= 2) {
    return 'danger'
  }

  if (days <= 5) {
    return 'warn'
  }

  return 'good'
}

/** "Còn 2 ngày" / "Hết hạn hôm nay" / "Quá hạn 1 ngày" */
export function formatExpiry(days) {
  if (days === null || days === undefined) {
    return 'Không rõ hạn'
  }

  if (days < 0) {
    return `Quá hạn ${Math.abs(days)} ngày`
  }

  if (days === 0) {
    return 'Hết hạn hôm nay'
  }

  if (days === 1) {
    return 'Còn 1 ngày'
  }

  return `Còn ${days} ngày`
}

/** Nhãn tiếng Việt cho trạng thái đơn hàng. */
export const ORDER_STATUS_LABEL = {
  pending: 'Chờ duyệt',
  confirmed: 'Đã duyệt',
  shipping: 'Đang giao',
  delivered: 'Đã giao',
  cancelled: 'Đã huỷ',
}

/** Nhãn tiếng Việt cho trạng thái thanh toán. */
export const PAYMENT_STATUS_LABEL = {
  unpaid: 'Chưa thanh toán',
  paid: 'Đã thanh toán',
  refunded: 'Đã hoàn tiền',
}

/** Nhãn tiếng Việt cho trạng thái khiếu nại. */
export const COMPLAINT_STATUS_LABEL = {
  pending: 'Chờ xử lý',
  approved_refund: 'Duyệt hoàn tiền',
  approved_replace: 'Duyệt giao bù',
  rejected: 'Từ chối',
}

/** Nhãn tiếng Việt cho trạng thái lô hàng. */
export const BATCH_STATUS_LABEL = {
  growing: 'Đang canh tác',
  available: 'Đang bán',
  depleted: 'Hết hàng',
  expired: 'Quá hạn',
  disposed: 'Đã xuất hủy',
}

/** Nhãn tiếng Việt cho loại hoạt động trong nhật ký canh tác. */
export const ACTIVITY_LABEL = {
  planting: 'Gieo trồng',
  watering: 'Tưới nước',
  fertilizing: 'Bón phân',
  spraying: 'Phun thuốc sinh học',
  inspection: 'Kiểm tra sâu bệnh',
  harvesting: 'Thu hoạch',
}

/** Nhãn tiếng Việt cho lý do hủy hàng. */
export const DISPOSE_REASON_LABEL = {
  damaged: 'Dập nát',
  expired: 'Hết hạn',
  overripe: 'Chín quá',
  other: 'Khác',
}

/** Nhãn tiếng Việt cho lý do khiếu nại. */
export const COMPLAINT_REASON_LABEL = {
  damaged: 'Hàng bị dập nát',
  rotten: 'Hàng bị hỏng, thối',
  missing_item: 'Thiếu hàng',
  wrong_item: 'Giao sai hàng',
  other: 'Lý do khác',
}
