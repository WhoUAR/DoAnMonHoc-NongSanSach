/**
 * NGƯỜI PHỤ TRÁCH: Đào Quốc Đạt (0306241190)
 * CHỨC NĂNG: Cấu hình một chỗ duy nhất để gọi API Backend Laravel.
 *
 *   - `api`      : axios instance đã gắn sẵn địa chỉ Backend và token.
 *   - `apiGet`   : gọi GET và trả về thẳng phần `data` của phản hồi.
 *   - `apiPost`  : gọi POST tương tự.
 *   - `readError`: đọc thông báo lỗi từ Backend để hiện lên màn hình.
 *
 *   Mọi phản hồi của Backend đều có dạng { success, message, data } nên
 *   các hàm dưới đây chỉ cần bóc lớp ngoài, component không phải tự xử lý.
 */

import axios from 'axios'

// Địa chỉ Backend đặt trong file .env của Frontend (VITE_API_URL).
// Nếu chưa cấu hình thì dùng mặc định cổng 8000 của Laravel.
export const API_URL = import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api'

const TOKEN_KEY = 'nongsan_token'

/** Đọc token đang lưu trong trình duyệt. */
export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

/** Lưu token sau khi đăng nhập. */
export function setToken(token) {
  if (token) {
    localStorage.setItem(TOKEN_KEY, token)
  } else {
    localStorage.removeItem(TOKEN_KEY)
  }
}

export const api = axios.create({
  baseURL: API_URL,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: 15000,
})

// Trước mỗi request: tự gắn token nếu đã đăng nhập.
api.interceptors.request.use((config) => {
  const token = getToken()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

// Sau mỗi phản hồi: nếu token hết hạn (401) thì xoá token và phát sự kiện
// để AuthProvider biết mà đăng xuất, tránh tình trạng giao diện "kẹt".
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 && getToken()) {
      setToken(null)
      window.dispatchEvent(new Event('nongsan:unauthorized'))
    }

    return Promise.reject(error)
  },
)

/** Gọi GET, trả về thẳng dữ liệu bên trong `data`. */
export async function apiGet(path, config) {
  const response = await api.get(path, config)

  return response.data
}

/** Gọi POST, trả về thẳng dữ liệu bên trong `data`. */
export async function apiPost(path, body, config) {
  const response = await api.post(path, body, config)

  return response.data
}

/** Gọi PUT, trả về thẳng dữ liệu bên trong `data`. */
export async function apiPut(path, body, config) {
  const response = await api.put(path, body, config)

  return response.data
}

/** Gọi DELETE, trả về thẳng dữ liệu bên trong `data`. */
export async function apiDelete(path, config) {
  const response = await api.delete(path, config)

  return response.data
}

/**
 * Đọc thông báo lỗi từ phản hồi của Backend.
 *
 * Backend trả 3 dạng lỗi:
 *   1. Lỗi validate: { message: 'Dữ liệu không hợp lệ.', errors: { email: ['...'] } }
 *   2. Lỗi nghiệp vụ: { message: 'Còn 7kg, không đủ 50kg.' }
 *   3. Lỗi mạng: không có error.response
 *
 * Hàm này gom cả ba về một chuỗi dễ đọc để hiện lên màn hình.
 */
export function readError(error) {
  if (!error.response) {
    return 'Không kết nối được máy chủ. Kiểm tra Backend đã chạy chưa.'
  }

  const body = error.response.data

  // Lỗi validate: ưu tiên thông báo đầu tiên của từng trường.
  if (body?.errors && typeof body.errors === 'object') {
    const firstField = Object.values(body.errors)[0]

    if (Array.isArray(firstField) && firstField.length > 0) {
      return firstField[0]
    }
  }

  return body?.message || 'Đã có lỗi xảy ra. Vui lòng thử lại.'
}

/**
 * Đọc danh sách lỗi theo từng trường, dùng để hiện đỏ dưới ô nhập liệu.
 * Ví dụ: { email: 'Email này đã được đăng ký.' }
 */
export function readFieldErrors(error) {
  const errors = error.response?.data?.errors

  if (!errors || typeof errors !== 'object') {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : String(messages),
    ]),
  )
}
