# Đặc tả hiện thực dự án (Implementation Spec)

Tài liệu này cụ thể hóa [`fashion-store-plan.md`](../fashion-store-plan.md) (đặc tả nghiệp vụ & dữ liệu, đã chốt) và [`CONTEXT.md`](../CONTEXT.md) (thuật ngữ) thành các hạng mục kỹ thuật cần code, theo đúng 7 giai đoạn ở mục 4 của plan. Đây là checklist triển khai — quy tắc nghiệp vụ chi tiết (số tiền, tồn kho, transaction...) vẫn tra ở plan §3, không lặp lại đầy đủ ở đây.

Quy ước tham chiếu: `S/…` = Storefront, `A/…` = Admin, đường dẫn viết tắt từ `app/`, `resources/views/`, `routes/web.php`.

## Trạng thái hiện tại (đã xong)

- 20 migration bảng nghiệp vụ (`database/migrations/`) + model Eloquent tương ứng (`app/Models/*.php`), dùng `#[Fillable]`/`#[Hidden]` (PHP 8.5 attribute) thay vì property.
- `roles` đã seed sẵn role `customer`; `users.role_id` bắt buộc (not null).
- Vite + Tailwind CSS v4 đã wiring (`vite.config.js`, `resources/css/app.css`); **Alpine.js chưa được thêm** vào `package.json`/`resources/js/app.js` (còn trống).
- `routes/web.php` chỉ có route `/` trả `welcome.blade.php` mặc định của Laravel — **chưa có** controller, request, policy, view, hay route nghiệp vụ nào.
- Test: chỉ có `DatabaseSchemaTest` (kiểm tra đủ 20 bảng) + 2 test mẫu (`ExampleTest`). Factory mới có `UserFactory`.
- `config/store.php` đã có `shipping_fee` từ `STORE_SHIPPING_FEE`.

Mọi việc bên dưới (trừ khi ghi "đã có") là **chưa làm**.

## Cấu trúc thư mục mục tiêu

Theo plan §1, bổ sung dần trong các giai đoạn dưới đây:

```text
app/
├── Actions/            # 1 class = 1 hành vi nghiệp vụ có transaction (đặt hàng, xác nhận phiếu nhập, duyệt đổi/trả...)
├── Http/
│   ├── Controllers/
│   │   ├── Storefront/
│   │   └── Admin/
│   └── Requests/
│       ├── Storefront/
│       └── Admin/
├── Models/              # đã có đủ 20 model
├── Policies/
├── Services/            # tính giá/giảm giá/tồn kho dùng lại giữa storefront & admin
└── View/Components/
```

Không tạo thư mục gốc khác ngoài kế hoạch này (đúng rule Boost "Application Structure & Architecture").

---

## Giai đoạn 1 — Nền tảng (còn thiếu)

Mục tiêu: auth, layout, phân quyền chạy được, chưa cần nghiệp vụ catalog/đơn hàng.

- **Frontend**: thêm `alpinejs` vào `package.json`, import trong `resources/js/app.js`; tạo layout Blade gốc (`resources/views/layouts/app.blade.php` cho storefront, `layouts/admin.blade.php` cho admin) và thư mục `resources/views/components/` cho các Blade Component dùng lại (button, input, alert flash message...).
- **Auth (session, không Sanctum)**:
  - Route + controller `Storefront/Auth`: đăng ký (`/dang-ky`), đăng nhập (`/dang-nhap`), đăng xuất (`/dang-xuat`), quên/đặt lại mật khẩu (`/quen-mat-khau`), xác minh email (dùng `MustVerifyEmail` — `User` đã implement sẵn), hồ sơ (`/tai-khoan`).
  - Form Request riêng cho từng action (`RegisterRequest`, `LoginRequest`...).
  - Rate limit đăng nhập (`throttle` middleware) theo plan §3.
- **Phân quyền**:
  - `app/Policies/*` đọc từ `roles.permissions` (JSON) qua `User->role->permissions`; viết 1 helper/trait `HasPermission` hoặc Gate::before dùng permission code (`products.view`, `inventory.adjust`, `orders.update_status`...) đã liệt kê ở plan §3 bảng `roles`.
  - Middleware nhóm `admin` (prefix `admin`, middleware `auth`, `verified`, 1 middleware quyền tùy chỉnh) bọc mọi route `Admin/*`.
- **Test**: Feature test đăng ký/đăng nhập/đăng xuất, truy cập route `/admin/*` khi chưa đủ quyền → 403.

## Giai đoạn 2 — Catalog và kho

Entity: `Brand`, `Category`, `Product`, `ProductImage`, `ProductVariant`, `Supplier`, `GoodsReceipt`, `GoodsReceiptItem`.

- **Admin CRUD (SSR, Blade form)** cho `Brand`, `Category` (có `parent_id`), `Product` (kèm ảnh + biến thể trong cùng form/nested resource), `Supplier`.
  - Controllers: `Admin/BrandController`, `Admin/CategoryController`, `Admin/ProductController`, `Admin/ProductVariantController` (hoặc nested dưới Product), `Admin/ProductImageController`, `Admin/SupplierController`.
  - Requests validate theo ràng buộc plan §3 (vd. `slug` unique, `sku` unique, chuẩn hóa hoa/thường `size`/`color`, mỗi sản phẩm chỉ 1 ảnh chính, ảnh theo biến thể phải thuộc đúng `product_id`).
  - Upload ảnh: kiểm tra MIME/kích thước/tên file (plan §3); dev lưu local disk, chuẩn bị disk config tương thích S3 cho production.
- **Nhập hàng & tồn kho**:
  - `Admin/GoodsReceiptController` (tạo phiếu `draft`, thêm dòng `GoodsReceiptItem`) + action riêng `Actions/ConfirmGoodsReceipt` (transaction: chuyển `status=confirmed`, cộng `product_variants.stock_quantity`, ghi `audit_logs`, khóa sửa dòng hàng sau khi confirm — theo plan §3 mục 11–12).
  - Cảnh báo tồn thấp: query biến thể có `stock_quantity <= low_stock_threshold`, hiển thị trong màn hình kho (dùng ở Dashboard giai đoạn 6).
- **Giảm giá biến thể** (`Discount` với `scope=variant`): `Admin/DiscountController` cho phần "giảm giá tự động"; validate ràng buộc scope theo plan §3 mục 9 (không được có `code` khi `scope=variant`).
- **Test**: Feature test CRUD từng entity (quyền, validation, redirect+flash), test xác nhận phiếu nhập tăng đúng tồn kho trong transaction, test 2 request xác nhận đồng thời không cộng tồn 2 lần.

## Giai đoạn 3 — Mua hàng (storefront)

- **Catalog phía khách**: `Storefront/HomeController` (banner, danh mục, mới/bán chạy), `Storefront/ProductController` (`/san-pham`, `/danh-muc/{slug}`, `/san-pham/{slug}`) — filter (danh mục, thương hiệu, khoảng giá, size, màu, còn hàng) qua query string bằng Eloquent scope/query object, phân trang, giữ trạng thái filter trên URL để chia sẻ/SEO.
- **Giá hiển thị**: `Services/PriceCalculator` (hoặc tương đương) áp dụng discount biến thể đang hiệu lực — dùng lại được ở giỏ hàng/checkout.
- **Giỏ hàng** (`Cart`, `CartItem`, lưu DB cho cả khách vãng lai lẫn đã đăng nhập):
  - `Storefront/CartController` (`/gio-hang/*`): thêm/sửa/xóa dòng, unique (`cart_id`, `product_variant_id`), giá luôn tính lại chứ không lưu ở cart item (plan §3 mục 14).
  - Guest cart dùng `guest_token_hash` (hash lưu DB, token thật ở cookie); action gộp giỏ khi đăng nhập (`Actions/MergeGuestCartIntoUserCart`, trong transaction, mỗi user chỉ 1 cart `active`).
- **Yêu thích** (`Wishlist`): `Storefront/WishlistController` (`/yeu-thich/*`).
- **Đánh giá** (`Review`): `Storefront/ReviewController` — chỉ cho phép khi `order_item` thuộc đơn đã giao của đúng user/sản phẩm, `order_item_id` unique (plan §3 mục 17).
- **Sổ địa chỉ** (`Address`): CRUD trong `/tai-khoan/*`, đảm bảo tối đa 1 địa chỉ mặc định/user.
- **Test**: thêm/sửa số lượng giỏ hàng, gộp giỏ khi login, review chỉ tạo được sau khi giao hàng, wishlist toggle.

## Giai đoạn 4 — Checkout

- **Tính tiền server-side hoàn toàn** (không tin dữ liệu từ client): subtotal từ cart, áp `Discount` (`scope=order`, tức coupon) kiểm tra thời gian/điều kiện/số lượt dùng, cộng `shipping_fee` cố định từ `config('store.shipping_fee')`.
- `Storefront/CheckoutController` (`/thanh-toan`): hiển thị review đơn + form nhận địa chỉ/thanh toán; `Actions/PlaceOrder` là action trung tâm — transaction bao gồm: `lockForUpdate()` từng `product_variants` liên quan, kiểm tra đủ tồn, trừ kho, snapshot toàn bộ dữ liệu vào `order_items` (giá gốc, giảm giá, tên/sku/size/color tại thời điểm mua), khóa bản ghi `discounts` để tăng `used_count`, sinh `order_number`, sinh `guest_access_token_hash` ngẫu nhiên nếu là khách vãng lai.
- Thanh toán: `payment_method` = `cod` | `bank_transfer`. Với chuyển khoản: form nhập `transaction_code` + upload ảnh chứng từ (`Storefront/PaymentProofController`, route `/chung-tu-thanh-toan`) — set `payment_status=pending_review`.
- Theo dõi/hủy đơn (`/don-hang/*`): route công khai xác thực bằng access token (khách vãng lai) hoặc `auth` (đã đăng nhập) — **không** dùng riêng `order_number` để xác thực (plan §3). Hủy chỉ khi `status=pending`; hoàn tồn kho nếu đã trừ.
- **Test**: đặt hàng COD/chuyển khoản, áp coupon hợp lệ/hết hạn/vượt lượt dùng, 2 đơn mua đồng thời cùng biến thể sắp hết hàng không được âm kho, hủy đơn hoàn đúng tồn kho, truy cập theo dõi đơn sai token bị từ chối.

## Giai đoạn 5 — Vận hành (admin)

- **Quản lý đơn** (`Admin/OrderController`): chuyển trạng thái theo luồng `pending → confirmed → preparing → shipping → delivered`, cộng thêm `cancelled`/`returned`; mỗi lần chuyển append vào `orders.status_history` (JSON: trạng thái cũ/mới, người thao tác, ghi chú, thời điểm) và ghi `audit_logs`.
- **Duyệt chứng từ chuyển khoản** (`Admin/PaymentReviewController`): xác nhận (`payment_status=paid`) hoặc từ chối (`payment_status=rejected`, bắt buộc `payment_rejection_reason`), ghi `payment_reviewed_by`/`payment_reviewed_at`.
- **Đổi/trả** (`ReturnRequest`): `Storefront/ReturnRequestController` (khách tạo yêu cầu theo `order_item`, kèm ảnh + lý do, chỉ khi đơn đã giao và còn thời hạn) + `Admin/ReturnRequestController` (duyệt/từ chối; khi hoàn kho phải tăng tồn + ghi `audit_logs` trong cùng transaction — dùng `Actions/ApproveReturnRequest`, plan §3 mục 19).
- **Duyệt đánh giá** (`Admin/ReviewController`): ẩn/duyệt review vi phạm (`status`: pending/published/hidden).
- **Mã giảm giá dạng coupon** (`Discount` `scope=order`): `Admin/CouponController` CRUD theo thời gian, giá trị đơn tối thiểu, giới hạn tổng lượt dùng và giới hạn mỗi khách.
- **Quản lý nhân sự/khách hàng**: `Admin/StaffController` (gán role, quyền theo nhóm sản phẩm/kho/đơn hàng/khách hàng — Admin toàn quyền), `Admin/CustomerController` (xem khách hàng, địa chỉ, lịch sử mua).
- **Nhật ký** (`AuditLog`): 1 view chỉ-đọc cho Admin (`Admin/AuditLogController`, không có sửa/xóa từ UI theo plan §3 mục 20).
- **Test**: mỗi bước chuyển trạng thái đơn ghi đúng lịch sử, duyệt/từ chối thanh toán, duyệt đổi/trả hoàn đúng kho, phân quyền nhân viên giới hạn đúng theo nhóm.

## Giai đoạn 6 — Dashboard

- `Admin/DashboardController` (`/admin` hoặc `/admin/dashboard`), lọc theo khoảng ngày: doanh thu, số đơn, giá trị đơn trung bình, top sản phẩm bán chạy, danh sách biến thể sắp hết hàng (dùng lại query cảnh báo tồn thấp ở giai đoạn 2).
- Cân nhắc cache các số liệu tổng hợp theo khoảng ngày phổ biến (hôm nay/7 ngày/30 ngày) nếu dataset lớn.
- **Test**: số liệu dashboard khớp với dữ liệu seed cho một khoảng ngày cố định.

## Giai đoạn 7 — Hoàn thiện

Theo plan §5 (tiêu chí nghiệm thu) và §3 (bảo mật):

- SEO cơ bản (title/meta theo trang, slug thân thiện đã có sẵn ở model), accessibility (label, focus, alt ảnh), tối ưu ảnh (resize/format khi upload).
- Bảo mật: rate limit tìm kiếm/tạo đơn (đăng nhập đã làm ở giai đoạn 1), cookie `HttpOnly`/`Secure`/`SameSite`, CSRF trên mọi form, chống mass assignment (đã có `#[Fillable]`, tiếp tục review Form Request cho từng model mới).
- Cache: danh sách danh mục/thương hiệu cho menu, có thể cache kết quả catalog phổ biến.
- Đầy đủ factory + seeder cho 20 bảng (hiện chỉ có `UserFactory`) để demo/test có dữ liệu thật.
- Hoàn thiện README cài đặt (đã có phần Docker; bổ sung hướng dẫn Laragon nếu cần theo plan §1).
- Rà lại toàn bộ test theo plan §5: unit (tính tổng tiền/coupon/phí ship/quyền/chuyển trạng thái), feature (đăng ký, quyền route, CRUD, checkout, kho, thanh toán, đổi/trả), concurrency (2 đơn cùng biến thể), browser test (chọn biến thể, giỏ hàng, filter, màn admin).

---

## Gợi ý thứ tự làm việc

Bám theo thứ tự giai đoạn ở trên — mỗi giai đoạn phụ thuộc dữ liệu/route của giai đoạn trước (vd. checkout cần giỏ hàng, dashboard cần đơn hàng). Trong mỗi giai đoạn, làm theo thứ tự: migration/model (nếu thiếu) → Policy → Form Request → Controller → Action/Service (nếu có nghiệp vụ transaction) → Blade view → test — và chạy `vendor/bin/pint --dirty --format agent` + test liên quan trước khi chuyển hạng mục tiếp theo.
