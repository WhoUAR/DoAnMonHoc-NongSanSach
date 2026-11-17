/**
 * NGƯỜI PHỤ TRÁCH: Đào Quốc Đạt (0306241190)
 * CHỨC NĂNG: Trang báo lỗi 404 - đường dẫn không tồn tại.
 */

import { Link } from 'react-router-dom'
import Icon from '../components/Icon.jsx'

export default function NotFoundPage() {
  return (
    <div className="wrap" style={{ paddingBlock: 'var(--s9)' }}>
      <div style={{ maxWidth: '48ch' }}>
        <p
          className="data"
          style={{
            fontSize: '0.6875rem',
            letterSpacing: '0.08em',
            textTransform: 'uppercase',
            color: 'var(--ink-faint)',
          }}
        >
          Lỗi 404
        </p>

        <h1 style={{ marginTop: 'var(--s3)' }}>Không tìm thấy trang này</h1>

        <p
          style={{
            marginTop: 'var(--s3)',
            fontSize: '1.0625rem',
            lineHeight: 1.65,
            color: 'var(--ink-soft)',
          }}
        >
          Đường dẫn có thể đã bị đổi, hoặc mã lô trên tem không còn hiệu lực.
        </p>

        <div style={{ display: 'flex', gap: 'var(--s3)', marginTop: 'var(--s5)' }}>
          <Link to="/" className="btn btn--primary">
            Về trang chủ
          </Link>
          <Link to="/truy-xuat" className="btn btn--outline">
            <Icon name="qr" size={16} />
            Tra cứu mã lô
          </Link>
        </div>
      </div>
    </div>
  )
}
