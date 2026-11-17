/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Trang gói rau định kỳ.
 *
 *   Hiển thị các gói nông trại đang bán: giá, thời hạn, lịch giao, và danh
 *   sách sản phẩm trong mỗi lần giao. Khách bấm "Đăng ký gói" để mở biểu mẫu.
 *
 *   Điểm nhấn thiết kế: lịch giao hàng được vẽ thành các ô thứ trong tuần,
 *   đánh dấu những ngày có giao - đọc nhanh hơn một câu văn.
 */

import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { apiGet, apiPost, readError, readFieldErrors } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import { formatMoney, formatQuantity } from '../utils/format.js'
import './SubscriptionPage.css'

// Thứ trong tuần theo quy ước của Backend: 1 = Thứ 2 ... 7 = Chủ nhật.
const WEEKDAYS = [
  { value: 2, short: 'T2', full: 'Thứ 2' },
  { value: 3, short: 'T3', full: 'Thứ 3' },
  { value: 4, short: 'T4', full: 'Thứ 4' },
  { value: 5, short: 'T5', full: 'Thứ 5' },
  { value: 6, short: 'T6', full: 'Thứ 6' },
  { value: 7, short: 'T7', full: 'Thứ 7' },
  { value: 1, short: 'CN', full: 'Chủ nhật' },
]

export default function SubscriptionPage() {
  const { user } = useAuth()
  const navigate = useNavigate()

  const [plans, setPlans] = useState([])
  const [mySubscriptions, setMySubscriptions] = useState([])
  const [loading, setLoading] = useState(true)
  const [selectedPlan, setSelectedPlan] = useState(null)

  useEffect(() => {
    async function load() {
      try {
        const response = await apiGet('/subscription-plans')
        setPlans(response.data)

        // Chỉ khách đã đăng nhập mới xem được gói của mình.
        if (user) {
          const mine = await apiGet('/my-subscriptions').catch(() => null)

          if (mine?.data) {
            setMySubscriptions(mine.data)
          }
        }
      } finally {
        setLoading(false)
      }
    }

    load()
  }, [user])

  return (
    <div className="wrap subscription">
      <div className="subscription__intro">
        <h1>Gói rau định kỳ</h1>
        <p>
          Trả trước một tháng, nông trại giao đúng ngày cố định trong tuần. Bạn
          không phải đặt lại từng lần, và nông trại chủ động được lượng thu
          hoạch theo mùa.
        </p>
      </div>

      {/* ---- Gói đang dùng của khách ---- */}
      {mySubscriptions.length > 0 && (
        <section className="my-subs">
          <h2 className="my-subs__title">Gói của bạn</h2>

          {mySubscriptions.map((subscription) => (
            <div key={subscription.id} className="my-sub">
              <div className="my-sub__main">
                <span className="data my-sub__code">{subscription.subscription_code}</span>
                <h3>{subscription.plan?.name}</h3>
                <p className="data my-sub__dates">
                  {new Date(subscription.start_date).toLocaleDateString('vi-VN')} —{' '}
                  {new Date(subscription.end_date).toLocaleDateString('vi-VN')}
                </p>
              </div>

              <div className="my-sub__next">
                <span className="data-label">Kỳ giao tới</span>
                <span className="data">
                  {subscription.next_delivery_date
                    ? new Date(subscription.next_delivery_date).toLocaleDateString('vi-VN')
                    : '—'}
                </span>
              </div>

              <span
                className={`tag tag--${subscription.status === 'active' ? 'good' : 'neutral'}`}
              >
                {subscription.status === 'active' ? 'Đang chạy' : subscription.status}
              </span>
            </div>
          ))}
        </section>
      )}

      {/* ---- Danh sách gói ---- */}
      {loading ? (
        <div className="plan-grid">
          {[0, 1].map((index) => (
            <div key={index} className="skeleton" style={{ height: 380 }} />
          ))}
        </div>
      ) : (
        <div className="plan-grid">
          {plans.map((plan, index) => (
            <PlanCard
              key={plan.id}
              plan={plan}
              featured={index === 0}
              onSelect={() => {
                if (!user) {
                  navigate('/dang-nhap', { state: { from: '/goi-dinh-ky' } })
                  return
                }

                setSelectedPlan(plan)
              }}
            />
          ))}
        </div>
      )}

      {/* ---- Biểu mẫu đăng ký ---- */}
      {selectedPlan && (
        <SubscribeForm
          plan={selectedPlan}
          user={user}
          onClose={() => setSelectedPlan(null)}
          onDone={() => {
            setSelectedPlan(null)
            window.location.reload()
          }}
        />
      )}
    </div>
  )
}

// ============================================================================
// THẺ GÓI
// ============================================================================

function PlanCard({ plan, featured, onSelect }) {
  const weekdays = plan.delivery_weekdays ?? []

  return (
    <article className={`plan-card ${featured ? 'plan-card--featured' : ''}`}>
      {featured && <span className="plan-card__badge">Phổ biến nhất</span>}

      <header className="plan-card__head">
        <h2 className="plan-card__name">{plan.name}</h2>
        <p className="plan-card__desc">{plan.description}</p>
      </header>

      <div className="plan-card__price">
        <span className="data">{formatMoney(plan.price)}</span>
        <span className="plan-card__period">/ {plan.duration_days} ngày</span>
      </div>

      {/* Lịch giao: các ô thứ, ô có giao thì tô đậm */}
      <div className="plan-card__schedule">
        <span className="data-label">Lịch giao hằng tuần</span>
        <div className="weekday-row">
          {WEEKDAYS.map((day) => (
            <span
              key={day.value}
              className={`weekday ${weekdays.includes(day.value) ? 'is-active' : ''}`}
              title={day.full}
            >
              {day.short}
            </span>
          ))}
        </div>
      </div>

      <div className="plan-card__items">
        <span className="data-label">Mỗi lần giao gồm</span>
        <ul>
          {(plan.items ?? []).map((item) => (
            <li key={item.id}>
              <span className="data">{formatQuantity(item.quantity)}</span>{' '}
              {item.product?.unit} {item.product?.name}
            </li>
          ))}
        </ul>
      </div>

      <button type="button" className="btn btn--primary btn--block" onClick={onSelect}>
        Đăng ký gói này
        <Icon name="arrow" size={16} />
      </button>
    </article>
  )
}

// ============================================================================
// BIỂU MẪU ĐĂNG KÝ
// ============================================================================

function SubscribeForm({ plan, user, onClose, onDone }) {
  const [form, setForm] = useState({
    receiver_name: user?.full_name ?? '',
    receiver_phone: user?.phone ?? '',
    shipping_address: '',
    start_date: new Date().toISOString().slice(0, 10),
    payment_method: 'vnpay',
  })

  const [weekdays, setWeekdays] = useState(plan.delivery_weekdays ?? [])
  const [errors, setErrors] = useState({})
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  function setField(name, value) {
    setForm((current) => ({ ...current, [name]: value }))
  }

  function toggleWeekday(value) {
    setWeekdays((current) =>
      current.includes(value)
        ? current.filter((day) => day !== value)
        : [...current, value].sort(),
    )
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    setErrors({})

    try {
      await apiPost('/subscriptions', {
        subscription_plan_id: plan.id,
        start_date: form.start_date,
        delivery_weekdays: weekdays,
        receiver_name: form.receiver_name,
        receiver_phone: form.receiver_phone,
        shipping_address: form.shipping_address,
        payment_method: form.payment_method,
      })

      onDone()
    } catch (caught) {
      setErrors(readFieldErrors(caught))
      setError(readError(caught))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="sub-title">
      <div className="modal">
        <div className="modal__head">
          <h2 id="sub-title">Đăng ký {plan.name}</h2>
          <button type="button" className="modal__close" onClick={onClose} aria-label="Đóng">
            <Icon name="close" size={20} />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="modal__body" noValidate>
          <div className="field">
            <label className="field__label" htmlFor="sub-name">Người nhận</label>
            <input
              id="sub-name"
              className="input"
              value={form.receiver_name}
              onChange={(event) => setField('receiver_name', event.target.value)}
            />
            {errors.receiver_name && <p className="field__error">{errors.receiver_name}</p>}
          </div>

          <div className="field">
            <label className="field__label" htmlFor="sub-phone">Số điện thoại</label>
            <input
              id="sub-phone"
              className="input data"
              value={form.receiver_phone}
              onChange={(event) => setField('receiver_phone', event.target.value)}
              inputMode="tel"
            />
            {errors.receiver_phone && <p className="field__error">{errors.receiver_phone}</p>}
          </div>

          <div className="field">
            <label className="field__label" htmlFor="sub-address">Địa chỉ giao</label>
            <textarea
              id="sub-address"
              className="textarea"
              rows={2}
              value={form.shipping_address}
              onChange={(event) => setField('shipping_address', event.target.value)}
              placeholder="Số nhà, đường, phường, quận"
            />
            {errors.shipping_address && (
              <p className="field__error">{errors.shipping_address}</p>
            )}
          </div>

          <div className="field">
            <label className="field__label" htmlFor="sub-start">Bắt đầu từ ngày</label>
            <input
              id="sub-start"
              type="date"
              className="input data"
              value={form.start_date}
              onChange={(event) => setField('start_date', event.target.value)}
            />
          </div>

          <div className="field">
            <span className="field__label">Ngày giao trong tuần</span>
            <div className="weekday-row weekday-row--selectable">
              {WEEKDAYS.map((day) => (
                <button
                  key={day.value}
                  type="button"
                  className={`weekday weekday--button ${weekdays.includes(day.value) ? 'is-active' : ''}`}
                  onClick={() => toggleWeekday(day.value)}
                  aria-pressed={weekdays.includes(day.value)}
                >
                  {day.short}
                </button>
              ))}
            </div>
            <p className="field__hint">Chọn ít nhất một ngày.</p>
          </div>

          <div className="field">
            <span className="field__label">Thanh toán</span>
            <div className="pay-options">
              <label className={`pay-option ${form.payment_method === 'vnpay' ? 'is-active' : ''}`}>
                <input
                  type="radio"
                  name="sub-payment"
                  value="vnpay"
                  checked={form.payment_method === 'vnpay'}
                  onChange={(event) => setField('payment_method', event.target.value)}
                />
                <span className="pay-option__body">
                  <span className="pay-option__name">VNPay</span>
                  <span className="pay-option__desc">Trả trước cả tháng</span>
                </span>
              </label>

              <label className={`pay-option ${form.payment_method === 'cod' ? 'is-active' : ''}`}>
                <input
                  type="radio"
                  name="sub-payment"
                  value="cod"
                  checked={form.payment_method === 'cod'}
                  onChange={(event) => setField('payment_method', event.target.value)}
                />
                <span className="pay-option__body">
                  <span className="pay-option__name">Trả khi nhận</span>
                  <span className="pay-option__desc">Trả kỳ đầu tiên</span>
                </span>
              </label>
            </div>
          </div>

          {error && (
            <p className="auth__error" role="alert">
              <Icon name="alert" size={15} />
              {error}
            </p>
          )}

          <div className="modal__actions">
            <button type="button" className="btn btn--outline" onClick={onClose}>
              Huỷ
            </button>
            <button
              type="submit"
              className="btn btn--primary"
              disabled={submitting || weekdays.length === 0}
            >
              {submitting ? 'Đang gửi…' : `Đăng ký · ${formatMoney(plan.price)}`}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
