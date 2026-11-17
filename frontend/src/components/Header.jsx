/**
 * NGƯỜI PHỤ TRÁCH: Đào Quốc Đạt (0306241190)
 * CHỨC NĂNG: Thanh điều hướng trên cùng, dùng chung cho mọi trang khách hàng.
 *
 *   - Logo chữ + dấu đóng gói.
 *   - Menu theo vai trò: khách thấy "Sản phẩm / Gói định kỳ / Truy xuất";
 *     nhân viên thấy thêm lối vào bảng điều khiển của mình.
 *   - Giỏ hàng có số lượng cập nhật trực tiếp.
 *   - Trên điện thoại: menu thu gọn thành nút ba gạch.
 *
 *   Nền xanh nông nghiệp đậm: đây là "tấm biển ngoài cổng nông trại", tách
 *   hẳn khỏi nền giấy của phần nội dung bên dưới.
 */

import { useState } from 'react'
import { Link, NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../context/AuthContext.jsx'
import { useCart } from '../context/CartContext.jsx'
import Icon from './Icon.jsx'
import './Header.css'

/** Vai trò nào thì vào bảng điều khiển nào. */
const STAFF_HOME = {
  farm_staff: { to: '/staff/farm', label: 'Nông trại' },
  warehouse_staff: { to: '/staff/warehouse', label: 'Kho hàng' },
  sales: { to: '/staff/sales', label: 'Đơn hàng' },
  admin: { to: '/staff/admin', label: 'Quản trị' },
}

export default function Header() {
  const { user, logout, hasRole } = useAuth()
  const { totalQuantity } = useCart()
  const navigate = useNavigate()
  const [menuOpen, setMenuOpen] = useState(false)

  const staffHome = STAFF_HOME[user?.role]

  async function handleLogout() {
    await logout()
    setMenuOpen(false)
    navigate('/')
  }

  function closeMenu() {
    setMenuOpen(false)
  }

  return (
    <header className="site-header">
      <div className="wrap site-header__bar">
        <Link to="/" className="site-header__logo" onClick={closeMenu}>
          <span className="site-header__mark" aria-hidden="true">
            NS
          </span>
          <span className="site-header__wordmark">
            Nông Sản Sạch
            <span className="site-header__sub">Vườn Củ Chi · giao trong ngày</span>
          </span>
        </Link>

        <nav
          className={`site-header__nav ${menuOpen ? 'is-open' : ''}`}
          aria-label="Điều hướng chính"
        >
          <NavLink to="/san-pham" className="site-header__link" onClick={closeMenu}>
            Sản phẩm
          </NavLink>
          <NavLink to="/goi-dinh-ky" className="site-header__link" onClick={closeMenu}>
            Gói định kỳ
          </NavLink>
          <NavLink to="/truy-xuat" className="site-header__link" onClick={closeMenu}>
            Truy xuất
          </NavLink>

          {staffHome && (
            <NavLink to={staffHome.to} className="site-header__link" onClick={closeMenu}>
              {staffHome.label}
            </NavLink>
          )}

          {hasRole('customer') && (
            <NavLink to="/don-hang" className="site-header__link" onClick={closeMenu}>
              Đơn của tôi
            </NavLink>
          )}
        </nav>

        <div className="site-header__actions">
          <Link
            to="/gio-hang"
            className="site-header__cart"
            aria-label={`Giỏ hàng, ${totalQuantity} món`}
          >
            <Icon name="cart" size={19} />
            <span className="site-header__cart-label">Giỏ</span>
            {totalQuantity > 0 && (
              <span className="site-header__count data">{totalQuantity}</span>
            )}
          </Link>

          {user ? (
            <div className="site-header__account">
              <span className="site-header__name">{user.full_name}</span>
              <button
                type="button"
                className="site-header__logout"
                onClick={handleLogout}
              >
                Đăng xuất
              </button>
            </div>
          ) : (
            <Link to="/dang-nhap" className="site-header__login" onClick={closeMenu}>
              Đăng nhập
            </Link>
          )}

          <button
            type="button"
            className="site-header__toggle"
            onClick={() => setMenuOpen((open) => !open)}
            aria-expanded={menuOpen}
            aria-label={menuOpen ? 'Đóng menu' : 'Mở menu'}
          >
            <Icon name={menuOpen ? 'close' : 'menu'} size={22} />
          </button>
        </div>
      </div>
    </header>
  )
}
