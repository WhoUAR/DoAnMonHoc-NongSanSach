/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thanh Liêm (0306241214)
 * CHỨC NĂNG: Trang danh sách sản phẩm có bộ lọc.
 *
 *   Bộ lọc gồm: danh mục, khoảng giá, chứng nhận, từ khoá, cách sắp xếp.
 *   Mỗi khi đổi bộ lọc, component gọi lại API và cập nhật thanh địa chỉ
 *   (query string) để khách có thể chia sẻ hoặc lưu lại đường dẫn đã lọc.
 *
 *   Đây là phần "Xem & Tìm kiếm sản phẩm" thuộc nhóm Must-have.
 */

import { useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { apiGet } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import ProduceMark from '../components/ProduceMark.jsx'
import { formatMoney, formatQuantity } from '../utils/format.js'
import './ProductListPage.css'

// Các khoảng giá dựng sẵn cho dễ chọn trên điện thoại.
const PRICE_RANGES = [
  { key: 'all', label: 'Mọi mức giá', min: '', max: '' },
  { key: 'under-20', label: 'Dưới 20.000đ', min: '', max: '20000' },
  { key: '20-40', label: '20.000 – 40.000đ', min: '20000', max: '40000' },
  { key: 'over-40', label: 'Trên 40.000đ', min: '40000', max: '' },
]

const SORT_OPTIONS = [
  { value: 'newest', label: 'Mới nhất' },
  { value: 'price_asc', label: 'Giá thấp đến cao' },
  { value: 'price_desc', label: 'Giá cao đến thấp' },
  { value: 'name_asc', label: 'Tên A–Z' },
]

export default function ProductListPage() {
  const [searchParams, setSearchParams] = useSearchParams()

  const [products, setProducts] = useState([])
  const [categories, setCategories] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  // Đọc bộ lọc từ thanh địa chỉ.
  const category = searchParams.get('category') || ''
  const search = searchParams.get('search') || ''
  const sort = searchParams.get('sort') || 'newest'
  const priceKey = searchParams.get('price') || 'all'

  // Ô tìm kiếm cần state riêng để không gọi API mỗi lần gõ một chữ.
  const [searchInput, setSearchInput] = useState(search)

  const activePriceRange = useMemo(
    () => PRICE_RANGES.find((range) => range.key === priceKey) ?? PRICE_RANGES[0],
    [priceKey],
  )

  /** Cập nhật một tham số bộ lọc trên thanh địa chỉ. */
  function setFilter(key, value) {
    const next = new URLSearchParams(searchParams)

    if (value) {
      next.set(key, value)
    } else {
      next.delete(key)
    }

    // Đổi bộ lọc thì quay về trang 1.
    next.delete('page')
    setSearchParams(next)
  }

  // Tải danh mục một lần khi mở trang.
  useEffect(() => {
    apiGet('/categories')
      .then((response) => setCategories(response.data))
      .catch(() => setCategories([]))
  }, [])

  // Tải sản phẩm mỗi khi bộ lọc đổi.
  useEffect(() => {
    let cancelled = false

    async function load() {
      setLoading(true)
      setError('')

      const query = new URLSearchParams({ per_page: '12', sort })

      if (category) query.set('category', category)
      if (search) query.set('search', search)
      if (activePriceRange.min) query.set('min_price', activePriceRange.min)
      if (activePriceRange.max) query.set('max_price', activePriceRange.max)

      try {
        const response = await apiGet(`/products?${query.toString()}`)

        if (!cancelled) {
          setProducts(response.data)
          setMeta(response.meta)
        }
      } catch {
        if (!cancelled) {
          setError('Không tải được danh sách sản phẩm.')
          setProducts([])
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
  }, [category, search, sort, activePriceRange])

  /** Gửi ô tìm kiếm (chạy khi bấm Enter hoặc rời ô). */
  function submitSearch(event) {
    event.preventDefault()
    setFilter('search', searchInput.trim())
  }

  const hasFilters = Boolean(category || search || priceKey !== 'all')

  return (
    <div className="wrap product-list">
      {/* ---- Dòng tiêu đề ---- */}
      <div className="product-list__head">
        <h1>Nông sản đang bán</h1>
        <p className="product-list__count data">
          {meta ? `${meta.total} mặt hàng` : 'Đang tải…'}
        </p>
      </div>

      {/* ---- Thanh lọc ---- */}
      <div className="filters">
        <form className="filters__search" onSubmit={submitSearch} role="search">
          <Icon name="search" size={17} />
          <input
            type="search"
            className="filters__input"
            placeholder="Tìm rau củ, trái cây…"
            value={searchInput}
            onChange={(event) => setSearchInput(event.target.value)}
            aria-label="Tìm sản phẩm"
          />
          <button type="submit" className="btn btn--solid btn--sm">
            Tìm
          </button>
        </form>

        <div className="filters__row">
          {/* Danh mục */}
          <label className="filters__field">
            <span className="filters__label">Danh mục</span>
            <select
              className="select select--compact"
              value={category}
              onChange={(event) => setFilter('category', event.target.value)}
            >
              <option value="">Tất cả</option>
              {categories.map((item) => (
                <option key={item.id} value={item.slug}>
                  {item.name}
                </option>
              ))}
            </select>
          </label>

          {/* Giá */}
          <label className="filters__field">
            <span className="filters__label">Giá</span>
            <select
              className="select select--compact"
              value={priceKey}
              onChange={(event) => setFilter('price', event.target.value)}
            >
              {PRICE_RANGES.map((range) => (
                <option key={range.key} value={range.key}>
                  {range.label}
                </option>
              ))}
            </select>
          </label>

          {/* Sắp xếp */}
          <label className="filters__field">
            <span className="filters__label">Sắp xếp</span>
            <select
              className="select select--compact"
              value={sort}
              onChange={(event) => setFilter('sort', event.target.value)}
            >
              {SORT_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </label>

          {hasFilters && (
            <button
              type="button"
              className="filters__clear"
              onClick={() => {
                setSearchInput('')
                setSearchParams(new URLSearchParams())
              }}
            >
              <Icon name="close" size={14} />
              Xoá bộ lọc
            </button>
          )}
        </div>
      </div>

      {/* ---- Kết quả ---- */}
      {loading ? (
        <div className="product-grid">
          {[0, 1, 2, 3, 4, 5].map((index) => (
            <div key={index} className="product-card">
              <div className="skeleton product-card__image" />
              <div className="product-card__body">
                <div className="skeleton" style={{ height: 20, width: '65%' }} />
                <div
                  className="skeleton"
                  style={{ height: 16, width: '40%', marginTop: 12 }}
                />
              </div>
            </div>
          ))}
        </div>
      ) : error ? (
        <div className="empty">
          <p className="empty__title">Không tải được dữ liệu</p>
          <p>{error}</p>
        </div>
      ) : products.length === 0 ? (
        <div className="empty">
          <p className="empty__title">Không tìm thấy sản phẩm nào</p>
          <p>Thử bỏ bớt bộ lọc, hoặc tìm với từ khoá khác.</p>
        </div>
      ) : (
        <div className="product-grid">
          {products.map((product) => (
            <Link
              key={product.id}
              to={`/san-pham/${product.slug}`}
              className="product-card"
            >
              <div className="product-card__image">
                {product.thumbnail_url ? (
                  <img src={product.thumbnail_url} alt={product.name} loading="lazy" />
                ) : (
                  <ProduceMark name={product.name} sku={product.sku} />
                )}
                {!product.in_stock && (
                  <span className="product-card__soldout">Tạm hết</span>
                )}
              </div>

              <div className="product-card__body">
                <h2 className="product-card__name">{product.name}</h2>
                <div className="product-card__price-row">
                  <span className="product-card__price data">
                    {formatMoney(product.current_price)}
                  </span>
                  <span className="product-card__unit">/ {product.unit}</span>
                  {product.sale_price && (
                    <span className="product-card__was data">
                      {formatMoney(product.price)}
                    </span>
                  )}
                </div>
              </div>

              <div className="product-card__strip">
                <span className="data">
                  Còn <strong>{formatQuantity(product.available_quantity)}</strong>{' '}
                  {product.unit}
                </span>
                {product.certification && (
                  <span className="product-card__cert">{product.certification}</span>
                )}
              </div>
            </Link>
          ))}
        </div>
      )}
    </div>
  )
}
