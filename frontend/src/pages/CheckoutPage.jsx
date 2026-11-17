/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Trang thanh toán (đặt hàng).
 *
 *   HỖ TRỢ KHÁCH VÃNG LAI: không bắt buộc đăng nhập vẫn đặt được, đúng
 *   yêu cầu Must-have. Khách đã đăng nhập thì đơn được gắn với tài khoản
 *   và có thể dùng điểm tích luỹ.
 *
 *   Luồng:
 *     1. Điền thông tin người nhận + địa chỉ.
 *     2. Chọn thanh toán COD hoặc VNPay.
 *     3. Nếu đã đăng nhập: chọn dùng điểm tích luỹ.
 *     4. Gửi lên Backend -> nhận mã đơn -> sang trang đơn hàng.
 *     Nếu chọn VNPay: chuyển trình duyệt sang cổng thanh toán.
 */

import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { apiPost, readError, readFieldErrors } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import { useCart } from '../context/CartContext.jsx'
import { formatMoney, formatQuantity } from '../utils/format.js'
import './CheckoutPage.css'

const POINT_VALUE = 1000

export default function CheckoutPage() {
  const navigate = useNavigate()
  const { user } = useAuth()
  const { items, subtotal, clearCart } = useCart()

  const [form, setForm] = useState({
    receiver_name: user?.full_name ?? '',
    receiver_phone: user?.phone ?? '',
    shipping_address: '',
    note: '',
    payment_method: 'cod',
  })

  const [pointsUsed, setPointsUsed] = useState(0)
  const [errors, setErrors] = useState({})
  const [submitting, setSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState('')

  // Điểm tối đa có thể dùng: không vượt số điểm đang có và không vượt giá trị đơn.
  const maxPointsByAmount = Math.floor(subtotal / POINT_VALUE)
  const maxPoints = Math.min(user?.loyalty_points ?? 0, maxPointsByAmount)
  const discountAmount = pointsUsed * POINT_VALUE

  // Phí giao do Backend tính theo địa chỉ (theo km) nên ở đây chỉ hiện tạm tính.
  const total = Math.max(0, subtotal - discountAmount)

  /** Cập nhật một trường của biểu mẫu. */
  function setField(name, value) {
    setForm((current) => ({ ...current, [name]: value }))
    setErrors((current) => ({ ...current, [name]: undefined }))
  }

  /** Gửi đơn hàng lên Backend. */
  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setSubmitError('')
    setErrors({})

    const payload = {
      receiver_name: form.receiver_name,
      receiver_phone: form.receiver_phone,
      shipping_address: form.shipping_address,
      note: form.note || null,
      payment_method: form.payment_method,
      points_used: pointsUsed,
      items: items.map((item) => ({
        product_id: item.product_id,
        quantity: item.quantity,
      })),
    }

    try {
      const response = await apiPost('/orders', payload)
      const { order, payment_url: paymentUrl } = response.data

      clearCart()

      // VNPay: chuyển sang cổng thanh toán. Nếu đang chạy chế độ mô phỏng
      // thì đây là endpoint nội bộ, vẫn chạy đúng luồng.
      if (paymentUrl) {
        window.location.href = paymentUrl
        return
      }

      navigate(`/don-hang/${order.id}`, { replace: true })
    } catch (caught) {
      setErrors(readFieldErrors(caught))
      setSubmitError(readError(caught))
    } finally {
      setSubmitting(false)
    }
  }

  // Giỏ trống thì không cho vào thanh toán.
  if (items.length === 0) {
    return (
      <div className="wrap checkout">
        <div className="empty">
          <p className="empty__title">Chưa có gì để thanh toán</p>
          <p>Giỏ hàng của bạn đang trống.</p>
          <p style={{ marginTop: 'var(--s4)' }}>
            <Link to="/san-pham" className="btn btn--primary">
              Chọn nông sản
            </Link>
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="wrap checkout">
      <div className="checkout__head">
        <h1>Đặt hàng</h1>
        {!user && (
          <p className="checkout__guest-note">
            Bạn đang đặt hàng với tư cách khách vãng lai.{' '}
            <Link to="/dang-nhap">Đăng nhập</Link> để tích điểm cho đơn này.
          </p>
        )}
      </div>

      <form className="checkout__grid" onSubmit={handleSubmit} noValidate>
        {/* ---- Cột trái: biểu mẫu ---- */}
        <div className="checkout__form">
          <section className="checkout__section">
            <h2 className="checkout__section-title">Người nhận</h2>

            <div className="field">
              <label className="field__label" htmlFor="receiver_name">
                Họ tên người nhận
              </label>
              <input
                id="receiver_name"
                className={`input ${errors.receiver_name ? 'input--invalid' : ''}`}
                value={form.receiver_name}
                onChange={(event) => setField('receiver_name', event.target.value)}
                autoComplete="name"
              />
              {errors.receiver_name && (
                <p className="field__error">{errors.receiver_name}</p>
              )}
            </div>

            <div className="field">
              <label className="field__label" htmlFor="receiver_phone">
                Số điện thoại
              </label>
              <input
                id="receiver_phone"
                className={`input data ${errors.receiver_phone ? 'input--invalid' : ''}`}
                value={form.receiver_phone}
                onChange={(event) => setField('receiver_phone', event.target.value)}
                autoComplete="tel"
                inputMode="tel"
                placeholder="09xx xxx xxx"
              />
              {errors.receiver_phone && (
                <p className="field__error">{errors.receiver_phone}</p>
              )}
            </div>

            <div className="field">
              <label className="field__label" htmlFor="shipping_address">
                Địa chỉ giao hàng
              </label>
              <textarea
                id="shipping_address"
                className={`textarea ${errors.shipping_address ? 'textarea--invalid' : ''}`}
                value={form.shipping_address}
                onChange={(event) => setField('shipping_address', event.target.value)}
                placeholder="Số nhà, tên đường, phường/xã, quận/huyện, tỉnh/thành"
                autoComplete="street-address"
                rows={3}
              />
              {errors.shipping_address && (
                <p className="field__error">{errors.shipping_address}</p>
              )}
              <p className="field__hint">
                Ghi rõ tên quận/huyện để tính đúng phí giao.
              </p>
            </div>

            <div className="field">
              <label className="field__label" htmlFor="note">
                Ghi chú cho nông trại <span className="field__optional">(không bắt buộc)</span>
              </label>
              <input
                id="note"
                className="input"
                value={form.note}
                onChange={(event) => setField('note', event.target.value)}
                placeholder="Ví dụ: giao sau 17h, để trước cổng"
              />
            </div>
          </section>

          {/* ---- Thanh toán ---- */}
          <section className="checkout__section">
            <h2 className="checkout__section-title">Thanh toán</h2>

            <div className="pay-options">
              <label
                className={`pay-option ${form.payment_method === 'cod' ? 'is-active' : ''}`}
              >
                <input
                  type="radio"
                  name="payment_method"
                  value="cod"
                  checked={form.payment_method === 'cod'}
                  onChange={(event) => setField('payment_method', event.target.value)}
                />
                <span className="pay-option__body">
                  <span className="pay-option__name">Trả tiền khi nhận hàng</span>
                  <span className="pay-option__desc">
                    Thanh toán cho nhân viên giao hàng. Phù hợp đơn nội thành.
                  </span>
                </span>
              </label>

              <label
                className={`pay-option ${form.payment_method === 'vnpay' ? 'is-active' : ''}`}
              >
                <input
                  type="radio"
                  name="payment_method"
                  value="vnpay"
                  checked={form.payment_method === 'vnpay'}
                  onChange={(event) => setField('payment_method', event.target.value)}
                />
                <span className="pay-option__body">
                  <span className="pay-option__name">Chuyển khoản VNPay</span>
                  <span className="pay-option__desc">
                    Thanh toán qua cổng VNPay. Đơn được ưu tiên xử lý trước.
                  </span>
                </span>
              </label>
            </div>
          </section>

          {/* ---- Điểm tích luỹ: chỉ hiện với khách đã đăng nhập có điểm ---- */}
          {user && maxPoints > 0 && (
            <section className="checkout__section">
              <h2 className="checkout__section-title">Điểm tích luỹ</h2>

              <p className="checkout__points-note">
                Bạn có <strong className="data">{user.loyalty_points}</strong> điểm.
                Mỗi điểm giảm {formatMoney(POINT_VALUE)}.
              </p>

              <div className="points-row">
                <input
                  type="range"
                  min={0}
                  max={maxPoints}
                  step={1}
                  value={pointsUsed}
                  onChange={(event) => setPointsUsed(Number(event.target.value))}
                  className="points-row__slider"
                  aria-label="Số điểm muốn dùng"
                />

                <input
                  type="number"
                  className="input data points-row__input"
                  value={pointsUsed}
                  min={0}
                  max={maxPoints}
                  onChange={(event) => {
                    const value = Number(event.target.value)

                    setPointsUsed(Math.max(0, Math.min(value, maxPoints)))
                  }}
                  aria-label="Số điểm dùng để giảm giá"
                />

                <button
                  type="button"
                  className="btn btn--ghost btn--sm"
                  onClick={() => setPointsUsed(maxPoints)}
                >
                  Dùng tối đa
                </button>
              </div>

              {pointsUsed > 0 && (
                <p className="checkout__discount">
                  Giảm <span className="data">{formatMoney(discountAmount)}</span> cho đơn này.
                </p>
              )}
            </section>
          )}
        </div>

        {/* ---- Cột phải: tóm tắt đơn ---- */}
        <aside className="checkout__summary">
          <h2 className="checkout__summary-title">Đơn của bạn</h2>

          <ul className="checkout__items">
            {items.map((item) => (
              <li key={item.product_id} className="checkout__item">
                <span className="checkout__item-qty data">
                  {formatQuantity(item.quantity)}×
                </span>
                <span className="checkout__item-name">{item.name}</span>
                <span className="checkout__item-price data">
                  {formatMoney(item.price * item.quantity)}
                </span>
              </li>
            ))}
          </ul>

          <dl className="checkout__lines">
            <div className="checkout__line">
              <dt>Tiền hàng</dt>
              <dd className="data">{formatMoney(subtotal)}</dd>
            </div>
            <div className="checkout__line">
              <dt>Phí giao</dt>
              <dd className="data">Tính theo địa chỉ</dd>
            </div>
            {pointsUsed > 0 && (
              <div className="checkout__line checkout__line--discount">
                <dt>Dùng {pointsUsed} điểm</dt>
                <dd className="data">−{formatMoney(discountAmount)}</dd>
              </div>
            )}
          </dl>

          <div className="checkout__total">
            <span>Tạm tính</span>
            <strong className="data">{formatMoney(total)}</strong>
          </div>

          {submitError && (
            <p className="checkout__error" role="alert">
              <Icon name="alert" size={15} />
              {submitError}
            </p>
          )}

          <button
            type="submit"
            className="btn btn--primary btn--block"
            disabled={submitting}
          >
            {submitting ? 'Đang gửi đơn…' : 'Đặt hàng'}
          </button>

          <p className="checkout__note">
            Đơn sẽ được nhân viên duyệt trong giờ làm việc. Bạn nhận được mã đơn
            ngay sau khi đặt.
          </p>
        </aside>
      </form>
    </div>
  )
}
