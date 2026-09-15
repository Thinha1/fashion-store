# Tóm tắt phiên: Hiển thị sản phẩm (UI Storefront)

Nhánh: `feat/Hien_Thi_San_Pham-UI`

## 1. Tính năng đã làm

### Trang danh sách sản phẩm `/san-pham`
- Controller mới `app/Http/Controllers/Storefront/ProductController.php` (`index`, `show`).
- Lưới sản phẩm kiểu editorial (`.pg-card`): ảnh 3:4, brand, tên, giá, badge "Nổi bật"/"Hết hàng".
- Thanh công cụ: số lượng sản phẩm, sắp xếp (mới nhất / giá tăng / giá giảm), đổi mật độ lưới 2/3/4 cột (Alpine + localStorage).
- Bộ lọc theo **danh mục 3 cấp** (Nhóm → Loại → Kiểu) và theo **thương hiệu**, kết hợp được với nhau qua query string.

### Trang chi tiết sản phẩm `/san-pham/{slug}`
- Gallery: thumbnail dọc + ảnh lớn có nút trước/sau.
- Chọn màu (chỉ Đen/Trắng, dạng chấm tròn) và size; tự khớp sang tổ hợp biến thể hợp lệ, size không có sẵn bị vô hiệu hóa.
- Giá/tồn kho động theo biến thể, bộ đếm số lượng.
- Nút "Thêm vào giỏ" chỉ hiện thông báo (tính năng giỏ hàng chưa có trong codebase).
- Tên thương hiệu link sang trang bộ sưu tập; mục "Cùng thương hiệu".

### Bộ sưu tập theo thương hiệu
- Controller mới `CollectionController`: `/bo-suu-tap` (danh sách brand) và `/bo-suu-tap/{brand}` (mọi sản phẩm của brand, lọc theo loại).

### Trang chủ
- Thêm khu "Sản phẩm nổi bật" (8 sản phẩm `is_featured`).

### Header / Mega-menu
- Navbar có dropdown Áo / Quần / Phụ kiện kiểu YaMe: mỗi cột là Loại, bên dưới là các Kiểu (link lọc sẵn). Loại chưa chia kiểu (Balo, Tất) liệt kê sản phẩm.
- Dữ liệu menu share qua `View::composer('layouts.app')` trong `AppServiceProvider` (không cache — cache DB làm hỏng Eloquent Collection lồng nhau).
- Thêm link "Sản phẩm", "Bộ sưu tập"; menu mobile hiện đủ 3 cấp.
- Sửa layout: chỉ trang auth/profile dùng khung `auth-shell`, các trang khác full-width.

## 2. Sửa lỗi
- `Admin/ProductController::storeProductImages()` luôn set `is_primary = false` → ảnh upload không hiện trên card. Nay ảnh đầu tiên tự thành ảnh chính.
- `@apply group` làm hỏng build Tailwind v4 → bỏ khỏi `@apply`, thêm class `group` trực tiếp trong HTML.
- Hàng chip lọc bị giãn do kế thừa `justify-between` → tách class `.catalog-filter-row`.

## 3. Dữ liệu (chỉ trong DB local, KHÔNG nằm trong git)
- Đã `db:seed` (roles + admin). Xóa 16 sản phẩm mock ban đầu.
- 8 thương hiệu: Claude, Codex, Gemini, GPT, Grok, DeepSeek, Qwen, Kimi.
- Cây danh mục 3 cấp:
  - Áo → Áo Thun (Cổ Tròn, Cổ Polo, TankTop) / Áo Sơ Mi (Tay Ngắn, Tay Dài) / Áo Khoác (Hoodie, Gió, Bomber)
  - Quần → Quần Jeans (Slim Fit, Regular Fit) / Quần Tây (Slim Fit, Regular Fit) / Quần Đùi (Easy Pant, Casual)
  - Phụ kiện → Nón (Dad Hat, Bucket) / Balo / Tất
- 57 sản phẩm, tên dạng `<Kiểu> <Thương hiệu> [Model]`; màu biến thể chỉ Đen/Trắng; tồn kho demo gán ngẫu nhiên.
- File import mẫu: `fashion-store-agents-import.xlsx` (tạo bằng xlsxwriter — openpyxl ghi inline string khiến PhpSpreadsheet trả `RichText` và import lỗi).
- Ảnh upload lưu trên MinIO (Docker volume), không đi theo git.

## 4. Môi trường local
- `.env`: đổi `DB_FORWARD_PORT=3308`, `MAILPIT_FORWARD_PORT=8026` (tránh trùng project klcn041). `.env` không commit.
- Mailpit: http://localhost:8026 — MinIO console: http://localhost:9001.
- Nếu thêm CSS mà trang không đổi: `docker compose restart node` (Vite dev cache bản CSS cũ).

## 5. File thay đổi
- Sửa: `app/Http/Controllers/Admin/ProductController.php`, `app/Http/Controllers/Storefront/HomeController.php`, `app/Providers/AppServiceProvider.php`, `resources/css/app.css`, `resources/views/layouts/app.blade.php`, `resources/views/storefront/home.blade.php`, `routes/web.php`
- Mới: `app/Http/Controllers/Storefront/ProductController.php`, `app/Http/Controllers/Storefront/CollectionController.php`, `resources/views/storefront/products/{index,show}.blade.php`, `resources/views/storefront/collections/{index,show}.blade.php`

## 6. Chưa làm / lưu ý
- Giỏ hàng, wishlist chưa có.
- Chưa viết feature test cho các trang mới.
- Không nên commit: `.claude/skills/archify/`, `skills-lock.json`, `fashion-store-architecture.html` (sản phẩm phụ của việc thử skill Archify).
