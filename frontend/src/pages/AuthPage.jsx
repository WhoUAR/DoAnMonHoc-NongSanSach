/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Trang đăng nhập và đăng ký (hai chế độ trong cùng một trang).
 *
 *   Vì sao gộp chung: người dùng thường bấm "Đăng nhập" rồi mới nhận ra
 *   mình chưa có tài khoản. Chuyển đổi tại chỗ nhanh hơn mở trang khác.
 *
 *   Mật khẩu được gửi nguyên văn qua HTTPS (khi triển khai) và Backend băm
 *   bằng Bcrypt - Frontend không băm gì cả, đúng yêu cầu bảo mật Tuần 2.
 */

import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { readError, readFieldErrors } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import { useAuth } from '../context/AuthContext.jsx'
import './AuthPage.css'

export default function AuthPage({ mode = 'login' }) {
  const isLogin = mode === 'login'

  const navigate = useNavigate()
  const location = useLocation()
  const { login, register } = useAuth()

  const [form, setForm] = useState({
    full_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
  })

  const [errors, setErrors] = useState({})
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  // Sau khi đăng nhập, quay lại đúng trang người dùng định vào.
  const redirectTo = location.state?.from ?? '/'

  function setField(name, value) {
    setForm((current) => ({ ...current, [name]: value }))
    setErrors((current) => ({ ...current, [name]: undefined }))
  }

  async function handleSubmit(event) {
    event.preventDefault()
    setSubmitting(true)
    setError('')
    setErrors({})

    try {
      if (isLogin) {
        await login(form.email, form.password)
      } else {
        await register({
          full_name: form.full_name,
          email: form.email,
          phone: form.phone || null,
          password: form.password,
          password_confirmation: form.password_confirmation,
        })
      }

      navigate(redirectTo, { replace: true })
    } catch (caught) {
      setErrors(readFieldErrors(caught))
      setError(readError(caught))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="auth">
      {/* ---- Cột trái: thông điệp ---- */}
      <div className="auth__aside">
        <Link to="/" className="auth__logo">
          <span className="auth__mark" aria-hidden="true">
            NS
          </span>
          Nông Sản Sạch
        </Link>

        <div className="auth__pitch">
          <h1 className="auth__pitch-title">
            Đăng nhập để đơn hàng của bạn được tích điểm và theo dõi.
          </h1>

          <ul className="auth__benefits">
            <li>
              <Icon name="check" size={16} />
              Xem lại đơn cũ và mã lô của từng món
            </li>
            <li>
              <Icon name="check" size={16} />
              Tích 1 điểm cho mỗi 10.000đ, dùng trừ trực tiếp
            </li>
            <li>
              <Icon name="check" size={16} />
              Đăng ký gói rau giao Thứ 3 và Thứ 6 hằng tuần
            </li>
          </ul>

          <p className="auth__guest">
            Chưa muốn tạo tài khoản? Vẫn{' '}
            <Link to="/thanh-toan">đặt hàng với tư cách khách vãng lai</Link>.
          </p>
        </div>
      </div>

      {/* ---- Cột phải: biểu mẫu ---- */}
      <div className="auth__panel">
        <div className="auth__tabs">
          <Link
            to="/dang-nhap"
            className={`auth__tab ${isLogin ? 'is-active' : ''}`}
            replace
          >
            Đăng nhập
          </Link>
          <Link
            to="/dang-ky"
            className={`auth__tab ${!isLogin ? 'is-active' : ''}`}
            replace
          >
            Đăng ký
          </Link>
        </div>

        <h2 className="auth__title">
          {isLogin ? 'Chào bạn quay lại' : 'Tạo tài khoản mới'}
        </h2>

        <form onSubmit={handleSubmit} noValidate>
          {!isLogin && (
            <>
              <div className="field">
                <label className="field__label" htmlFor="full_name">
                  Họ và tên
                </label>
                <input
                  id="full_name"
                  className={`input ${errors.full_name ? 'input--invalid' : ''}`}
                  value={form.full_name}
                  onChange={(event) => setField('full_name', event.target.value)}
                  autoComplete="name"
                />
                {errors.full_name && <p className="field__error">{errors.full_name}</p>}
              </div>

              <div className="field">
                <label className="field__label" htmlFor="phone">
                  Số điện thoại <span className="field__optional">(không bắt buộc)</span>
                </label>
                <input
                  id="phone"
                  className="input data"
                  value={form.phone}
                  onChange={(event) => setField('phone', event.target.value)}
                  autoComplete="tel"
                  inputMode="tel"
                />
              </div>
            </>
          )}

          <div className="field">
            <label className="field__label" htmlFor="email">
              Email
            </label>
            <input
              id="email"
              type="email"
              className={`input ${errors.email ? 'input--invalid' : ''}`}
              value={form.email}
              onChange={(event) => setField('email', event.target.value)}
              autoComplete="email"
              inputMode="email"
            />
            {errors.email && <p className="field__error">{errors.email}</p>}
          </div>

          <div className="field">
            <label className="field__label" htmlFor="password">
              Mật khẩu
            </label>
            <input
              id="password"
              type="password"
              className={`input ${errors.password ? 'input--invalid' : ''}`}
              value={form.password}
              onChange={(event) => setField('password', event.target.value)}
              autoComplete={isLogin ? 'current-password' : 'new-password'}
            />
            {errors.password && <p className="field__error">{errors.password}</p>}
            {!isLogin && (
              <p className="field__hint">Ít nhất 8 ký tự.</p>
            )}
          </div>

          {!isLogin && (
            <div className="field">
              <label className="field__label" htmlFor="password_confirmation">
                Nhập lại mật khẩu
              </label>
              <input
                id="password_confirmation"
                type="password"
                className="input"
                value={form.password_confirmation}
                onChange={(event) =>
                  setField('password_confirmation', event.target.value)
                }
                autoComplete="new-password"
              />
            </div>
          )}

          {error && (
            <p className="auth__error" role="alert">
              <Icon name="alert" size={15} />
              {error}
            </p>
          )}

          <button
            type="submit"
            className="btn btn--primary btn--block auth__submit"
            disabled={submitting}
          >
            {submitting
              ? 'Đang xử lý…'
              : isLogin
                ? 'Đăng nhập'
                : 'Tạo tài khoản'}
          </button>
        </form>

        <p className="auth__switch">
          {isLogin ? (
            <>
              Chưa có tài khoản? <Link to="/dang-ky">Đăng ký ngay</Link>
            </>
          ) : (
            <>
              Đã có tài khoản? <Link to="/dang-nhap">Đăng nhập</Link>
            </>
          )}
        </p>
      </div>
    </div>
  )
}
