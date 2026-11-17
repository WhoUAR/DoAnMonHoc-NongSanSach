/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Bảng điều khiển QUẢN TRỊ VIÊN.
 *
 *   Bốn khối số liệu + tỷ lệ hao hụt + danh sách lô cận hạn.
 *   Đây là màn hình báo cáo thống kê thuộc nhóm Must-have.
 *
 *   THIẾT KẾ: bốn con số lớn xếp thành một dải sổ cái, KHÔNG dùng thẻ
 *   "icon + số + nhãn" kiểu dashboard SaaS. Chúng nằm chung một khung, kẻ
 *   dọc giữa, đọc như bảng tổng kết cuối trang sổ.
 */

import { useEffect, useState } from 'react'
import { apiGet } from '../../api/client.js'
import Icon from '../../components/Icon.jsx'
import { formatMoney, formatQuantity } from '../../utils/format.js'
import './StaffPages.css'

export default function AdminDashboard() {
  const [overview, setOverview] = useState(null)
  const [inventory, setInventory] = useState(null)
  const [topProducts, setTopProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    async function load() {
      try {
        const [overviewResponse, inventoryResponse, topResponse] = await Promise.all([
          apiGet('/admin/reports/overview'),
          apiGet('/admin/reports/inventory'),
          apiGet('/admin/reports/top-products?limit=5'),
        ])

        setOverview(overviewResponse.data)
        setInventory(inventoryResponse.data)
        setTopProducts(topResponse.data)
      } catch {
        setError('Không tải được báo cáo.')
      } finally {
        setLoading(false)
      }
    }

    load()
  }, [])

  if (loading) {
    return (
      <div className="staff">
        <div className="skeleton" style={{ height: 130, marginBottom: 24 }} />
        <div className="skeleton" style={{ height: 300 }} />
      </div>
    )
  }

  if (error) {
    return (
      <div className="staff">
        <div className="empty">
          <p className="empty__title">{error}</p>
          <p>Kiểm tra Backend đã chạy chưa.</p>
        </div>
      </div>
    )
  }

  const orders = overview?.orders ?? {}
  const totalOrders = Object.values(orders).reduce((sum, value) => sum + Number(value), 0)

  return (
    <div className="staff">
      <header className="staff__head">
        <div>
          <h1>Tổng quan</h1>
          <p className="staff__sub">
            Từ {new Date(overview.period.from).toLocaleDateString('vi-VN')} đến{' '}
            {new Date(overview.period.to).toLocaleDateString('vi-VN')}
          </p>
        </div>
      </header>

      {/* ---- Dải số liệu: một khung, kẻ dọc, đọc như bảng tổng kết ---- */}
      <section className="ledger-strip">
        <div className="ledger-strip__cell">
          <span className="data-label">Doanh thu đã giao</span>
          <span className="data ledger-strip__value">
            {formatMoney(overview.revenue)}
          </span>
          <span className="ledger-strip__note">
            Chỉ tính đơn đã giao thành công
          </span>
        </div>

        <div className="ledger-strip__cell">
          <span className="data-label">Tổng đơn trong kỳ</span>
          <span className="data ledger-strip__value">{totalOrders}</span>
          <span className="ledger-strip__note">
            {orders.pending ?? 0} đơn chờ duyệt
          </span>
        </div>

        <div className="ledger-strip__cell">
          <span className="data-label">Khách hàng</span>
          <span className="data ledger-strip__value">{overview.customers}</span>
          <span className="ledger-strip__note">Tài khoản đã đăng ký</span>
        </div>

        <div
          className={`ledger-strip__cell ${overview.loss.loss_rate > 5 ? 'ledger-strip__cell--alert' : ''}`}
        >
          <span className="data-label">Tỷ lệ hao hụt</span>
          <span className="data ledger-strip__value">{overview.loss.loss_rate}%</span>
          <span className="ledger-strip__note">
            {formatQuantity(overview.loss.disposed)} /{' '}
            {formatQuantity(overview.loss.imported)} đơn vị nhập
          </span>
        </div>
      </section>

      <div className="admin-grid">
        {/* ---- Sản phẩm bán chạy ---- */}
        <section className="admin-block">
          <h2 className="admin-block__title">
            <Icon name="chart" size={16} />
            Bán chạy nhất
          </h2>

          {topProducts.length === 0 ? (
            <p className="staff-table__muted">Chưa có dữ liệu bán hàng.</p>
          ) : (
            <table className="ledger">
              <thead>
                <tr>
                  <th scope="col">Sản phẩm</th>
                  <th scope="col" className="num">Đã bán</th>
                  <th scope="col" className="num">Doanh thu</th>
                </tr>
              </thead>
              <tbody>
                {topProducts.map((product) => (
                  <tr key={product.product_id}>
                    <td>{product.product_name}</td>
                    <td className="num data">{formatQuantity(product.total_quantity)}</td>
                    <td className="num data">{formatMoney(product.total_revenue)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>

        {/* ---- Lô cận hạn ---- */}
        <section className="admin-block">
          <h2 className="admin-block__title">
            <Icon name="alert" size={16} />
            Lô cận hạn cần xử lý
          </h2>

          {inventory.expiring_soon.length === 0 ? (
            <p className="staff-table__muted">Không có lô nào sắp hết hạn.</p>
          ) : (
            <ul className="expiry-list">
              {inventory.expiring_soon.map((batch) => (
                <li key={batch.batch_code} className="expiry-item">
                  <div>
                    <span className="data expiry-item__code">{batch.batch_code}</span>
                    <span className="expiry-item__name">{batch.product_name}</span>
                  </div>

                  <div className="expiry-item__right">
                    <span className="data">
                      {formatQuantity(batch.quantity_available)}
                    </span>
                    <span
                      className={`tag tag--${batch.days_until_expiry <= 2 ? 'danger' : 'warn'}`}
                    >
                      {batch.days_until_expiry} ngày
                    </span>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </div>
  )
}
