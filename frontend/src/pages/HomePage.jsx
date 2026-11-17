/**
 * NGƯỜI PHỤ TRÁCH: Đào Quốc Đạt (0306241190)
 * CHỨC NĂNG: Trang chủ của cửa hàng.
 *
 *   MÀN HÌNH ĐẦU TIÊN KHÔNG PHẢI HERO QUẢNG CÁO.
 *   Thay vì ảnh nền đẹp và một câu khẩu hiệu, trang chủ mở bằng CHÍNH BẢNG
 *   LÔ HÀNG đang bán hôm nay - đúng thứ hệ thống này làm được mà sàn TMĐT
 *   không làm được. Khách nhìn thấy ngay: lô nào, thu hoạch ngày nào, còn
 *   mấy ngày, còn bao nhiêu kg.
 *
 *   BÊN PHẢI là cột giải thích ngắn gọn vì sao điều đó quan trọng
 *   (FEFO - hàng cũ xuất trước) kèm nút mua.
 *
 *   BÊN DƯỚI: danh sách sản phẩm, mỗi thẻ mang theo dải lô của nó; và một
 *   khối giới thiệu quy trình nông trại.
 */

import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { apiGet } from '../api/client.js'
import Icon from '../components/Icon.jsx'
import ProduceMark from '../components/ProduceMark.jsx'
import {
  expiryLevel,
  formatExpiry,
  formatMoney,
  formatQuantity,
} from '../utils/format.js'
import './HomePage.css'

export default function HomePage() {
  const [products, setProducts] = useState([])
  const [batches, setBatches] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')

  /**
   * Tải dữ liệu khi mở trang.
   * Chạy hai request song song để trang hiện nhanh hơn.
   */
  useEffect(() => {
    let cancelled = false

    async function load() {
      try {
        const [productResponse, batchResponse] = await Promise.all([
          apiGet('/products?per_page=6'),
          apiGet('/available-batches?limit=6'),
        ])

        if (cancelled) {
          return
        }

        setProducts(productResponse.data)
        setBatches(batchResponse.data)
      } catch {
        if (!cancelled) {
          setError('Không tải được dữ liệu. Kiểm tra Backend đã chạy chưa.')
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
  }, [])

  // Sản phẩm còn hàng để hiện trên lưới giới thiệu.
  const inStock = products.filter((product) => product.in_stock)

  return (
    <>
      {/* ==================================================================
          PHẦN MỞ ĐẦU: BẢNG LÔ XUẤT VƯỜN
          ================================================================== */}
      <section className="board">
        <div className="wrap board__grid">
          {/* Cột trái: bảng sổ cái các lô đang bán */}
          <div className="board__ledger">
            <div className="board__head">
              <h1 className="board__title">Hôm nay xuất vườn</h1>
              <p className="board__meta data">
                {new Date().toLocaleDateString('vi-VN', {
                  weekday: 'long',
                  day: '2-digit',
                  month: '2-digit',
                  year: 'numeric',
                })}
                <span className="board__meta-sep" aria-hidden="true">/</span>
                Củ Chi, TP.HCM
              </p>
            </div>

            {loading ? (
              <div className="board__loading">
                {[0, 1, 2, 3].map((row) => (
                  <div key={row} className="skeleton board__skeleton" />
                ))}
              </div>
            ) : error ? (
              <div className="empty">
                <p className="empty__title">Chưa kết nối được nông trại</p>
                <p>{error}</p>
              </div>
            ) : (
              <table className="ledger board__table">
                <caption className="visually-hidden">
                  Các lô nông sản đang bán, xếp theo hạn sử dụng gần nhất
                </caption>
                <thead>
                  <tr>
                    <th scope="col">Mã lô</th>
                    <th scope="col">Nông sản</th>
                    <th scope="col" className="num">Còn</th>
                  </tr>
                </thead>
                <tbody>
                  {batches.map((batch) => {
                    const level = expiryLevel(batch.days_until_expiry ?? 99)

                    return (
                      <tr key={batch.batch_code}>
                        <td className="data board__code">{batch.batch_code}</td>
                        <td className="board__product">
                          <Link to={`/san-pham/${batch.product_slug}`}>
                            {batch.product_name}
                          </Link>
                          {/* Thu hoạch + hạn dùng nằm cùng ô để trên điện thoại
                              không mất thông tin quan trọng nhất khi bảng thu hẹp */}
                          <span className="board__sub">
                            <span className="board__cert">
                              Hái {batch.harvest_date}
                            </span>
                            <span className={`tag tag--${level}`}>
                              {formatExpiry(batch.days_until_expiry)}
                            </span>
                          </span>
                        </td>
                        <td className="num data">
                          {formatQuantity(batch.quantity_available)}
                          <span className="board__unit">{batch.unit}</span>
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            )}

            <p className="board__foot">
              Bảng cập nhật trực tiếp từ kho. Mỗi lô là một lần thu hoạch riêng,
              có ngày và hạn dùng riêng.
            </p>
          </div>

          {/* Cột phải: lời giải thích + hành động */}
          <aside className="board__aside">
            <p className="board__claim">
              Chúng tôi bán theo <strong>lô</strong>, không bán theo “còn hàng”.
            </p>

            <p className="board__body">
              Mỗi lần thu hoạch là một lô riêng, ghi rõ ngày hái và hạn dùng.
              Khi bạn đặt hàng, kho tự lấy lô <strong>gần hết hạn trước</strong> —
              nguyên tắc FEFO — nên rau đến tay bạn luôn là rau tươi nhất còn
              trong kho, và nông trại không phải đổ bỏ hàng vì quên ngày.
            </p>

            <ol className="board__steps">
              <li>
                <span className="board__step-num data">01</span>
                <span>Lô hái sáng nay được cân và dán mã</span>
              </li>
              <li>
                <span className="board__step-num data">02</span>
                <span>Đơn của bạn tự trừ đúng lô cũ nhất</span>
              </li>
              <li>
                <span className="board__step-num data">03</span>
                <span>Quét mã trên túi để xem cả mùa vụ</span>
              </li>
            </ol>

            <Link to="/san-pham" className="btn btn--primary btn--block">
              Xem nông sản hôm nay
              <Icon name="arrow" size={17} />
            </Link>

            <Link to="/truy-xuat" className="board__trace">
              <Icon name="qr" size={16} />
              Tra cứu mã lô trên túi hàng
            </Link>
          </aside>
        </div>
      </section>

      {/* ==================================================================
          SẢN PHẨM ĐANG BÁN
          ================================================================== */}
      <section className="section">
        <div className="wrap">
          <div className="section__head">
            <h2>Đang có trong vườn</h2>
            <Link to="/san-pham" className="section__more">
              Tất cả sản phẩm
              <Icon name="arrow" size={15} />
            </Link>
          </div>

          {loading ? (
            <div className="product-grid">
              {[0, 1, 2].map((index) => (
                <div key={index} className="product-card">
                  <div className="skeleton product-card__image" />
                  <div className="product-card__body">
                    <div className="skeleton" style={{ height: 20, width: '60%' }} />
                    <div
                      className="skeleton"
                      style={{ height: 16, width: '40%', marginTop: 12 }}
                    />
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="product-grid">
              {products.slice(0, 6).map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          )}
        </div>
      </section>

      {/* ==================================================================
          QUY TRÌNH NÔNG TRẠI
          ================================================================== */}
      <section className="section section--dark">
        <div className="wrap">
          <div className="section__head">
            <h2>Từ luống đất đến bếp</h2>
          </div>

          <div className="process">
            <article className="process__item">
              <span className="process__num data">01</span>
              <h3>Ghi nhật ký canh tác</h3>
              <p>
                Nhân viên nông trại ghi từng lần gieo, tưới, bón phân ngay trên
                điện thoại. Có ảnh, có ngày, không sửa được sau 24 giờ.
              </p>
            </article>

            <article className="process__item">
              <span className="process__num data">02</span>
              <h3>Thu hoạch, tạo lô</h3>
              <p>
                Mỗi lần hái là một lô mới. Hệ thống tự tính hạn dùng từ số ngày
                bảo quản của từng loại rau củ.
              </p>
            </article>

            <article className="process__item">
              <span className="process__num data">03</span>
              <h3>Giao trong ngày</h3>
              <p>
                Đơn được duyệt trước 9h sẽ giao nội thành trong ngày. Không qua
                chợ đầu mối, không nằm kho lạnh.
              </p>
            </article>
          </div>
        </div>
      </section>
    </>
  )
}

/**
 * Thẻ sản phẩm dùng lại ở trang chủ và trang danh sách.
 * Điểm khác biệt so với thẻ TMĐT thường: có dải lô ở chân thẻ.
 */
function ProductCard({ product }) {
  const outOfStock = !product.in_stock

  return (
    <Link to={`/san-pham/${product.slug}`} className="product-card">
      <div className="product-card__image">
        {product.thumbnail_url ? (
          <img src={product.thumbnail_url} alt={product.name} loading="lazy" />
        ) : (
          <ProduceMark name={product.name} sku={product.sku} />
        )}
        {outOfStock && <span className="product-card__soldout">Tạm hết</span>}
      </div>

      <div className="product-card__body">
        <h3 className="product-card__name">{product.name}</h3>

        <div className="product-card__price-row">
          <span className="product-card__price data">
            {formatMoney(product.current_price)}
          </span>
          <span className="product-card__unit">/ {product.unit}</span>
          {product.sale_price && (
            <span className="product-card__was data">{formatMoney(product.price)}</span>
          )}
        </div>
      </div>

      {/* Dải lô: phần quan trọng nhất của thẻ, lấy từ dữ liệu thật của sản phẩm */}
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
  )
}
