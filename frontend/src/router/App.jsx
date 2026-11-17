/**
 * NGƯỜI PHỤ TRÁCH: Đào Quốc Đạt (0306241190)
 * CHỨC NĂNG: Khai báo toàn bộ đường dẫn của ứng dụng React.
 *
 *   Bảo vệ theo vai trò:
 *     - `RequireAuth`  : bắt buộc đăng nhập.
 *     - `RequireRole`  : bắt buộc một trong các vai trò cho trước.
 *
 *   Nếu chưa đăng nhập mà vào trang cần quyền, chuyển sang trang đăng nhập
 *   và ghi nhớ trang định vào để quay lại sau khi đăng nhập xong.
 */

import { Navigate, Route, Routes, useLocation } from 'react-router-dom'
import { useAuth } from '../context/AuthContext.jsx'
import Header from '../components/Header.jsx'

import AuthPage from '../pages/AuthPage.jsx'
import CartPage from '../pages/CartPage.jsx'
import CheckoutPage from '../pages/CheckoutPage.jsx'
import ComplaintPage from '../pages/ComplaintPage.jsx'
import HomePage from '../pages/HomePage.jsx'
import NotFoundPage from '../pages/NotFoundPage.jsx'
import ProductDetailPage from '../pages/ProductDetailPage.jsx'
import ProductListPage from '../pages/ProductListPage.jsx'
import SubscriptionPage from '../pages/SubscriptionPage.jsx'
import TraceabilityPage from '../pages/TraceabilityPage.jsx'
import { OrderDetailPage, OrderListPage } from '../pages/OrdersPage.jsx'

import AdminDashboard from '../pages/staff/AdminDashboard.jsx'
import FarmDashboard from '../pages/staff/FarmDashboard.jsx'
import SalesDashboard from '../pages/staff/SalesDashboard.jsx'
import WarehouseDashboard from '../pages/staff/WarehouseDashboard.jsx'

/** Bắt buộc đăng nhập; chưa đăng nhập thì đưa sang trang đăng nhập. */
function RequireAuth({ children }) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) {
    return <PageLoader />
  }

  if (!user) {
    return <Navigate to="/dang-nhap" state={{ from: location.pathname }} replace />
  }

  return children
}

/** Bắt buộc một trong các vai trò được truyền vào. */
function RequireRole({ roles, children }) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) {
    return <PageLoader />
  }

  if (!user) {
    return <Navigate to="/dang-nhap" state={{ from: location.pathname }} replace />
  }

  if (!roles.includes(user.role)) {
    return <Navigate to="/khong-co-quyen" replace />
  }

  return children
}

/** Màn hình chờ trong lúc kiểm tra token. */
function PageLoader() {
  return (
    <div className="wrap" style={{ paddingBlock: 'var(--s9)' }}>
      <div className="skeleton" style={{ height: 24, width: 160 }} />
      <div className="skeleton" style={{ height: 200, marginTop: 24 }} />
    </div>
  )
}

export default function App() {
  return (
    <>
      <Header />

      <main>
        <Routes>
          {/* ---- Công khai ---- */}
          <Route path="/" element={<HomePage />} />
          <Route path="/san-pham" element={<ProductListPage />} />
          <Route path="/san-pham/:slug" element={<ProductDetailPage />} />
          <Route path="/goi-dinh-ky" element={<SubscriptionPage />} />
          <Route path="/truy-xuat" element={<TraceabilityPage />} />
          <Route path="/truy-xuat/:token" element={<TraceabilityPage />} />
          <Route path="/gio-hang" element={<CartPage />} />
          <Route path="/thanh-toan" element={<CheckoutPage />} />

          {/* ---- Đăng nhập / đăng ký ---- */}
          <Route path="/dang-nhap" element={<AuthPage mode="login" />} />
          <Route path="/dang-ky" element={<AuthPage mode="register" />} />

          {/* ---- Cần đăng nhập ---- */}
          <Route
            path="/don-hang"
            element={
              <RequireAuth>
                <OrderListPage />
              </RequireAuth>
            }
          />
          <Route
            path="/don-hang/:id"
            element={
              <RequireAuth>
                <OrderDetailPage />
              </RequireAuth>
            }
          />
          <Route
            path="/khieu-nai/:orderId"
            element={
              <RequireAuth>
                <ComplaintPage />
              </RequireAuth>
            }
          />

          {/* ---- Bảng điều khiển nhân viên ---- */}
          <Route
            path="/staff/farm"
            element={
              <RequireRole roles={['farm_staff', 'admin']}>
                <FarmDashboard />
              </RequireRole>
            }
          />
          <Route
            path="/staff/warehouse"
            element={
              <RequireRole roles={['warehouse_staff', 'admin']}>
                <WarehouseDashboard />
              </RequireRole>
            }
          />
          <Route
            path="/staff/sales"
            element={
              <RequireRole roles={['sales', 'admin']}>
                <SalesDashboard />
              </RequireRole>
            }
          />
          <Route
            path="/staff/admin"
            element={
              <RequireRole roles={['admin']}>
                <AdminDashboard />
              </RequireRole>
            }
          />

          {/* ---- Trang không có quyền và 404 ---- */}
          <Route
            path="/khong-co-quyen"
            element={
              <div className="wrap" style={{ paddingBlock: 'var(--s9)' }}>
                <div className="empty">
                  <p className="empty__title">Bạn không có quyền vào trang này</p>
                  <p>Tài khoản của bạn không được cấp quyền cho khu vực này.</p>
                </div>
              </div>
            }
          />
          <Route path="*" element={<NotFoundPage />} />
        </Routes>
      </main>

      <SiteFooter />
    </>
  )
}

/** Chân trang dùng chung. */
function SiteFooter() {
  return (
    <footer className="site-footer">
      <div className="wrap site-footer__inner">
        <div>
          <p className="site-footer__name">Nông Sản Sạch</p>
          <p className="site-footer__line">
            Vườn Củ Chi, TP.HCM · Giao nội thành trong ngày
          </p>
        </div>

        <p className="site-footer__line data">
          Đồ án môn học · Nhóm 8 · Cao đẳng Kỹ thuật Cao Thắng
        </p>
      </div>
    </footer>
  )
}
