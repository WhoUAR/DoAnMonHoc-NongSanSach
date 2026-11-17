/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Trang gửi khiếu nại / đổi trả cho một đơn đã giao.
 *
 *   Quy tắc nghiệp vụ thể hiện ngay trên giao diện:
 *     - Chỉ mở được với đơn đã giao và còn trong 24 giờ (Backend kiểm tra lại).
 *     - BẮT BUỘC đính kèm ít nhất một ảnh/video minh chứng.
 *
 *   LƯU Ý VỀ TẢI ẢNH: đồ án chưa tích hợp Cloudinary upload trực tiếp từ
 *   trình duyệt, nên form này nhận ĐƯỜNG DẪN ảnh. Khi nhóm hoàn thiện
 *   Cloudinary, chỉ cần thay ô nhập bằng nút chọn tệp và gọi API upload,
 *   phần còn lại giữ nguyên.
 */

import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { apiGet, apiPost, readError, readFieldErrors } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import {
  COMPLAINT_REASON_LABEL,
  formatMoney,
} from '../utils/format.js'
import './ComplaintPage.css'

export default function ComplaintPage() {
  const { orderId } = useParams()
  const navigate = useNavigate()

  const [order, setOrder] = useState(null)
  const [loading, setLoading] = useState(true)
  const [loadError, setLoadError] = useState('')

  const [form, setForm] = useState({
    reason: 'damaged',
    description: '',
  })
  const [mediaInput, setMediaInput] = useState('')
  const [mediaList, setMediaList] = useState([])
  const [errors, setErrors] = useState({})
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  useEffect(() => {
    apiGet(`/orders/${orderId}`)
      .then((response) => setOrder(response.data))
      .catch(() => setLoadError('Không tìm thấy đơn hàng này.'))
      .finally(() => setLoading(false))
  }, [orderId])

  /** Thêm một đường dẫn ảnh/video vào danh sách minh chứng. */
  function addMedia() {
    const url = mediaInput.trim()

    if (!url || mediaList.includes(url) || mediaList.length >= 5) {
      return
    }

    setMediaList((current) => [...current, url])
    setMediaInput('')
    setErrors((current) => ({ ...current, evidence_media: undefined }))
  }

  /** Kiểm tra sơ bộ URL trước khi thêm, tránh khách dán nhầm chữ. */
  function isValidUrl(value) {
    try {
      const parsed = new URL(value)

      return parsed.protocol === 'http:' || parsed.protocol === 'https:'
    } catch {
      return false
    }
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    setErrors({})

    try {
      await apiPost('/complaints', {
        order_id: Number(orderId),
        reason: form.reason,
        description: form.description || null,
        evidence_media: mediaList,
      })

      navigate(`/don-hang/${orderId}`, { replace: true })
    } catch (caught) {
      setErrors(readFieldErrors(caught))
      setError(readError(caught))
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return (
      <div className="wrap complaint">
        <div className="skeleton" style={{ height: 240 }} />
      </div>
    )
  }

  if (loadError || !order) {
    return (
      <div className="wrap complaint">
        <div className="empty">
          <p className="empty__title">{loadError}</p>
          <p>
            <Link to="/don-hang">← Về danh sách đơn</Link>
          </p>
        </div>
      </div>
    )
  }

  // Kiểm tra lại hạn 24 giờ ở phía giao diện để báo sớm cho khách.
  const deliveredAt = order.delivered_at ? new Date(order.delivered_at) : null
  const withinWindow =
    deliveredAt !== null && deliveredAt.getTime() + 24 * 3600 * 1000 > Date.now()

  if (order.status !== 'delivered' || !withinWindow) {
    return (
      <div className="wrap complaint">
        <Link to={`/don-hang/${orderId}`} className="orders__back">
          <Icon name="back" size={15} />
          Về đơn hàng
        </Link>

        <div className="empty">
          <p className="empty__title">Không thể gửi khiếu nại cho đơn này</p>
          <p>
            {order.status !== 'delivered'
              ? 'Chỉ nhận khiếu nại cho đơn đã giao thành công.'
              : 'Đã quá 24 giờ kể từ khi nhận hàng. Nông sản tươi nên thời hạn khiếu nại là 24 giờ.'}
          </p>
        </div>
      </div>
    )
  }

  return (
    <div className="wrap complaint">
      <Link to={`/don-hang/${orderId}`} className="orders__back">
        <Icon name="back" size={15} />
        Về đơn hàng
      </Link>

      <div className="complaint__head">
        <h1>Gửi khiếu nại</h1>
        <p className="complaint__order data">
          Đơn {order.order_code} · {formatMoney(order.total_amount)}
        </p>
      </div>

      <div className="complaint__grid">
        <form className="complaint__form" onSubmit={handleSubmit}>
          {/* ---- Lý do ---- */}
          <fieldset className="complaint__fieldset">
            <legend className="field__label">Vấn đề của đơn hàng</legend>

            <div className="reason-grid">
              {Object.entries(COMPLAINT_REASON_LABEL).map(([value, label]) => (
                <label
                  key={value}
                  className={`reason ${form.reason === value ? 'is-active' : ''}`}
                >
                  <input
                    type="radio"
                    name="reason"
                    value={value}
                    checked={form.reason === value}
                    onChange={(event) =>
                      setForm((current) => ({ ...current, reason: event.target.value }))
                    }
                  />
                  {label}
                </label>
              ))}
            </div>
          </fieldset>

          {/* ---- Mô tả ---- */}
          <div className="field">
            <label className="field__label" htmlFor="description">
              Mô tả thêm <span className="field__optional">(không bắt buộc)</span>
            </label>
            <textarea
              id="description"
              className="textarea"
              rows={3}
              value={form.description}
              onChange={(event) =>
                setForm((current) => ({ ...current, description: event.target.value }))
              }
              placeholder="Ví dụ: 2 bó rau muống bị dập ở phần gốc, còn lại vẫn dùng được"
            />
          </div>

          {/* ---- Minh chứng ---- */}
          <div className="field">
            <label className="field__label" htmlFor="media">
              Ảnh / video minh chứng <span className="field__required">bắt buộc</span>
            </label>

            <div className="complaint__media-input">
              <input
                id="media"
                className="input"
                value={mediaInput}
                onChange={(event) => setMediaInput(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') {
                    event.preventDefault()

                    if (isValidUrl(mediaInput.trim())) {
                      addMedia()
                    } else {
                      setErrors((current) => ({
                        ...current,
                        evidence_media: 'Đường dẫn phải bắt đầu bằng http:// hoặc https://',
                      }))
                    }
                  }
                }}
                placeholder="Dán đường dẫn ảnh đã tải lên (Cloudinary)"
              />
              <button
                type="button"
                className="btn btn--outline"
                onClick={() => {
                  if (isValidUrl(mediaInput.trim())) {
                    addMedia()
                  } else {
                    setErrors((current) => ({
                      ...current,
                      evidence_media: 'Đường dẫn phải bắt đầu bằng http:// hoặc https://',
                    }))
                  }
                }}
              >
                Thêm
              </button>
            </div>

            {errors.evidence_media && (
              <p className="field__error">{errors.evidence_media}</p>
            )}

            <p className="field__hint">
              Tối đa 5 tệp. Chụp rõ phần hàng bị hỏng để CSKH xử lý nhanh.
            </p>

            {mediaList.length > 0 && (
              <ul className="media-list">
                {mediaList.map((url) => (
                  <li key={url} className="media-list__item">
                    <img src={url} alt="Minh chứng khiếu nại" />
                    <button
                      type="button"
                      className="media-list__remove"
                      onClick={() =>
                        setMediaList((current) => current.filter((item) => item !== url))
                      }
                      aria-label="Xoá ảnh này"
                    >
                      <Icon name="close" size={14} />
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </div>

          {error && (
            <p className="auth__error" role="alert">
              <Icon name="alert" size={15} />
              {error}
            </p>
          )}

          <button
            type="submit"
            className="btn btn--primary btn--block"
            disabled={submitting || mediaList.length === 0}
          >
            {submitting ? 'Đang gửi…' : 'Gửi khiếu nại'}
          </button>
        </form>

        {/* ---- Cột phải: giải thích ---- */}
        <aside className="complaint__aside">
          <h2 className="complaint__aside-title">Chuyện gì xảy ra tiếp theo</h2>

          <ol className="complaint__steps">
            <li>
              <span className="data">01</span>
              Bộ phận CSKH tiếp nhận trong giờ làm việc.
            </li>
            <li>
              <span className="data">02</span>
              Nếu đúng lỗi từ nông trại, bạn chọn hoàn tiền hoặc giao bù.
            </li>
            <li>
              <span className="data">03</span>
              Đơn giao bù được xử lý trong chuyến giao kế tiếp.
            </li>
          </ol>

          <p className="complaint__note">
            Thời hạn 24 giờ là vì nông sản tươi hỏng rất nhanh — qua ngày sau thì
            không còn xác định được lỗi phát sinh từ khâu nào.
          </p>
        </aside>
      </div>
    </div>
  )
}
