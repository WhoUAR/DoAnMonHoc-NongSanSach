/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thanh Liêm (0306241214)
 * CHỨC NĂNG: Trang truy xuất nguồn gốc - CÔNG KHAI, không cần đăng nhập.
 *
 *   Hai chế độ trong cùng một trang:
 *     1. Nhập mã lô (hoặc mở link từ mã QR) -> xem hồ sơ đầy đủ.
 *     2. Chưa có mã -> hiện ô nhập và giải thích cách tìm mã trên túi hàng.
 *
 *   Trang này là điểm chốt của đề tài: khách tự kiểm chứng được quy trình
 *   canh tác, không phải tin vào lời quảng cáo.
 */

import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { apiGet, readError } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import { ACTIVITY_LABEL, formatExpiry } from '../utils/format.js'
import './TraceabilityPage.css'

export default function TraceabilityPage() {
  const { token } = useParams()

  const [tokenInput, setTokenInput] = useState('')
  const [record, setRecord] = useState(null)
  const [loading, setLoading] = useState(Boolean(token))
  const [error, setError] = useState('')

  /** Tải hồ sơ truy xuất khi có token trên đường dẫn. */
  useEffect(() => {
    if (!token) {
      setLoading(false)
      return
    }

    let cancelled = false

    setLoading(true)
    setError('')

    apiGet(`/traceability/${token}`)
      .then((response) => {
        if (!cancelled) {
          setRecord(response.data)
        }
      })
      .catch((caught) => {
        if (!cancelled) {
          setError(readError(caught))
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [token])

  // ------------------------------------------------------------------------
  // Chế độ 1: chưa có token -> màn hình nhập mã
  // ------------------------------------------------------------------------
  if (!token) {
    return (
      <div className="wrap trace">
        <div className="trace__intro">
          <h1>Tra cứu nguồn gốc</h1>
          <p className="trace__intro-body">
            Trên mỗi túi rau có một tem dán ghi mã lô. Nhập mã đó để xem lô hàng
            được trồng ở đâu, ai chăm sóc, và từng ngày trong mùa vụ.
          </p>
        </div>

        <form
          className="trace__form"
          onSubmit={(event) => {
            event.preventDefault()

            const value = tokenInput.trim()

            if (value) {
              // Chuyển sang đường dẫn có token để có thể lưu hoặc chia sẻ.
              window.location.href = `/truy-xuat/${value}`
            }
          }}
        >
          <label className="field__label" htmlFor="token">
            Mã lô trên tem
          </label>
          <div className="trace__form-row">
            <input
              id="token"
              className="input data"
              value={tokenInput}
              onChange={(event) => setTokenInput(event.target.value)}
              placeholder="Ví dụ: aB3xK9…"
              autoComplete="off"
            />
            <button type="submit" className="btn btn--primary" disabled={!tokenInput.trim()}>
              Tra cứu
            </button>
          </div>
          <p className="field__hint">
            Hoặc quét mã QR trên tem bằng camera điện thoại — mã sẽ mở thẳng
            trang này.
          </p>
        </form>

        <div className="trace__how">
          <h2 className="trace__how-title">Tem nằm ở đâu?</h2>
          <ol className="trace__how-list">
            <li>
              <span className="data">01</span>
              Mặt trước túi giấy, góc trên bên phải
            </li>
            <li>
              <span className="data">02</span>
              Mã QR in đen trên nền trắng, cỡ 2×2 cm
            </li>
            <li>
              <span className="data">03</span>
              Bên dưới mã có dòng chữ nhỏ ghi mã lô
            </li>
          </ol>
        </div>
      </div>
    )
  }

  // ------------------------------------------------------------------------
  // Đang tải
  // ------------------------------------------------------------------------
  if (loading) {
    return (
      <div className="wrap trace">
        <div className="skeleton" style={{ height: 120, marginBottom: 24 }} />
        <div className="skeleton" style={{ height: 300 }} />
      </div>
    )
  }

  // ------------------------------------------------------------------------
  // Không tìm thấy
  // ------------------------------------------------------------------------
  if (error || !record) {
    return (
      <div className="wrap trace">
        <div className="empty">
          <p className="empty__title">Không tìm thấy lô hàng này</p>
          <p>{error || 'Mã QR có thể đã bị hỏng hoặc lô đã bị xoá.'}</p>
          <p style={{ marginTop: 'var(--s4)' }}>
            <Link to="/truy-xuat" className="btn btn--outline">
              Nhập lại mã lô
            </Link>
          </p>
        </div>
      </div>
    )
  }

  const { batch, product, farmer, timeline } = record

  return (
    <div className="trace">
      {/* ---- Đầu trang: nhãn lô ---- */}
      <div className="trace__header">
        <div className="wrap">
          <p className="trace__eyebrow data">Hồ sơ lô hàng</p>
          <h1 className="trace__code data">{batch.batch_code}</h1>

          <div className="trace__header-grid">
            <div className="trace__header-item">
              <span className="data-label">Sản phẩm</span>
              <span className="trace__header-value">{product.name}</span>
            </div>
            <div className="trace__header-item">
              <span className="data-label">Vùng trồng</span>
              <span className="trace__header-value">{product.origin ?? '—'}</span>
            </div>
            <div className="trace__header-item">
              <span className="data-label">Người phụ trách</span>
              <span className="trace__header-value">{farmer.full_name ?? '—'}</span>
            </div>
            <div className="trace__header-item">
              <span className="data-label">Chứng nhận</span>
              <span className="trace__header-value">
                {product.certification ?? '—'}
              </span>
            </div>
          </div>
        </div>
      </div>

      <div className="wrap trace__body">
        {/* ---- Ba mốc thời gian ---- */}
        <section className="trace__dates">
          <div className="trace__date">
            <span className="data-label">Ngày gieo trồng</span>
            <span className="data trace__date-value">{batch.planted_date ?? '—'}</span>
          </div>
          <div className="trace__date trace__date--key">
            <span className="data-label">Ngày thu hoạch</span>
            <span className="data trace__date-value">{batch.harvest_date ?? '—'}</span>
          </div>
          <div className="trace__date">
            <span className="data-label">Hạn sử dụng</span>
            <span className="data trace__date-value">{batch.expiry_date ?? '—'}</span>
            <span
              className={`tag tag--${batch.days_until_expiry <= 2 ? 'danger' : batch.days_until_expiry <= 5 ? 'warn' : 'good'}`}
            >
              {formatExpiry(batch.days_until_expiry)}
            </span>
          </div>
        </section>

        {/* ---- Dòng thời gian canh tác ---- */}
        <section className="trace__timeline-section">
          <div className="trace__section-head">
            <h2>Nhật ký canh tác</h2>
            <p className="trace__section-note">
              Ghi bởi nhân viên nông trại, không sửa được sau 24 giờ.
              {timeline.length > 0 && ` ${timeline.length} mốc.`}
            </p>
          </div>

          {timeline.length === 0 ? (
            <div className="empty">
              <p className="empty__title">Lô này chưa có nhật ký</p>
              <p>Nhật ký được cập nhật trong quá trình canh tác.</p>
            </div>
          ) : (
            <ol className="timeline">
              {timeline.map((log, index) => (
                <li key={`${log.activity_date}-${index}`} className="timeline__item">
                  <div className="timeline__marker">
                    <span className="timeline__dot" aria-hidden="true" />
                  </div>

                  <div className="timeline__content">
                    <div className="timeline__head">
                      <span className="timeline__type data">
                        {ACTIVITY_LABEL[log.activity_type] ?? log.activity_type}
                      </span>
                      <time className="timeline__date data">{log.activity_date}</time>
                    </div>

                    <h3 className="timeline__title">{log.title}</h3>

                    {log.description && (
                      <p className="timeline__desc">{log.description}</p>
                    )}

                    {log.media?.length > 0 && (
                      <div className="timeline__media">
                        {log.media.map((url) => (
                          <img
                            key={url}
                            src={url}
                            alt={`Ảnh minh chứng: ${log.title}`}
                            loading="lazy"
                          />
                        ))}
                      </div>
                    )}

                    {log.author && (
                      <p className="timeline__author">
                        Ghi bởi {log.author}
                      </p>
                    )}
                  </div>
                </li>
              ))}
            </ol>
          )}
        </section>

        <p className="trace__footer">
          <Icon name="leaf" size={15} />
          Dữ liệu này lấy trực tiếp từ sổ canh tác của nông trại, không qua
          khâu chỉnh sửa nào.
        </p>
      </div>
    </div>
  )
}
