/**
 * NGƯỜI PHỤ TRÁCH: Đào Quốc Đạt (0306241190)
 * CHỨC NĂNG: Điểm khởi động của ứng dụng React.
 *
 *   Thứ tự bọc các Provider rất quan trọng:
 *     BrowserRouter -> AuthProvider -> CartProvider -> App
 *   AuthProvider phải nằm ngoài vì Header cần biết người đang đăng nhập;
 *   CartProvider nằm trong vì giỏ hàng không phụ thuộc tài khoản.
 */

import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'

import './styles/fonts.css'
import './styles/system.css'

import App from './router/App.jsx'
import { AuthProvider } from './context/AuthContext.jsx'
import { CartProvider } from './context/CartContext.jsx'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter>
      <AuthProvider>
        <CartProvider>
          <App />
        </CartProvider>
      </AuthProvider>
    </BrowserRouter>
  </StrictMode>,
)
