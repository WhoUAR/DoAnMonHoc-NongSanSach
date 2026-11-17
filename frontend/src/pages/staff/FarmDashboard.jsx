/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thanh Liêm (0306241214)
 * CHỨC NĂNG: Bảng điều khiển của NHÂN VIÊN NÔNG TRẠI (Farm Staff).
 *
 *   Công việc:
 *     1. Xem các lô đang canh tác.
 *     2. Tạo lô mới khi bắt đầu gieo trồng.
 *     3. Ghi nhật ký canh tác cho từng lô (gieo, tưới, bón phân, thu hoạch).
 *     4. Lấy mã QR để in tem dán túi hàng.
 *
 *   THIẾT KẾ: nhân viên dùng ngoài đồng, nắng, tay bẩn. Nút to, chữ to,
 *   thao tác một tay. Nhật ký mở thành ngăn kéo bên cạnh thay vì chuyển trang
 *   để không mất ngữ cảnh lô đang làm.
 */

import { useCallback, useEffect, useState } from 'react'
import { apiGet, apiPost, readError } from '../../api/client.js'
import Icon from '../../components/Icon.jsx'
import {
  ACTIVITY_LABEL,
  BATCH_STATUS_LABEL,
  formatDate,
  formatQuantity,
} from '../../utils/format.js'
import './StaffPages.css'

export default function FarmDashboard() {
  const [batches, setBatches] = useState([])
  const [products, setProducts] = useState([])
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState(null)

  // Lô đang mở nhật ký (null = không mở).
  const [activeBatch, setActiveBatch] = useState(null)
  const [creating, setCreating] = useState(false)

  const loadData = useCallback(async () => {
    setLoading(true)

    try {
      const [batchResponse, productResponse] = await Promise.all([
        apiGet('/batches?per_page=50'),
        apiGet('/products?per_page=50'),
      ])

      setBatches(batchResponse.data)
      setProducts(productResponse.data)
    } catch {
      setMessage({ type: 'error', text: 'Không tải được dữ liệu nông trại.' })
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadData()
  }, [loadData])

  async function handleCreated(text) {
    setMessage({ type: 'ok', text })
    setCreating(false)
    await loadData()
  }

  const growing = batches.filter((batch) => batch.status === 'growing')

  return (
    <div className="staff">
      <header className="staff__head">
        <div>
          <h1>Nông trại</h1>
          <p className="staff__sub">Lô canh tác · nhật ký · mã truy xuất</p>
        </div>

        <button
          type="button"
          className="btn btn--primary"
          onClick={() => setCreating(true)}
        >
          <Icon name="plus" size={16} />
          Tạo lô mới
        </button>
      </header>

      {message && (
        <p className={`staff__message staff__message--${message.type}`} role="status">
          {message.text}
        </p>
      )}

      {loading ? (
        <div className="stack">
          {[0, 1, 2].map((index) => (
            <div key={index} className="skeleton" style={{ height: 76 }} />
          ))}
        </div>
      ) : (
        <>
          {/* ---- Lô đang canh tác: thẻ lớn, dễ bấm ---- */}
          <section className="farm-section">
            <h2 className="staff__section-title">
              Đang canh tác
              <span className="data staff__section-count">{growing.length}</span>
            </h2>

            {growing.length === 0 ? (
              <div className="empty">
                <p className="empty__title">Chưa có lô đang canh tác</p>
                <p>Bấm “Tạo lô mới” khi bắt đầu gieo trồng.</p>
              </div>
            ) : (
              <div className="farm-cards">
                {growing.map((batch) => (
                  <article key={batch.id} className="farm-card">
                    <div className="farm-card__head">
                      <span className="data farm-card__code">{batch.batch_code}</span>
                      <span className="tag tag--neutral">
                        {BATCH_STATUS_LABEL[batch.status]}
                      </span>
                    </div>

                    <h3 className="farm-card__name">{batch.product?.name}</h3>

                    <dl className="farm-card__meta">
                      <div>
                        <dt className="data-label">Gieo trồng</dt>
                        <dd className="data">{formatDate(batch.planted_date)}</dd>
                      </div>
                      <div>
                        <dt className="data-label">Dự kiến bảo quản</dt>
                        <dd className="data">{batch.product?.shelf_life_days} ngày</dd>
                      </div>
                    </dl>

                    <div className="farm-card__actions">
                      <button
                        type="button"
                        className="btn btn--solid btn--sm"
                        onClick={() => setActiveBatch(batch)}
                      >
                        <Icon name="leaf" size={15} />
                        Nhật ký
                      </button>

                      <a
                        className="btn btn--outline btn--sm"
                        href={`/api/traceability/${batch.traceability_token}/qr.svg`}
                        target="_blank"
                        rel="noreferrer"
                      >
                        <Icon name="qr" size={15} />
                        Mã QR
                      </a>
                    </div>
                  </article>
                ))}
              </div>
            )}
          </section>

          {/* ---- Tất cả lô ---- */}
          <section className="farm-section">
            <h2 className="staff__section-title">
              Tất cả lô
              <span className="data staff__section-count">{batches.length}</span>
            </h2>

            <div className="table-wrap">
              <table className="ledger staff-table">
                <thead>
                  <tr>
                    <th scope="col">Mã lô</th>
                    <th scope="col">Sản phẩm</th>
                    <th scope="col" className="num">Còn</th>
                    <th scope="col">Thu hoạch</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">Nhật ký</th>
                  </tr>
                </thead>
                <tbody>
                  {batches.map((batch) => (
                    <tr key={batch.id}>
                      <td className="data staff-table__code">{batch.batch_code}</td>
                      <td>{batch.product?.name}</td>
                      <td className="num data">
                        {formatQuantity(batch.quantity_available)}
                      </td>
                      <td className="data staff-table__date">
                        {batch.harvest_date ? formatDate(batch.harvest_date) : '—'}
                      </td>
                      <td>
                        <span
                          className={`tag tag--${batch.status === 'available' ? 'good' : 'neutral'}`}
                        >
                          {BATCH_STATUS_LABEL[batch.status] ?? batch.status}
                        </span>
                      </td>
                      <td>
                        <button
                          type="button"
                          className="btn btn--ghost btn--sm"
                          onClick={() => setActiveBatch(batch)}
                        >
                          Xem
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      )}

      {creating && (
        <CreateBatchDialog
          products={products}
          onClose={() => setCreating(false)}
          onDone={handleCreated}
        />
      )}

      {activeBatch && (
        <FarmingLogDrawer
          batch={activeBatch}
          onClose={() => setActiveBatch(null)}
          onLogged={async (text) => {
            setMessage({ type: 'ok', text })
            await loadData()
          }}
        />
      )}
    </div>
  )
}

// ============================================================================
// HỘP THOẠI TẠO LÔ
// ============================================================================

function CreateBatchDialog({ products, onClose, onDone }) {
  const [form, setForm] = useState({
    product_id: products[0]?.id ?? '',
    planted_date: new Date().toISOString().slice(0, 10),
    quality_note: '',
  })
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      const response = await apiPost('/batches', {
        product_id: Number(form.product_id),
        planted_date: form.planted_date,
        quality_note: form.quality_note || null,
      })

      onDone(`Đã tạo lô ${response.data.batch.batch_code}.`)
    } catch (caught) {
      setError(readError(caught))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="modal-backdrop" role="dialog" aria-modal="true">
      <div className="modal">
        <div className="modal__head">
          <h2>Tạo lô canh tác mới</h2>
          <button type="button" className="modal__close" onClick={onClose} aria-label="Đóng">
            <Icon name="close" size={20} />
          </button>
        </div>

        <form className="modal__body" onSubmit={handleSubmit}>
          <div className="field">
            <label className="field__label" htmlFor="batch-product">Sản phẩm</label>
            <select
              id="batch-product"
              className="select"
              value={form.product_id}
              onChange={(event) =>
                setForm((current) => ({ ...current, product_id: event.target.value }))
              }
            >
              {products.map((product) => (
                <option key={product.id} value={product.id}>
                  {product.name} ({product.unit})
                </option>
              ))}
            </select>
          </div>

          <div className="field">
            <label className="field__label" htmlFor="batch-date">Ngày gieo trồng</label>
            <input
              id="batch-date"
              type="date"
              className="input data"
              value={form.planted_date}
              max={new Date().toISOString().slice(0, 10)}
              onChange={(event) =>
                setForm((current) => ({ ...current, planted_date: event.target.value }))
              }
            />
          </div>

          <div className="field">
            <label className="field__label" htmlFor="batch-note">
              Ghi chú <span className="field__optional">(không bắt buộc)</span>
            </label>
            <input
              id="batch-note"
              className="input"
              value={form.quality_note}
              onChange={(event) =>
                setForm((current) => ({ ...current, quality_note: event.target.value }))
              }
              placeholder="Ví dụ: luống 3, giống mới"
            />
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
            <button type="submit" className="btn btn--primary" disabled={submitting}>
              {submitting ? 'Đang tạo…' : 'Tạo lô'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ============================================================================
// NGĂN KÉO NHẬT KÝ
// ============================================================================

function FarmingLogDrawer({ batch, onClose, onLogged }) {
  const [logs, setLogs] = useState([])
  const [loading, setLoading] = useState(true)
  const [form, setForm] = useState({
    activity_type: 'watering',
    activity_date: new Date().toISOString().slice(0, 10),
    title: '',
    description: '',
  })
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const loadLogs = useCallback(async () => {
    setLoading(true)

    try {
      const response = await apiGet(`/batches/${batch.id}/farming-logs`)
      setLogs(response.data.logs)
    } finally {
      setLoading(false)
    }
  }, [batch.id])

  useEffect(() => {
    loadLogs()
  }, [loadLogs])

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      await apiPost(`/batches/${batch.id}/farming-logs`, {
        activity_type: form.activity_type,
        activity_date: form.activity_date,
        title: form.title,
        description: form.description || null,
      })

      setForm((current) => ({ ...current, title: '', description: '' }))
      await loadLogs()
      await onLogged(`Đã ghi nhật ký cho lô ${batch.batch_code}.`)
    } catch (caught) {
      setError(readError(caught))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <>
      <div className="drawer-backdrop" onClick={onClose} aria-hidden="true" />

      <aside className="drawer" role="dialog" aria-modal="true" aria-labelledby="log-title">
        <header className="drawer__head">
          <div>
            <span className="data drawer__code">{batch.batch_code}</span>
            <h2 id="log-title" className="drawer__title">
              Nhật ký · {batch.product?.name}
            </h2>
          </div>

          <button type="button" className="drawer__close" onClick={onClose} aria-label="Đóng">
            <Icon name="close" size={20} />
          </button>
        </header>

        <div className="drawer__body">
          {/* ---- Biểu mẫu ghi mốc mới ---- */}
          <form className="log-form" onSubmit={handleSubmit}>
            <h3 className="log-form__title">Ghi mốc mới</h3>

            <div className="log-form__row">
              <label className="field" style={{ flex: 2 }}>
                <span className="field__label">Hoạt động</span>
                <select
                  className="select"
                  value={form.activity_type}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, activity_type: event.target.value }))
                  }
                >
                  {Object.entries(ACTIVITY_LABEL).map(([key, label]) => (
                    <option key={key} value={key}>{label}</option>
                  ))}
                </select>
              </label>

              <label className="field" style={{ flex: 1 }}>
                <span className="field__label">Ngày</span>
                <input
                  type="date"
                  className="input data"
                  value={form.activity_date}
                  max={new Date().toISOString().slice(0, 10)}
                  onChange={(event) =>
                    setForm((current) => ({ ...current, activity_date: event.target.value }))
                  }
                />
              </label>
            </div>

            <label className="field">
              <span className="field__label">Tiêu đề</span>
              <input
                className="input"
                value={form.title}
                onChange={(event) =>
                  setForm((current) => ({ ...current, title: event.target.value }))
                }
                placeholder="Ví dụ: Tưới nhỏ giọt buổi sáng"
                required
              />
            </label>

            <label className="field">
              <span className="field__label">
                Mô tả <span className="field__optional">(không bắt buộc)</span>
              </span>
              <textarea
                className="textarea"
                rows={2}
                value={form.description}
                onChange={(event) =>
                  setForm((current) => ({ ...current, description: event.target.value }))
                }
              />
            </label>

            {error && (
              <p className="auth__error" role="alert">
                <Icon name="alert" size={15} />
                {error}
              </p>
            )}

            <button type="submit" className="btn btn--primary btn--block" disabled={submitting}>
              {submitting ? 'Đang lưu…' : 'Ghi vào nhật ký'}
            </button>
          </form>

          {/* ---- Các mốc đã ghi ---- */}
          <div className="log-list">
            <h3 className="log-form__title">
              Đã ghi <span className="data staff__section-count">{logs.length}</span>
            </h3>

            {loading ? (
              <div className="skeleton" style={{ height: 100 }} />
            ) : logs.length === 0 ? (
              <p className="staff-table__muted">Chưa có mốc nào.</p>
            ) : (
              logs.map((log) => (
                <div key={log.id} className="log-entry">
                  <div className="log-entry__head">
                    <span className="data log-entry__type">
                      {ACTIVITY_LABEL[log.activity_type] ?? log.activity_type}
                    </span>
                    <span className="data log-entry__date">
                      {formatDate(log.activity_date)}
                    </span>
                  </div>
                  <p className="log-entry__title">{log.title}</p>
                  {log.description && (
                    <p className="log-entry__desc">{log.description}</p>
                  )}
                </div>
              ))
            )}
          </div>
        </div>
      </aside>
    </>
  )
}
