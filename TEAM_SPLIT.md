# Phân công triển khai — 3 người

Chia phần **còn lại** của [`IMPLEMENTATION_SPEC.md`](IMPLEMENTATION_SPEC.md) (Giai đoạn 3–7) cho 3 người, dựa trên trạng thái thực tế đã kiểm tra trong code (không chỉ dựa vào spec gốc):

- ✅ Đã xong: Giai đoạn 1 (Nền tảng: auth, phân quyền, layout) và Giai đoạn 2 (Catalog & kho: Brand/Category/Product/Variant/Supplier/GoodsReceipt/Discount biến thể).
- ⚠️ Nợ nhỏ từ Giai đoạn 2: các controller admin (`BrandController`, `ProductController`...) chưa gán `created_by`/`updated_by` khi tạo/sửa — bất kỳ ai đụng lại các controller này nên tiện tay vá (`'created_by' => auth()->id()` lúc create, `'updated_by' => auth()->id()` lúc update).
- 🔄 Quyết định mới: **bỏ hẳn luồng khách vãng lai** — đặt hàng, giỏ hàng, theo dõi/hủy đơn, đổi/trả đều bắt buộc đăng nhập. `carts.guest_token_hash` và `orders.guest_access_token_hash` đã bị xóa khỏi schema (migration `2026_09_11_000200_require_login_for_cart_and_order.php`); `carts.user_id`/`orders.user_id` giờ bắt buộc (not null).

Nguyên tắc chia: mỗi người sở hữu trọn một luồng nghiệp vụ (ít đụng file chung), phần phụ thuộc chéo (giá/giảm giá, dữ liệu đơn hàng) được khai báo rõ interface để 2 người còn lại dùng mà không cần chờ nhau code xong 100%.

📊 **Đọc [`BUSINESS_FLOWS.md`](BUSINESS_FLOWS.md) trước khi code** — sơ đồ trạng thái đơn hàng, trình tự transaction checkout, và hợp đồng `Services/PriceCalculator` đã chốt cứng ở đó; đừng tự suy diễn thứ tự bước khác đi.

## Người 1 — Storefront & giỏ hàng (trọn Giai đoạn 3, trừ đánh giá)

Không phụ thuộc ai, có thể bắt đầu ngay.

- **Catalog phía khách**: `Storefront/HomeController` (banner, danh mục, mới/bán chạy), `Storefront/ProductController` (`/san-pham`, `/danh-muc/{slug}`, `/san-pham/{slug}`) — filter theo query string (danh mục, thương hiệu, khoảng giá, size, màu, còn hàng), phân trang.
- **Giỏ hàng**: `Storefront/CartController` (`/gio-hang/*`, sau middleware `auth`) — chỉ cho tài khoản đã đăng nhập, không có luồng khách vãng lai (đặt hàng bắt buộc đăng nhập, xem `fashion-store-plan.md` §"Quy tắc nghiệp vụ").
- **Yêu thích**: `Storefront/WishlistController` (`/yeu-thich/*`).
- **Sổ địa chỉ**: CRUD địa chỉ trong `/tai-khoan/*` (tối đa 1 địa chỉ mặc định/user).
- **Giao cho Người 2**: route `/gio-hang` phải trả về đúng `Cart`/`CartItem` model chuẩn (không đổi cấu trúc) vì Checkout (Người 2) đọc trực tiếp từ đó.

Test: thêm/sửa số lượng giỏ hàng, khách chưa đăng nhập bị redirect sang đăng nhập khi vào giỏ hàng, wishlist toggle, filter catalog qua query string, sổ địa chỉ.

## Người 2 — Giá/giảm giá, Checkout & quản lý đơn (Giai đoạn 4 + nửa đầu Giai đoạn 5)

Cần `Cart`/`CartItem` đọc được (đã có model sẵn, không cần chờ Người 1 xong UI để bắt đầu code).

- **Service dùng chung** (Người 1 và Người 3 đều gọi lại): `Services/PriceCalculator` — áp discount biến thể đang hiệu lực + coupon (`scope=order`) lên một tập `CartItem`/`OrderItem`. Đây là interface chung, hoàn thành và thông báo sớm cho 2 người kia.
- **Checkout**: `Storefront/CheckoutController` (`/thanh-toan`, sau `auth`) + `Actions/PlaceOrder` (transaction, `lockForUpdate()` trên `product_variants`, snapshot vào `order_items`, sinh `order_number`, `orders.user_id` luôn lấy từ `auth()->id()`).
- **Thanh toán**: `Storefront/PaymentProofController` (`/chung-tu-thanh-toan`, COD/chuyển khoản).
- **Theo dõi/hủy đơn** (khách): `/don-hang/*` (sau `auth`) — chỉ xem/thao tác đơn của chính mình (so `orders.user_id` với `auth()->id()`).
- **Quản lý đơn (admin)**: `Admin/OrderController` — luồng `pending → confirmed → preparing → shipping → delivered` (+ `cancelled`/`returned`), ghi `status_history` + `audit_logs`.
- **Duyệt chứng từ chuyển khoản (admin)**: `Admin/PaymentReviewController`.
- **Giao cho Người 3**: field `orders.status`, `order_items` schema giữ nguyên như migration hiện có (Người 3 dùng để làm return request và dashboard).

Test: đặt hàng COD/chuyển khoản, áp coupon hợp lệ/hết hạn/vượt lượt dùng, 2 đơn đồng thời cùng biến thể không âm kho, hủy đơn hoàn tồn kho, chuyển trạng thái ghi đúng lịch sử, duyệt/từ chối thanh toán.

## Người 3 — Đánh giá, đổi/trả, quản trị nâng cao, Dashboard & Hoàn thiện (Giai đoạn 3 phần Review + nửa sau Giai đoạn 5 + Giai đoạn 6 + Giai đoạn 7)

Phần review/coupon-admin/nhân sự có thể bắt đầu ngay (không phụ thuộc); phần đổi/trả và dashboard cần đợi Người 2 có `Order`/`OrderItem` thật (tối thiểu là schema, không cần chờ UI checkout xong).

- **Đánh giá**: `Storefront/ReviewController` (chỉ tạo được khi `order_item` thuộc đơn đã giao, đúng user/sản phẩm) + `Admin/ReviewController` (duyệt/ẩn).
- **Đổi/trả**: `Storefront/ReturnRequestController` (khách tạo yêu cầu theo `order_item`) + `Admin/ReturnRequestController` + `Actions/ApproveReturnRequest` (hoàn kho + `audit_logs` trong transaction).
- **Coupon**: `Admin/CouponController` (Discount `scope=order`) — dùng lại `Services/PriceCalculator` của Người 2, không viết logic tính giảm giá riêng.
- **Quản trị nhân sự/khách hàng**: `Admin/StaffController`, `Admin/CustomerController`.
- **Nhật ký**: `Admin/AuditLogController` (chỉ đọc).
- **Dashboard thật** (Giai đoạn 6): doanh thu, số đơn, giá trị đơn trung bình, top bán chạy, cảnh báo tồn thấp (query đã gợi ý sẵn ở Giai đoạn 2) — thay thế `DashboardController` placeholder hiện tại.
- **Hoàn thiện** (Giai đoạn 7, làm sau cùng khi 1 & 2 đã ổn định): SEO cơ bản, accessibility, tối ưu ảnh, cache danh mục/thương hiệu, factory + seeder đầy đủ cho 20 bảng (hiện chỉ có `UserFactory`), rà lại toàn bộ test theo tiêu chí nghiệm thu ở plan §5, vá nợ `created_by`/`updated_by` còn thiếu từ Giai đoạn 2.

Test: review chỉ tạo được sau khi giao hàng, đổi/trả hoàn đúng kho, duyệt/từ chối đổi trả, phân quyền nhân viên theo nhóm, số liệu dashboard khớp dữ liệu seed, coupon áp dụng đúng qua `PriceCalculator`.

## Quy tắc chung khi làm song song

- Mỗi người làm trên branch riêng (`feat/<ten>-<giai-doan>`), PR nhỏ theo từng controller/action thay vì gộp cả giai đoạn vào 1 PR.
- Trước khi mở PR: `vendor/bin/pint --dirty --format agent`, chạy test liên quan (`php artisan test --filter=...`).
- Đổi cột/bảng migration: báo trước trong nhóm — cả 3 người đều đọc dữ liệu qua các bảng đã chốt ở `fashion-store-plan.md` §3, tránh sửa schema trùng lúc.
- `Services/PriceCalculator` (Người 2 sở hữu) là điểm dùng chung duy nhất giữa 3 người — Người 1 (hiển thị giá trên catalog/giỏ hàng) và Người 3 (coupon admin) chỉ gọi, không tự viết lại công thức tính giá.
