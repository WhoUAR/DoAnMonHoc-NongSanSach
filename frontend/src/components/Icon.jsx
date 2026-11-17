/**
 * NGƯỜI PHỤ TRÁCH: Đào Quốc Đạt (0306241190)
 * CHỨC NĂNG: Bộ icon dạng SVG tự vẽ, dùng thay emoji.
 *
 *   Vì sao tự vẽ thay vì dùng emoji hay thư viện ngoài:
 *   - Emoji hiển thị khác nhau trên mỗi máy và làm giao diện trông nghiệp dư.
 *   - Thư viện icon phải cài thêm, tăng dung lượng, trong khi dự án chỉ
 *     cần khoảng chục hình.
 *   - Tự vẽ bảo đảm mọi icon cùng nét (stroke 1.6) và cùng góc nhìn.
 *
 *   Cách dùng:  <Icon name="cart" size={20} />
 */

const PATHS = {
  // Giỏ hàng
  cart: (
    <>
      <path d="M2 3h2.2l2.4 11.2a1.6 1.6 0 0 0 1.6 1.3h7.9a1.6 1.6 0 0 0 1.6-1.2L19.5 7H5" />
      <circle cx="9" cy="19.5" r="1.4" />
      <circle cx="17" cy="19.5" r="1.4" />
    </>
  ),

  // Người dùng
  user: (
    <>
      <circle cx="12" cy="8" r="3.6" />
      <path d="M4.5 20a7.5 7.5 0 0 1 15 0" />
    </>
  ),

  // Mã QR
  qr: (
    <>
      <rect x="3" y="3" width="6.5" height="6.5" />
      <rect x="14.5" y="3" width="6.5" height="6.5" />
      <rect x="3" y="14.5" width="6.5" height="6.5" />
      <path d="M14.5 14.5h3v3h-3zM20.5 14.5h.5M20.5 20.5h.5M17.5 20.5h.5" />
    </>
  ),

  // Lô hàng / thùng
  batch: (
    <>
      <path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z" />
      <path d="M3 7.5 12 12l9-4.5M12 12v9" />
    </>
  ),

  // Đồng hồ / hạn dùng
  clock: (
    <>
      <circle cx="12" cy="12" r="8.6" />
      <path d="M12 7v5.2l3.3 2" />
    </>
  ),

  // Lá - nông sản
  leaf: (
    <>
      <path d="M20 4c0 9-5.4 13-10 13a5.6 5.6 0 0 1-5.6-5.6C4.4 7.2 10.6 4 20 4Z" />
      <path d="M4 20c2.2-4.4 5-7.2 9-9.4" />
    </>
  ),

  // Dấu kiểm
  check: <path d="m4.5 12.5 4.5 4.5L19.5 6.5" />,

  // Cảnh báo
  alert: (
    <>
      <path d="M12 3.5 21 19H3z" />
      <path d="M12 9.5v4M12 16.4v.6" />
    </>
  ),

  // Dấu X
  close: <path d="M6 6l12 12M18 6 6 18" />,

  // Mũi tên phải
  arrow: <path d="M4 12h15m0 0-5.5-5.5M19 12l-5.5 5.5" />,

  // Mũi tên trái
  back: <path d="M20 12H5m0 0 5.5-5.5M5 12l5.5 5.5" />,

  // Dấu cộng
  plus: <path d="M12 5v14M5 12h14" />,

  // Dấu trừ
  minus: <path d="M5 12h14" />,

  // Tìm kiếm
  search: (
    <>
      <circle cx="10.5" cy="10.5" r="6.5" />
      <path d="m15.5 15.5 4.5 4.5" />
    </>
  ),

  // Đơn hàng / hoá đơn
  receipt: (
    <>
      <path d="M5 3h14v18l-3.5-2-3.5 2-3.5-2L5 21z" />
      <path d="M9 8h6M9 12.5h6" />
    </>
  ),

  // Gói định kỳ / lịch
  calendar: (
    <>
      <rect x="3.5" y="5" width="17" height="15.5" rx="1" />
      <path d="M3.5 10h17M8 3v4M16 3v4" />
    </>
  ),

  // Biểu đồ / báo cáo
  chart: (
    <>
      <path d="M4 20V4M4 20h16" />
      <path d="M8 20v-6M13 20v-9M18 20v-4" />
    </>
  ),

  // Tải lên
  upload: (
    <>
      <path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5" />
      <path d="M4 16v3.5h16V16" />
    </>
  ),

  // Thùng rác
  trash: (
    <>
      <path d="M4 7h16M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13" />
      <path d="M10.5 11v5.5M13.5 11v5.5" />
    </>
  ),

  // Menu ba gạch
  menu: <path d="M4 7h16M4 12h16M4 17h16" />,

  // Sao
  star: (
    <path d="m12 3.8 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8L3.5 10l5.9-.9z" />
  ),
}

export default function Icon({ name, size = 20, className = '' }) {
  const paths = PATHS[name]

  if (!paths) {
    return null
  }

  return (
    <svg
      className={className}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.6"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
    >
      {paths}
    </svg>
  )
}
