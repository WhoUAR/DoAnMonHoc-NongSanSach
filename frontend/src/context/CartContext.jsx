/**
 * NGƯỜI PHỤ TRÁCH: Trần Thanh An (0306241171)
 * CHỨC NĂNG: Giỏ hàng của khách, lưu trong localStorage.
 *
 *   Vì sao giỏ hàng nằm ở trình duyệt chứ không ở database:
 *   - Khách VÃNG LAI (chưa có tài khoản) vẫn phải thêm hàng vào giỏ được,
 *     đúng yêu cầu Must-have của đề tài.
 *   - Khi khách đăng nhập, giỏ vẫn còn nguyên vì vẫn nằm trong máy họ.
 *
 *   Mỗi dòng hàng lưu: id sản phẩm, tên, đơn vị, giá, số lượng, ảnh.
 *   KHÔNG lưu tồn kho trong giỏ - tồn kho luôn hỏi lại Backend lúc đặt hàng
 *   để tránh trường hợp khách thấy "còn 10kg" nhưng thực tế đã bán hết.
 */

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'

const CartContext = createContext(null)
const STORAGE_KEY = 'nongsan_cart'

/** Đọc giỏ hàng đã lưu khi mở lại trang. */
function loadCart() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)

    return raw ? JSON.parse(raw) : []
  } catch {
    return []
  }
}

export function CartProvider({ children }) {
  const [items, setItems] = useState(loadCart)

  // Mỗi khi giỏ thay đổi thì ghi lại xuống localStorage.
  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items))
  }, [items])

  /**
   * Thêm sản phẩm vào giỏ.
   * Nếu sản phẩm đã có trong giỏ thì cộng dồn số lượng.
   */
  const addItem = useCallback((product, quantity = 1) => {
    setItems((current) => {
      const existing = current.find((item) => item.product_id === product.id)

      if (existing) {
        return current.map((item) =>
          item.product_id === product.id
            ? { ...item, quantity: round(item.quantity + quantity) }
            : item,
        )
      }

      return [
        ...current,
        {
          product_id: product.id,
          name: product.name,
          unit: product.unit,
          price: product.current_price ?? product.price,
          thumbnail_url: product.thumbnail_url,
          quantity: round(quantity),
        },
      ]
    })
  }, [])

  /** Đổi số lượng của một dòng. Truyền 0 hoặc số âm thì xoá dòng đó. */
  const updateQuantity = useCallback((productId, quantity) => {
    setItems((current) => {
      if (quantity <= 0) {
        return current.filter((item) => item.product_id !== productId)
      }

      return current.map((item) =>
        item.product_id === productId ? { ...item, quantity: round(quantity) } : item,
      )
    })
  }, [])

  const removeItem = useCallback((productId) => {
    setItems((current) => current.filter((item) => item.product_id !== productId))
  }, [])

  const clearCart = useCallback(() => setItems([]), [])

  // Tổng tiền hàng và tổng số món, tính lại mỗi khi giỏ đổi.
  const totals = useMemo(() => {
    const totalQuantity = items.reduce((sum, item) => sum + item.quantity, 0)
    const subtotal = items.reduce((sum, item) => sum + item.quantity * item.price, 0)

    return { totalQuantity, subtotal }
  }, [items])

  const value = useMemo(
    () => ({ items, addItem, updateQuantity, removeItem, clearCart, ...totals }),
    [items, addItem, updateQuantity, removeItem, clearCart, totals],
  )

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>
}

/** Làm tròn 3 chữ số thập phân - khớp với kiểu decimal(12,3) trong MySQL. */
function round(value) {
  return Math.round(Number(value) * 1000) / 1000
}

export function useCart() {
  const context = useContext(CartContext)

  if (context === null) {
    throw new Error('useCart phải được dùng bên trong <CartProvider>.')
  }

  return context
}
