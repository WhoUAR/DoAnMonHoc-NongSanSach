<?php

/**
 * NGƯỜI PHỤ TRÁCH: Nguyễn Thanh Liêm (0306241214)
 * CHỨC NĂNG: Controller xem và tìm kiếm sản phẩm (dành cho khách hàng).
 *
 *   index()   : danh sách sản phẩm có lọc theo danh mục, khoảng giá,
 *               chứng nhận, từ khoá; sắp xếp và phân trang.
 *   show()    : chi tiết một sản phẩm kèm đánh giá và số lượng còn lại
 *               (cộng từ các lô theo FEFO, không phải một cột tồn kho tổng).
 *   categories(): danh sách danh mục cho thanh điều hướng.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    /**
     * DANH SÁCH SẢN PHẨM.
     *
     * Ví dụ: /api/products?category=rau-an-la&min_price=10000&max_price=50000&search=cải
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with('category:id,name,slug')
            ->where('is_active', true);

        // Lọc theo danh mục (nhận cả id lẫn slug cho tiện dùng ở URL).
        if ($request->filled('category')) {
            $category = $request->string('category')->toString();

            $query->whereHas('category', function ($subQuery) use ($category) {
                $subQuery->where('slug', $category)->orWhere('id', $category);
            });
        }

        // Lọc theo khoảng giá.
        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->input('max_price'));
        }

        // Lọc theo chứng nhận (VietGAP, USDA Organic...).
        if ($request->filled('certification')) {
            $query->where('certification_summary', 'like', '%' . $request->input('certification') . '%');
        }

        // Tìm kiếm theo tên hoặc mô tả.
        if ($request->filled('search')) {
            $keyword = $request->string('search')->toString();

            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        // Sắp xếp: mặc định mới nhất; hỗ trợ giá tăng/giảm.
        match ($request->input('sort', 'newest')) {
            'price_asc'  => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name_asc'   => $query->orderBy('name'),
            default      => $query->orderByDesc('id'),
        };

        $perPage = max(1, min((int) $request->input('per_page', 12), 50));
        $products = $query->paginate($perPage);

        // Bổ sung tồn kho khả dụng cho từng sản phẩm trong danh sách.
        $products->getCollection()->transform(function (Product $product) {
            return $this->formatProduct($product);
        });

        return $this->paginated($products, 'Lấy danh sách sản phẩm thành công.');
    }

    /**
     * CHI TIẾT SẢN PHẨM.
     */
    public function show(string $slugOrId): JsonResponse
    {
        $product = Product::query()
            ->with([
                'category:id,name,slug',
                'reviews' => fn ($query) => $query->where('is_visible', true)->latest()->limit(10),
                'reviews.user:id,full_name,avatar_url',
            ])
            ->where('is_active', true)
            ->where(function ($query) use ($slugOrId) {
                $query->where('slug', $slugOrId)->orWhere('id', $slugOrId);
            })
            ->first();

        if ($product === null) {
            return $this->error('Không tìm thấy sản phẩm.', 404);
        }

        $data = $this->formatProduct($product);
        $data['description'] = $product->description;
        $data['gallery']     = $product->gallery ?? [];
        $data['origin']      = $product->origin;
        $data['reviews']     = $product->reviews;
        $data['average_rating'] = round((float) $product->reviews->avg('rating'), 1);

        return $this->success($data, 'Lấy chi tiết sản phẩm thành công.');
    }

    /**
     * DANH SÁCH DANH MỤC.
     */
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success($categories, 'Lấy danh mục thành công.');
    }

    /**
     * BẢNG LÔ ĐANG BÁN (công khai, không cần đăng nhập).
     *
     * Đây là dữ liệu cho bảng "Hôm nay xuất vườn" ở trang chủ.
     * Chỉ trả những thông tin khách được thấy: mã lô, tên sản phẩm,
     * số lượng còn lại, hạn sử dụng. KHÔNG trả số điện thoại nhân viên,
     * không trả thẻ kho, không trả giá vốn.
     */
    public function availableBatches(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->input('limit', 8), 40));

        $batches = \App\Models\Batch::query()
            ->with('product:id,name,slug,unit,thumbnail_url,shelf_life_days')
            ->available()
            ->fefoOrder()
            ->limit($limit)
            ->get()
            ->map(fn (\App\Models\Batch $batch) => [
                'batch_code'        => $batch->batch_code,
                'product_name'      => $batch->product?->name,
                'product_slug'      => $batch->product?->slug,
                'unit'              => $batch->product?->unit,
                'harvest_date'      => $batch->harvest_date?->format('d/m/Y'),
                'expiry_date'       => $batch->expiry_date?->format('d/m/Y'),
                'days_until_expiry' => $batch->daysUntilExpiry(),
                'quantity_available' => (float) $batch->quantity_available,
            ]);

        return $this->success($batches, 'Lấy bảng lô đang bán thành công.');
    }

    /**
     * Định dạng lại dữ liệu sản phẩm trả về cho Frontend.
     * Cố tình chỉ trả các trường cần thiết, không trả toàn bộ Model.
     */
    private function formatProduct(Product $product): array
    {
        return [
            'id'                 => $product->id,
            'name'               => $product->name,
            'slug'               => $product->slug,
            'sku'                => $product->sku,
            'unit'               => $product->unit,
            'price'              => (float) $product->price,
            'sale_price'         => $product->sale_price !== null ? (float) $product->sale_price : null,
            'current_price'      => $product->currentPrice(),
            'thumbnail_url'      => $product->thumbnail_url,
            'origin'             => $product->origin,
            'certification'      => $product->certification_summary,
            'category'           => $product->category?->only(['id', 'name', 'slug']),
            'available_quantity' => $product->totalAvailableQuantity(),
            'in_stock'           => $product->totalAvailableQuantity() > 0,
        ];
    }
}
