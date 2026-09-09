# Fashion Store

Laravel 13 monolith render giao diện phía server bằng Blade. Dự án dùng mô hình 20 bảng domain đã chốt trong `../fashion-store-plan.md`; giỏ hàng được lưu trong MySQL và không có bảng shipping riêng.

## Chạy bằng Docker Compose

Yêu cầu duy nhất: Docker Desktop đang chạy.

```powershell
docker compose up -d --build
```

Lần chạy đầu, service `setup` tự cài Composer dependency, sinh `APP_KEY`, tạo storage link; service `migrate` tự chạy migration. Sau khi hoàn tất:

- Website: http://localhost:8080
- Vite HMR: http://localhost:5173
- MySQL từ máy host: `127.0.0.1:3307`
- MySQL trong Compose: `mysql:3306`

Các service chính gồm PHP 8.4-FPM (`app`), Nginx, MySQL 8.4, Node 22/Vite, queue worker và scheduler.

## Lệnh thường dùng

```powershell
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint
docker compose logs -f
docker compose down
```

Tài khoản được tạo khi chạy seeder:

- Email: `admin@example.com`
- Mật khẩu: `password`

Chỉ dùng tài khoản mặc định này ở môi trường phát triển.

## Database

20 bảng domain:

`users`, `addresses`, `roles`, `brands`, `categories`, `products`, `product_images`, `product_variants`, `discounts`, `suppliers`, `goods_receipts`, `goods_receipt_items`, `carts`, `cart_items`, `orders`, `order_items`, `reviews`, `wishlists`, `return_requests`, `audit_logs`.

Laravel còn tạo các bảng hạ tầng cho session, cache, queue, reset mật khẩu và migration; các bảng này không được tính vào 20 bảng nghiệp vụ.

Phí vận chuyển cố định lấy từ `STORE_SHIPPING_FEE` và được snapshot vào `orders.shipping_fee` khi đặt hàng.
