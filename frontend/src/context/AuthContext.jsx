/**
 * NGƯỜI PHỤ TRÁCH: Trần Quốc Khánh (Nhóm trưởng - 0306241206)
 * CHỨC NĂNG: Quản lý trạng thái đăng nhập cho toàn bộ ứng dụng React.
 *
 *   - `user`      : thông tin người đang đăng nhập (null nếu chưa đăng nhập).
 *   - `loading`   : true trong lúc đang kiểm tra token cũ khi mở trang.
 *   - `login()`   : gọi API đăng nhập, lưu token, cập nhật user.
 *   - `register()`: gọi API đăng ký.
 *   - `logout()`  : xoá token, xoá user.
 *   - `hasRole()` : kiểm tra vai trò để ẩn/hiện menu phù hợp.
 *
 *   Dùng React Context để mọi component đều đọc được trạng thái này mà
 *   không phải truyền props qua nhiều tầng.
 */

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { apiGet, apiPost, getToken, setToken } from '../api/client.js'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [loading, setLoading] = useState(true)

  /**
   * Khi mở trang: nếu đã có token trong máy thì hỏi Backend xem còn hợp lệ không.
   * Nhờ vậy người dùng không phải đăng nhập lại mỗi lần tải lại trang.
   */
  useEffect(() => {
    let cancelled = false

    async function restoreSession() {
      if (!getToken()) {
        setLoading(false)
        return
      }

      try {
        const response = await apiGet('/auth/me')

        if (!cancelled) {
          setUser(response.data)
        }
      } catch {
        // Token hỏng hoặc hết hạn -> coi như chưa đăng nhập.
        setToken(null)

        if (!cancelled) {
          setUser(null)
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    restoreSession()

    return () => {
      cancelled = true
    }
  }, [])

  // Lắng nghe sự kiện 401 do api client phát ra để tự đăng xuất.
  useEffect(() => {
    function handleUnauthorized() {
      setUser(null)
    }

    window.addEventListener('nongsan:unauthorized', handleUnauthorized)

    return () => window.removeEventListener('nongsan:unauthorized', handleUnauthorized)
  }, [])

  const login = useCallback(async (email, password) => {
    const response = await apiPost('/auth/login', { email, password })

    setToken(response.data.token)
    setUser(response.data.user)

    return response.data.user
  }, [])

  const register = useCallback(async (payload) => {
    const response = await apiPost('/auth/register', payload)

    setToken(response.data.token)
    setUser(response.data.user)

    return response.data.user
  }, [])

  const logout = useCallback(async () => {
    try {
      await apiPost('/auth/logout')
    } catch {
      // Kể cả khi API lỗi thì vẫn phải xoá token ở máy để đăng xuất được.
    }

    setToken(null)
    setUser(null)
  }, [])

  const refreshUser = useCallback(async () => {
    if (!getToken()) {
      return null
    }

    const response = await apiGet('/auth/me')
    setUser(response.data)

    return response.data
  }, [])

  /** Kiểm tra người dùng có một trong các vai trò được truyền vào hay không. */
  const hasRole = useCallback(
    (...roles) => (user ? roles.includes(user.role) : false),
    [user],
  )

  const value = useMemo(
    () => ({ user, loading, login, register, logout, refreshUser, hasRole }),
    [user, loading, login, register, logout, refreshUser, hasRole],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

/** Hook để component đọc trạng thái đăng nhập. */
export function useAuth() {
  const context = useContext(AuthContext)

  if (context === null) {
    throw new Error('useAuth phải được dùng bên trong <AuthProvider>.')
  }

  return context
}
