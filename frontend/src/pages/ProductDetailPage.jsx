/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thanh Liêm (0306241214)
 * CHỨC NĂNG: Trang chi tiết một sản phẩm.
 *
 *   Ngoài ảnh, giá và mô tả thông thường, trang này hiển thị phần mà sàn
 *   TMĐT không có: các LÔ đang bán của sản phẩm, kèm ngày thu hoạch và
 *   số ngày còn lại. Khách thấy rõ mình đang mua lô nào, hái khi nào.
 */

import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { apiGet } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import ProduceMark from '../components/ProduceMark.jsx'
import { useCart } from '../context/CartContext.jsx'
import {
  expiryLevel,
  formatExpiry,
  formatMoney,
  formatQuantity,
} from '../utils/format.js'
import './ProductDetailPage.css'

export default function ProductDetailPage() {
  const { slug } = useParams()
  const navigate = useNavigate()
  const { addItem } = useCart()

  const [product, setProduct] = useState(null)
  const [batches, setBatches] = useState([])
  const [quantity, setQuantity] = useState(1)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [added, setAdded] = useState(false)

  /** Tải chi tiết sản phẩm và danh sách lô của sản phẩm đó. */
  useEffect(() => {
    let cancelled = false

    async function load() {
      setLoading(true)
      setError('')

      try {
        const response = await apiGet(`/products/${slug}`)

        if (cancelled) {
          return
        }

        setProduct(response.data)
        setQuantity(1)

        // Lấy các lô đang bán của riêng sản phẩm này.
        // Dùng API bảng lô công khai rồi lọc theo tên sản phẩm.
        const batchResponse = await apiGet('/available-batches?limit=40').catch(
          () => null,
        )

        if (!cancelled && batchResponse?.data) {
          setBatches(
            batchResponse.data.filter(
              (batch) => batch.product_slug === response.data.slug,
            ),
          )
        }
      } catch {
        if (!cancelled) {
          setError('Không tìm thấy sản phẩm này.')
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    load()

    return () => {
      cancelled = true
    }
  }, [slug])

  if (loading) {
    return (
      <div className="wrap product-detail">
        <div className="product-detail__grid">
          <div className="skeleton product-detail__media" />
          <div>
            <div className="skeleton" style={{ height: 36, width: '70%' }} />
            <div
              className="skeleton"
              style={{ height: 24, width: '35%', marginTop: 16 }}
            />
            <div
              className="skeleton"
              style={{ height: 90, width: '100%', marginTop: 24 }}
            />
          </div>
        </div>
      </div>
    )
  }

  if (error || !product) {
    return (
      <div className="wrap product-detail">
        <div className="empty">
          <p className="empty__title">{error || 'Không tìm thấy sản phẩm'}</p>
          <p>
            <Link to="/san-pham">← Quay lại danh sách sản phẩm</Link>
          </p>
        </div>
      </div>
    )
  }

  const maxQuantity = product.available_quantity
  const outOfStock = !product.in_stock

  /** Thêm vào giỏ, hiện xác nhận tạm thời. */
  function handleAddToCart() {
    addItem(product, quantity)
    setAdded(true)
    window.setTimeout(() => setAdded(false), 2200)
  }

  /** Chặn số lượng vượt tồn kho ngay trên giao diện. */
  function changeQuantity(delta) {
    setQuantity((current) => {
      const next = Math.round((current + delta) * 1000) / 1000

      if (next < 0.5) return 0.5
      if (next > maxQuantity) return maxQuantity

      return next
    })
  }

  return (
    <div className="wrap product-detail">
      <nav className="product-detail__crumbs" aria-label="Đường dẫn">
        <Link to="/">Trang chủ</Link>
        <span aria-hidden="true">/</span>
        <Link to="/san-pham">Sản phẩm</Link>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{product.name}</span>
      </nav>

      <div className="product-detail__grid">
        {/* ---- Ảnh ---- */}
        <div className="product-detail__media">
          {product.thumbnail_url ? (
            <img src={product.thumbnail_url} alt={product.name} />
          ) : (
            <ProduceMark name={product.name} sku={product.sku} size="lg" />
          )}
        </div>

        {/* ---- Thông tin ---- */}
        <div className="product-detail__info">
          <h1 className="product-detail__name">{product.name}</h1>

          {product.origin && (
            <p className="product-detail__origin">
              <Icon name="leaf" size={15} />
              {product.origin}
            </p>
          )}

          <div className="product-detail__price-row">
            <span className="product-detail__price data">
              {formatMoney(product.current_price)}
            </span>
            <span className="product-detail__unit">/ {product.unit}</span>
            {product.sale_price && (
              <span className="product-detail__was data">
                {formatMoney(product.price)}
              </span>
            )}
          </div>

          {product.description && (
            <p className="product-detail__desc">{product.description}</p>
          )}

          {/* ---- Chọn số lượng + thêm giỏ ---- */}
          <div className="product-detail__buy">
            <div className="stepper">
              <button
                type="button"
                className="stepper__btn"
                onClick={() => changeQuantity(-0.5)}
                disabled={outOfStock || quantity <= 0.5}
                aria-label="Giảm số lượng"
              >
                <Icon name="minus" size={16} />
              </button>

              <input
                type="number"
                className="stepper__input data"
                value={quantity}
                min={0.5}
                max={maxQuantity}
                step={0.5}
                onChange={(event) => {
                  const value = Number(event.target.value)

                  if (value > 0 && value <= maxQuantity) {
                    setQuantity(value)
                  }
                }}
                aria-label={`Số lượng, đơn vị ${product.unit}`}
              />

              <button
                type="button"
                className="stepper__btn"
                onClick={() => changeQuantity(0.5)}
                disabled={outOfStock || quantity >= maxQuantity}
                aria-label="Tăng số lượng"
              >
                <Icon name="plus" size={16} />
              </button>

              <span className="stepper__unit">{product.unit}</span>
            </div>

            <button
              type="button"
              className="btn btn--primary product-detail__add"
              onClick={handleAddToCart}
              disabled={outOfStock}
            >
              {outOfStock ? 'Tạm hết hàng' : added ? 'Đã thêm vào giỏ' : 'Thêm vào giỏ'}
              {!outOfStock && !added && <Icon name="cart" size={17} />}
            </button>
          </div>

          {!outOfStock && (
            <p className="product-detail__stock data">
              Còn {formatQuantity(maxQuantity)} {product.unit} trong kho
            </p>
          )}

          {added && (
            <button
              type="button"
              className="product-detail__goto-cart"
              onClick={() => navigate('/gio-hang')}
            >
              Xem giỏ hàng
              <Icon name="arrow" size={15} />
            </button>
          )}
        </div>
      </div>

      {/* ==================================================================
          CÁC LÔ ĐANG BÁN - phần riêng của hệ thống này
          ================================================================== */}
      {batches.length > 0 && (
        <section className="batch-panel">
          <div className="batch-panel__head">
            <h2>Các lô đang bán</h2>
            <p className="batch-panel__note">
              Kho sẽ tự lấy lô gần hết hạn trước. Nếu đơn của bạn cần nhiều hơn
              một lô còn hàng, hệ thống chia đơn ra và ghi rõ từng lô.
            </p>
          </div>

          <div className="batch-panel__list">
            {batches.map((batch, index) => {
              const level = expiryLevel(batch.days_until_expiry ?? 99)

              return (
                <article key={batch.batch_code} className="batch-row">
                  <div className="batch-row__rank data">
                    {String(index + 1).padStart(2, '0')}
                    {index === 0 && <span className="batch-row__next">xuất trước</span>}
                  </div>

                  <div className="batch-row__main">
                    <span className="batch-row__code data">{batch.batch_code}</span>
                    <span className="batch-row__harvest data">
                      Thu hoạch {batch.harvest_date}
                    </span>
                  </div>

                  <div className="batch-row__amount data">
                    {formatQuantity(batch.quantity_available)}
                    <span className="batch-row__unit">{batch.unit}</span>
                  </div>

                  <span className={`tag tag--${level}`}>
                    {formatExpiry(batch.days_until_expiry)}
                  </span>
                </article>
              )
            })}
          </div>
        </section>
      )}
    </div>
  )
}
