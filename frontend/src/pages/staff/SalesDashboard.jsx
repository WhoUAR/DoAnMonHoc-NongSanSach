/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Bảng điều khiển của NHÂN VIÊN BÁN HÀNG (Sales).
 *
 *   Công việc chính trong ngày:
 *     1. Xem đơn đang chờ duyệt và bấm duyệt (lúc này FEFO chạy, trừ kho).
 *     2. Chuyển đơn sang đang giao, rồi xác nhận đã giao.
 *     3. Theo dõi cảnh báo lô sắp hết hạn để đề xuất xả hàng.
 *
 *   THIẾT KẾ: đây là màn hình "Operate" - nhân viên dùng nhanh, nhiều lần
 *   trong ngày. Vì vậy bảng dày, mỗi dòng có nút thao tác ngay, không cần
 *   mở trang chi tiết. Cột trạng thái dùng màu tem để quét bằng mắt.
 */

import { useEffect, useState } from 'react'
import { apiGet, apiPost, readError } from '../../api/client.js'
import Icon from '../../components/Icon.jsx'
import {
  ORDER_STATUS_LABEL,
  formatDateTime,
  formatMoney,
} from '../../utils/format.js'
import './StaffPages.css'

const TABS = [
  { key: 'pending', label: 'Chờ duyệt' },
  { key: 'confirmed', label: 'Đã duyệt' },
  { key: 'shipping', label: 'Đang giao' },
  { key: 'delivered', label: 'Đã giao' },
  { key: '', label: 'Tất cả' },
]

export default function SalesDashboard() {
  const [tab, setTab] = useState('pending')
  const [orders, setOrders] = useState([])
  const [expiring, setExpiring] = useState([])
  const [loading, setLoading] = useState(true)
  const [busyId, setBusyId] = useState(null)
  const [message, setMessage] = useState(null)

  /** Tải đơn theo tab đang chọn. */
  async function loadOrders(activeTab = tab) {
    setLoading(true)

    try {
      const query = activeTab ? `?status=${activeTab}` : ''
      const response = await apiGet(`/sales/orders${query}`)
      setOrders(response.data)
    } catch {
      setMessage({ type: 'error', text: 'Không tải được danh sách đơn.' })
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadOrders(tab)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tab])

  // Cảnh báo lô cận hạn chỉ cần tải một lần.
  useEffect(() => {
    apiGet('/batches-expiring-soon?days=3')
      .then((response) => setExpiring(response.data))
      .catch(() => setExpiring([]))
  }, [])

  /**
   * Gọi một thao tác trên đơn (duyệt / giao / hoàn tất).
   * Sau khi thành công thì tải lại danh sách.
   */
  async function runAction(orderId, action, label) {
    setBusyId(orderId)
    setMessage(null)

    try {
      await apiPost(`/sales/orders/${orderId}/${action}`)
      setMessage({ type: 'ok', text: `${label} thành công.` })
      await loadOrders(tab)
    } catch (error) {
      setMessage({ type: 'error', text: readError(error) })
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div className="staff">
      <header className="staff__head">
        <div>
          <h1>Đơn hàng</h1>
          <p className="staff__sub">Duyệt đơn và điều phối giao hàng</p>
        </div>
      </header>

      {/* ---- Cảnh báo lô cận hạn ---- */}
      {expiring.length > 0 && (
        <div className="alert-strip">
          <Icon name="alert" size={17} />
          <div>
            <strong className="data">{expiring.length} lô sắp hết hạn</strong>
            <span>
              {expiring
                .slice(0, 3)
                .map((batch) => `${batch.product_name} (còn ${batch.days_until_expiry} ngày)`)
                .join(' · ')}
            </span>
          </div>
        </div>
      )}

      {message && (
        <p className={`staff__message staff__message--${message.type}`} role="status">
          {message.text}
        </p>
      )}

      {/* ---- Tab trạng thái ---- */}
      <div className="tabs" role="tablist">
        {TABS.map((item) => (
          <button
            key={item.key || 'all'}
            type="button"
            role="tab"
            aria-selected={tab === item.key}
            className={`tabs__tab ${tab === item.key ? 'is-active' : ''}`}
            onClick={() => setTab(item.key)}
          >
            {item.label}
          </button>
        ))}
      </div>

      {/* ---- Bảng đơn ---- */}
      {loading ? (
        <div className="stack">
          {[0, 1, 2].map((index) => (
            <div key={index} className="skeleton" style={{ height: 72 }} />
          ))}
        </div>
      ) : orders.length === 0 ? (
        <div className="empty">
          <p className="empty__title">Không có đơn nào ở mục này</p>
          <p>Chuyển sang tab khác để xem.</p>
        </div>
      ) : (
        <div className="table-wrap">
          <table className="ledger staff-table">
            <thead>
              <tr>
                <th scope="col">Mã đơn</th>
                <th scope="col">Người nhận</th>
                <th scope="col">Đặt lúc</th>
                <th scope="col" className="num">Tiền</th>
                <th scope="col">Trạng thái</th>
                <th scope="col">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              {orders.map((order) => (
                <tr key={order.id}>
                  <td className="data staff-table__code">{order.order_code}</td>
                  <td>
                    <div className="staff-table__person">
                      <span>{order.receiver_name}</span>
                      <span className="data staff-table__phone">
                        {order.receiver_phone}
                      </span>
                    </div>
                  </td>
                  <td className="data staff-table__date">
                    {formatDateTime(order.created_at)}
                  </td>
                  <td className="num data">{formatMoney(order.total_amount)}</td>
                  <td>
                    <span className={`tag tag--${statusTone(order.status)}`}>
                      {ORDER_STATUS_LABEL[order.status] ?? order.status}
                    </span>
                  </td>
                  <td>
                    <OrderActions
                      order={order}
                      busy={busyId === order.id}
                      onAction={runAction}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

/** Chọn màu tem theo trạng thái đơn. */
function statusTone(status) {
  if (status === 'delivered') return 'good'
  if (status === 'cancelled') return 'danger'
  if (status === 'pending') return 'warn'

  return 'neutral'
}

/**
 * Nút thao tác phù hợp với từng trạng thái đơn.
 * Chỉ hiện nút còn ý nghĩa, tránh nhân viên bấm nhầm.
 */
function OrderActions({ order, busy, onAction }) {
  if (order.status === 'pending') {
    return (
      <button
        type="button"
        className="btn btn--primary btn--sm"
        disabled={busy}
        onClick={() => onAction(order.id, 'confirm', 'Duyệt đơn')}
      >
        {busy ? 'Đang duyệt…' : 'Duyệt đơn'}
      </button>
    )
  }

  if (order.status === 'confirmed') {
    return (
      <button
        type="button"
        className="btn btn--solid btn--sm"
        disabled={busy}
        onClick={() => onAction(order.id, 'ship', 'Chuyển giao')}
      >
        Giao hàng
      </button>
    )
  }

  if (order.status === 'shipping') {
    return (
      <button
        type="button"
        className="btn btn--outline btn--sm"
        disabled={busy}
        onClick={() => onAction(order.id, 'deliver', 'Xác nhận giao')}
      >
        Đã giao
      </button>
    )
  }

  return <span className="staff-table__muted">—</span>
}
