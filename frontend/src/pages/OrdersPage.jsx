/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Trang danh sách đơn hàng của khách và chi tiết một đơn.
 *
 *   Hai component trong cùng file vì chúng dùng chung phần hiển thị trạng thái:
 *     - OrderListPage  : "Đơn của tôi"
 *     - OrderDetailPage: chi tiết, kèm các lô đã lấy (bằng chứng FEFO)
 *                        và nút khiếu nại nếu còn trong 24 giờ.
 */

import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { apiGet } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import {
  ORDER_STATUS_LABEL,
  PAYMENT_STATUS_LABEL,
  formatDateTime,
  formatMoney,
  formatQuantity,
} from '../utils/format.js'
import './OrdersPage.css'

/** Chọn màu tem theo trạng thái đơn. */
function statusTone(status) {
  if (status === 'delivered') return 'good'
  if (status === 'cancelled') return 'danger'
  if (status === 'pending') return 'warn'

  return 'neutral'
}

// ============================================================================
// DANH SÁCH ĐƠN
// ============================================================================

export function OrderListPage() {
  const [orders, setOrders] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    apiGet('/orders?per_page=20')
      .then((response) => setOrders(response.data))
      .catch(() => setError('Không tải được danh sách đơn hàng.'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) {
    return (
      <div className="wrap orders">
        <h1 className="orders__title">Đơn của tôi</h1>
        <div className="stack">
          {[0, 1, 2].map((index) => (
            <div key={index} className="skeleton" style={{ height: 96 }} />
          ))}
        </div>
      </div>
    )
  }

  if (error) {
    return (
      <div className="wrap orders">
        <h1 className="orders__title">Đơn của tôi</h1>
        <div className="empty">
          <p className="empty__title">Không tải được đơn hàng</p>
          <p>{error}</p>
        </div>
      </div>
    )
  }

  return (
    <div className="wrap orders">
      <div className="orders__head">
        <h1 className="orders__title">Đơn của tôi</h1>
        <p className="data orders__count">{orders.length} đơn</p>
      </div>

      {orders.length === 0 ? (
        <div className="empty">
          <p className="empty__title">Bạn chưa có đơn hàng nào</p>
          <p>Đơn đầu tiên sẽ xuất hiện ở đây ngay sau khi bạn đặt.</p>
          <p style={{ marginTop: 'var(--s4)' }}>
            <Link to="/san-pham" className="btn btn--primary">
              Chọn nông sản
            </Link>
          </p>
        </div>
      ) : (
        <div className="order-list">
          {orders.map((order) => (
            <Link
              key={order.id}
              to={`/don-hang/${order.id}`}
              className="order-row"
            >
              <div className="order-row__main">
                <span className="order-row__code data">{order.order_code}</span>
                <span className="order-row__date data">
                  {formatDateTime(order.created_at)}
                </span>
              </div>

              <span className={`tag tag--${statusTone(order.status)}`}>
                {ORDER_STATUS_LABEL[order.status] ?? order.status}
              </span>

              <span className="order-row__items data">
                {order.items_count} món
              </span>

              <span className="order-row__total data">
                {formatMoney(order.total_amount)}
              </span>

              <Icon name="arrow" size={16} className="order-row__arrow" />
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}

// ============================================================================
// CHI TIẾT ĐƠN
// ============================================================================

export function OrderDetailPage() {
  const { id } = useParams()

  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  useEffect(() => {
    apiGet(`/orders/${id}`)
      .then((response) => setOrder(response.data))
      .catch(() => setError('Không tìm thấy đơn hàng này.'))
      .finally(() => setLoading(false))
  }, [id])

  if (loading) {
    return (
      <div className="wrap orders">
        <div className="skeleton" style={{ height: 180, marginBottom: 24 }} />
        <div className="skeleton" style={{ height: 260 }} />
      </div>
    )
  }

  if (error || !order) {
    return (
      <div className="wrap orders">
        <div className="empty">
          <p className="empty__title">{error}</p>
          <p>
            <Link to="/don-hang">← Về danh sách đơn</Link>
          </p>
        </div>
      </div>
    )
  }

  // Còn trong 24 giờ kể từ lúc giao thì mới được khiếu nại.
  const deliveredAt = order.delivered_at ? new Date(order.delivered_at) : null
  const canComplain =
    order.status === 'delivered' &&
    deliveredAt !== null &&
    deliveredAt.getTime() + 24 * 3600 * 1000 > Date.now()

  return (
    <div className="wrap orders">
      <Link to="/don-hang" className="orders__back">
        <Icon name="back" size={15} />
        Đơn của tôi
      </Link>

      <div className="order-detail__head">
        <div>
          <h1 className="orders__title data">{order.order_code}</h1>
          <p className="order-detail__date data">
            Đặt lúc {formatDateTime(order.created_at)}
          </p>
        </div>

        <div className="order-detail__tags">
          <span className={`tag tag--${statusTone(order.status)}`}>
            {ORDER_STATUS_LABEL[order.status] ?? order.status}
          </span>
          <span
            className={`tag tag--${order.payment_status === 'paid' ? 'good' : 'neutral'}`}
          >
            {PAYMENT_STATUS_LABEL[order.payment_status] ?? order.payment_status}
          </span>
        </div>
      </div>

      <div className="order-detail__grid">
        <div>
          {/* ---- Các món ---- */}
          <section className="order-block">
            <h2 className="order-block__title">Món đã đặt</h2>

            <table className="ledger">
              <thead>
                <tr>
                  <th scope="col">Sản phẩm</th>
                  <th scope="col" className="num">SL</th>
                  <th scope="col" className="num">Đơn giá</th>
                  <th scope="col" className="num">Thành tiền</th>
                </tr>
              </thead>
              <tbody>
                {order.items?.map((item) => (
                  <tr key={item.id}>
                    <td>{item.product_name}</td>
                    <td className="num data">
                      {formatQuantity(item.quantity)} {item.product_unit}
                    </td>
                    <td className="num data">{formatMoney(item.unit_price)}</td>
                    <td className="num data">{formatMoney(item.line_total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>

          {/* ---- Bằng chứng FEFO: các lô đã lấy ---- */}
          {order.batch_allocations?.length > 0 && (
            <section className="order-block">
              <h2 className="order-block__title">Lô hàng đã xuất cho đơn này</h2>
              <p className="order-block__note">
                Kho tự lấy lô gần hết hạn trước. Đây là các lô thực tế trong túi
                hàng bạn nhận.
              </p>

              <div className="alloc-list">
                {order.batch_allocations.map((allocation) => (
                  <div key={allocation.id} className="alloc-row">
                    <span className="data alloc-row__code">
                      {allocation.batch?.batch_code ?? `Lô #${allocation.batch_id}`}
                    </span>
                    <span className="data alloc-row__expiry">
                      Hạn {allocation.batch?.expiry_date ?? '—'}
                    </span>
                    <span className="data alloc-row__qty">
                      {formatQuantity(allocation.quantity)}
                    </span>
                  </div>
                ))}
              </div>
            </section>
          )}
        </div>

        {/* ---- Cột phải ---- */}
        <aside>
          <section className="order-block order-block--kraft">
            <h2 className="order-block__title">Tổng tiền</h2>

            <dl className="order-totals">
              <div>
                <dt>Tiền hàng</dt>
                <dd className="data">{formatMoney(order.subtotal)}</dd>
              </div>
              <div>
                <dt>Phí giao</dt>
                <dd className="data">
                  {Number(order.shipping_fee) === 0
                    ? 'Miễn phí'
                    : formatMoney(order.shipping_fee)}
                </dd>
              </div>
              {Number(order.discount_amount) > 0 && (
                <div className="order-totals__discount">
                  <dt>Dùng {order.points_used} điểm</dt>
                  <dd className="data">−{formatMoney(order.discount_amount)}</dd>
                </div>
              )}
            </dl>

            <div className="order-totals__grand">
              <span>Thanh toán</span>
              <strong className="data">{formatMoney(order.total_amount)}</strong>
            </div>
          </section>

          <section className="order-block">
            <h2 className="order-block__title">Giao đến</h2>
            <p className="order-address">
              <strong>{order.receiver_name}</strong>
              <br />
              <span className="data">{order.receiver_phone}</span>
              <br />
              {order.shipping_address}
            </p>
            {order.note && (
              <p className="order-note">Ghi chú: {order.note}</p>
            )}
          </section>

          {canComplain && (
            <section className="order-block">
              <h2 className="order-block__title">Có vấn đề với hàng?</h2>
              <p className="order-block__note">
                Bạn có 24 giờ kể từ khi nhận hàng để gửi khiếu nại kèm ảnh hoặc
                video minh chứng.
              </p>
              <Link
                to={`/khieu-nai/${order.id}`}
                className="btn btn--outline btn--block"
              >
                Gửi khiếu nại
              </Link>
            </section>
          )}
        </aside>
      </div>
    </div>
  )
}
