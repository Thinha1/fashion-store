# Kiểm thử giao diện admin

`admin-interface.cjs` chạy Chromium qua Puppeteer, kiểm tra các trang quản trị ở desktop và mobile: độ tương phản nút, đường dẫn sửa, sắp xếp, dialog, chọn nhiều ảnh, báo lỗi biểu mẫu và sidebar. Ảnh chụp được lưu trong `.admin-qa/` (không đưa vào Git).

Chạy trên ứng dụng local đã có tài khoản và dữ liệu demo, với Docker Compose đang hoạt động:

```powershell
docker compose run --rm --no-deps node npm run build
powershell -NoProfile -File tests/Browser/run-admin-interface.ps1
```

Kiểm tra ảnh chung và ảnh biến thể, nút xóa/hoàn tác, dữ liệu form sau lỗi và gallery khi chọn màu:

```powershell
powershell -NoProfile -File tests/Browser/run-admin-interface.ps1 -Script tests/Browser/product-images.cjs
```

Kiểm thử gallery thay bộ ảnh ngay trong trang bằng dữ liệu mẫu, không sửa sản phẩm trong cơ sở dữ liệu. Các kiểm thử PHPUnit trong `ProductVariantImageTest` kiểm tra việc lưu/xóa file trên S3 giả lập và liên kết ảnh với biến thể. Logic chọn size/màu cũng được kiểm thử bằng `node --test tests/JavaScript/*.test.js` trong CI.

Kiểm tra component nhập tiền dùng chung: tự ngăn cách mỗi 3 chữ số, giữ giá đã lưu, dán/sửa/xóa số, phần thập phân, giá trị thực gửi lên form, dòng mới và phục hồi sau lỗi:

```powershell
powershell -NoProfile -File tests/Browser/run-admin-interface.ps1 -Script tests/Browser/currency-input.cjs
```

Component `<x-currency-input id="price" name="price" :value="old('price', $price)" />` hiển thị `1.234.567,89` nhưng gửi `1234567.89`. Mặc định có đơn vị `₫`; để trống vẫn gửi chuỗi rỗng, phù hợp với giá biến thể dùng giá cơ bản khi chưa nhập.

Script PowerShell sử dụng Chromium/Puppeteer từ image Mermaid CLI, tạm chuyển sang asset đã build và khôi phục `public/hot` khi kết thúc. Không chạy đồng thời với lệnh dọn cache view hoặc khởi động lại Vite. Các lệnh PHPUnit trong Docker nên dùng `--user www-data` để cache Blade có cùng quyền với web.

Script không xác nhận xóa dữ liệu thật. Phần kiểm tra xác nhận chặn việc gửi form ngay trong trang; lần gửi form sản phẩm dùng dữ liệu thiếu trường bắt buộc để kiểm tra thông báo lỗi. Chỉ dùng với môi trường kiểm thử.

Nếu có Puppeteer và Chromium cục bộ, có thể chạy trực tiếp:

```sh
ADMIN_TEST_URL=http://localhost:8080 node tests/Browser/admin-interface.cjs
```

Các biến tùy chọn: `ADMIN_TEST_EMAIL`, `ADMIN_TEST_PASSWORD`, `CHROMIUM_PATH`, `PUPPETEER_MODULE`, `ADMIN_TEST_ARTIFACTS`. Mặc định dùng tài khoản demo `admin@example.com` / `password`. `ADMIN_TEST_IMAGE_ORIGIN` dùng để ánh xạ URL MinIO khi trình duyệt chạy trong Docker.
