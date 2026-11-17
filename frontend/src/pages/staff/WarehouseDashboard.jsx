/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Văn Đoàn (0306241191)
 * CHỨC NĂNG: Bảng điều khiển của NHÂN VIÊN KHO (Warehouse Staff).
 *
 *   Công việc:
 *     1. Xem toàn bộ lô, sắp theo hạn dùng gần nhất (thứ tự FEFO).
 *     2. Thu hoạch: nhập số lượng thật, hệ thống tự tính hạn sử dụng.
 *     3. Xuất hủy: chọn lô, nhập số kg hỏng, chọn lý do.
 *
 *   THIẾT KẾ: bảng lô dùng chung ngôn ngữ với trang chủ - cùng là "sổ cái"
 *   nhưng đây là bản đầy đủ của người vận hành, có thêm cột thao tác.
 *   Dòng sắp hết hạn được tô nền đỏ nhạt để mắt bắt được ngay.
 */

import { useCallback, useEffect, useState } from 'react'
import { apiGet, apiPost, readError } from '../../api/client.js'
import Icon from '../../components/Icon.jsx'
import {
  BATCH_STATUS_LABEL,
  DISPOSE_REASON_LABEL,
  formatDate,
  formatExpiry,
  formatQuantity,
} from '../../utils/format.js'
import './StaffPages.css'

export default function WarehouseDashboard() {
  const [batches, setBatches] = useState([])
  const [loading, setLoading] = useState(true)
  const [message, setMessage] = useState(null)

  // Hộp thoại đang mở: null | { type: 'harvest' | 'dispose', batch }
  const [dialog, setDialog] = useState(null)

  const loadBatches = useCallback(async () => {
    setLoading(true)

    try {
      const response = await apiGet('/batches?per_page=50')
      setBatches(response.data)
    } catch {
      setMessage({ type: 'error', text: 'Không tải được danh sách lô hàng.' })
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    loadBatches()
  }, [loadBatches])

  /** Sau khi thu hoạch / hủy hàng thành công thì tải lại bảng. */
  async function handleDone(text) {
    setMessage({ type: 'ok', text })
    setDialog(null)
    await loadBatches()
  }

  // Đếm số lô cần chú ý để hiện ở đầu trang.
  const growingCount = batches.filter((batch) => batch.status === 'growing').length
  const expiringCount = batches.filter(
    (batch) => batch.status === 'available' && batch.days_until_expiry <= 3,
  ).length

  return (
    <div className="staff">
      <header className="staff__head">
        <div>
          <h1>Kho hàng</h1>
          <p className="staff__sub">
            Quản lý lô theo hạn sử dụng · xuất kho theo FEFO
          </p>
        </div>

        <div className="staff__stats">
          <div className="staff__stat">
            <span className="data-label">Đang canh tác</span>
            <span className="data staff__stat-value">{growingCount}</span>
          </div>
          <div className={`staff__stat ${expiringCount > 0 ? 'staff__stat--alert' : ''}`}>
            <span className="data-label">Cận hạn ≤3 ngày</span>
            <span className="data staff__stat-value">{expiringCount}</span>
          </div>
        </div>
      </header>

      {message && (
        <p className={`staff__message staff__message--${message.type}`} role="status">
          {message.text}
        </p>
      )}

      <div className="staff__toolbar">
        <p className="staff__legend">
          <span className="legend-swatch legend-swatch--danger" /> Nền đỏ = lô cận hạn,
          phải xử lý trước
        </p>
        <button type="button" className="btn btn--outline btn--sm" onClick={loadBatches}>
          <Icon name="check" size={15} />
          Tải lại
        </button>
      </div>

      {loading ? (
        <div className="stack">
          {[0, 1, 2, 3].map((index) => (
            <div key={index} className="skeleton" style={{ height: 64 }} />
          ))}
        </div>
      ) : batches.length === 0 ? (
        <div className="empty">
          <p className="empty__title">Chưa có lô hàng nào</p>
          <p>Nhân viên nông trại cần tạo lô trước khi thu hoạch.</p>
        </div>
      ) : (
        <div className="table-wrap">
          <table className="ledger staff-table">
            <thead>
              <tr>
                <th scope="col">Mã lô</th>
                <th scope="col">Sản phẩm</th>
                <th scope="col" className="num">Còn / Nhập</th>
                <th scope="col">Hạn dùng</th>
                <th scope="col">Trạng thái</th>
                <th scope="col">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              {batches.map((batch) => {
                const isExpiring =
                  batch.status === 'available' && batch.days_until_expiry <= 3

                return (
                  <tr key={batch.id} className={isExpiring ? 'staff-table__row--alert' : ''}>
                    <td className="data staff-table__code">{batch.batch_code}</td>

                    <td>
                      <span>{batch.product?.name}</span>
                      <span className="staff-table__muted">
                        {' '}
                        · {batch.product?.unit}
                      </span>
                    </td>

                    <td className="num data">
                      {formatQuantity(batch.quantity_available)}
                      <span className="staff-table__muted">
                        {' / '}
                        {formatQuantity(batch.quantity_imported)}
                      </span>
                    </td>

                    <td>
                      {batch.expiry_date ? (
                        isExpiring ? (
                          // Chỉ tô màu cảnh báo cho lô CÒN BÁN ĐƯỢC.
                          // Lô đã hết hàng thì hạn dùng không còn ý nghĩa xử lý.
                          <span className={`tag tag--${expiryTone(batch.days_until_expiry)}`}>
                            {formatExpiry(batch.days_until_expiry)}
                          </span>
                        ) : (
                          <span className="data staff-table__date">
                            {formatDate(batch.expiry_date)}
                          </span>
                        )
                      ) : (
                        <span className="staff-table__muted">Chưa thu hoạch</span>
                      )}
                    </td>

                    <td>
                      <span
                        className={`tag tag--${batch.status === 'available' ? 'good' : 'neutral'}`}
                      >
                        {BATCH_STATUS_LABEL[batch.status] ?? batch.status}
                      </span>
                    </td>

                    <td>
                      <div className="staff-table__actions">
                        {batch.status === 'growing' && (
                          <button
                            type="button"
                            className="btn btn--primary btn--sm"
                            onClick={() => setDialog({ type: 'harvest', batch })}
                          >
                            Thu hoạch
                          </button>
                        )}

                        {batch.status === 'available' && (
                          <button
                            type="button"
                            className="btn btn--outline btn--sm"
                            onClick={() => setDialog({ type: 'dispose', batch })}
                          >
                            Xuất hủy
                          </button>
                        )}
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
        </div>
      )}

      {dialog?.type === 'harvest' && (
        <HarvestDialog
          batch={dialog.batch}
          onClose={() => setDialog(null)}
          onDone={handleDone}
        />
      )}

      {dialog?.type === 'dispose' && (
        <DisposeDialog
          batch={dialog.batch}
          onClose={() => setDialog(null)}
          onDone={handleDone}
        />
      )}
    </div>
  )
}

function expiryTone(days) {
  if (days <= 2) return 'danger'
  if (days <= 5) return 'warn'

  return 'good'
}

// ============================================================================
// HỘP THOẠI THU HOẠCH
// ============================================================================

function HarvestDialog({ batch, onClose, onDone }) {
  const [harvestDate, setHarvestDate] = useState(
    new Date().toISOString().slice(0, 10),
  )
  const [quantity, setQuantity] = useState('')
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      await apiPost(`/batches/${batch.id}/harvest`, {
        harvest_date: harvestDate,
        quantity_imported: Number(quantity),
      })

      onDone(`Đã nhập kho ${quantity} ${batch.product?.unit} cho lô ${batch.batch_code}.`)
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
          <h2>Thu hoạch lô {batch.batch_code}</h2>
          <button type="button" className="modal__close" onClick={onClose} aria-label="Đóng">
            <Icon name="close" size={20} />
          </button>
        </div>

        <form className="modal__body" onSubmit={handleSubmit}>
          <p className="staff__dialog-note">
            {batch.product?.name} · Hệ thống tự tính hạn sử dụng từ ngày thu hoạch
            cộng số ngày bảo quản của sản phẩm.
          </p>

          <div className="field">
            <label className="field__label" htmlFor="harvest-date">Ngày thu hoạch</label>
            <input
              id="harvest-date"
              type="date"
              className="input data"
              value={harvestDate}
              max={new Date().toISOString().slice(0, 10)}
              onChange={(event) => setHarvestDate(event.target.value)}
            />
          </div>

          <div className="field">
            <label className="field__label" htmlFor="harvest-qty">
              Số lượng thu hoạch ({batch.product?.unit})
            </label>
            <input
              id="harvest-qty"
              type="number"
              className="input data"
              value={quantity}
              onChange={(event) => setQuantity(event.target.value)}
              min="0.001"
              step="0.1"
              placeholder="Ví dụ: 25.5"
              inputMode="decimal"
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
              {submitting ? 'Đang lưu…' : 'Xác nhận thu hoạch'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ============================================================================
// HỘP THOẠI XUẤT HỦY
// ============================================================================

function DisposeDialog({ batch, onClose, onDone }) {
  const [quantity, setQuantity] = useState('')
  const [reason, setReason] = useState('damaged')
  const [note, setNote] = useState('')
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')

    try {
      await apiPost(`/batches/${batch.id}/dispose`, {
        quantity: Number(quantity),
        reason,
        note: note || null,
      })

      onDone(
        `Đã xuất hủy ${quantity} ${batch.product?.unit} khỏi lô ${batch.batch_code}.`,
      )
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
          <h2>Xuất hủy lô {batch.batch_code}</h2>
          <button type="button" className="modal__close" onClick={onClose} aria-label="Đóng">
            <Icon name="close" size={20} />
          </button>
        </div>

        <form className="modal__body" onSubmit={handleSubmit}>
          <p className="staff__dialog-note">
            {batch.product?.name} · Còn {formatQuantity(batch.quantity_available)}{' '}
            {batch.product?.unit} trong lô.
          </p>

          <div className="field">
            <label className="field__label" htmlFor="dispose-qty">
              Số lượng hủy ({batch.product?.unit})
            </label>
            <input
              id="dispose-qty"
              type="number"
              className="input data"
              value={quantity}
              onChange={(event) => setQuantity(event.target.value)}
              min="0.001"
              max={batch.quantity_available}
              step="0.1"
              placeholder="Ví dụ: 5"
              inputMode="decimal"
            />
          </div>

          <div className="field">
            <label className="field__label" htmlFor="dispose-reason">Lý do</label>
            <select
              id="dispose-reason"
              className="select"
              value={reason}
              onChange={(event) => setReason(event.target.value)}
            >
              {Object.entries(DISPOSE_REASON_LABEL).map(([key, label]) => (
                <option key={key} value={key}>{label}</option>
              ))}
            </select>
          </div>

          <div className="field">
            <label className="field__label" htmlFor="dispose-note">
              Ghi chú <span className="field__optional">(không bắt buộc)</span>
            </label>
            <input
              id="dispose-note"
              className="input"
              value={note}
              onChange={(event) => setNote(event.target.value)}
              placeholder="Ví dụ: dập nát khi vận chuyển"
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
              {submitting ? 'Đang lưu…' : 'Xác nhận xuất hủy'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}
