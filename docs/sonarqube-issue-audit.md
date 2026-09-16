# Rà soát issue SonarQube — 16/09/2026

Đối chiếu 31 issue OPEN/CONFIRMED trên nhánh main của `Thinha1_fashion-store` (commit `1feadd3`) với mã nguồn và HTML sau khi Blade render.

| Nhóm | Số issue | Kết quả |
| --- | ---: | --- |
| Label ở các dòng phiếu nhập | 14 | Sửa liên kết `for`/`id` cho biến thể, số lượng, giá nhập; dùng đoạn văn cho nhãn “Thành tiền” vì đây là nội dung chỉ đọc. Clone đồng thời `name`, `id`, `for` để mỗi dòng có ID riêng. |
| GitHub Actions | 2 | Pin setup-php bằng SHA đầy đủ đã đối chiếu với tag v2 của repository chính thức; dùng `npm ci --ignore-scripts`. |
| Docker PHP chạy root | 1 | Đặt mặc định `USER www-data`; chỉ dịch vụ setup chạy root để khởi tạo và phân quyền named volume. |
| Sonar không theo được thành phần Blade | 12 | HTML đã render có label liên kết đúng. Giữ component dùng chung và ghi nhận là cảnh báo sai của phân tích tĩnh. |
| Vùng cuộn có tabindex | 2 | Giữ khả năng focus để người dùng cuộn bảng/sidebar bằng bàn phím. Đây là ứng dụng có chủ đích của tabindex cho vùng cuộn. |

## Bằng chứng và kiểm thử

`php artisan test --compact --do-not-cache-result --filter=test_rendered_form_controls_have_associated_labels` thất bại trước sửa với `goods-receipts: missing label for items[__INDEX__][product_variant_id]`. Năm nhóm form còn lại đã đạt trước sửa. Sau sửa, cả sáu nhóm đạt; kiểm tra bao gồm trang tạo, trang sửa và template thêm dòng.

`tests/Browser/admin-interface.cjs` kiểm tra `label.control` thuộc đúng dòng phiếu nhập, ID không lặp sau thêm/xóa dòng, click label focus đúng ô nhập, cuộn bảng bằng ArrowRight và sidebar bằng End. Kiểm thử chạy trên desktop/mobile, không lưu hoặc xóa dữ liệu demo.

GitHub Actions chạy cài đặt npm với script bị tắt, sau đó build frontend và chạy JavaScript/PHPUnit như trước. Docker cần được build lại để các dịch vụ PHP nhận user mặc định mới. Dịch vụ `setup` là tác vụ khởi tạo hữu hạn có quyền root; tiến trình phục vụ web và worker sử dụng www-data.

## Các issue cần phân loại false positive trên SonarQube

Các issue dưới đây được giữ nguyên trạng thái trên SonarQube. Không tắt rule, thêm exclusion hoặc sửa role/label chỉ để che cảnh báo. Các cảnh báo nhánh main đã sửa bằng code sẽ được cập nhật khi main được phân tích sau merge.

| Issue | Vị trí khi rà soát | Rule |
| --- | --- | --- |
| `AaCkHbC1cju49QyJ16bn` | `resources/views/layouts/admin.blade.php:15` | `Web:S6845` |
| `AaCix0_MWDjGJCQQisgZ` | `resources/views/components/admin-table.blade.php:12` | `Web:S6845` |
| `AaCix0-fWDjGJCQQisgV` | `resources/views/admin/brands/_form.blade.php:44` | `Web:InputWithoutLabelCheck` |
| `AaCix0-fWDjGJCQQisgU` | `resources/views/admin/brands/_form.blade.php:22` | `Web:InputWithoutLabelCheck` |
| `AaCix09HWDjGJCQQisfm` | `resources/views/admin/categories/_form.blade.php:22` | `Web:InputWithoutLabelCheck` |
| `AaCix09HWDjGJCQQisfn` | `resources/views/admin/categories/_form.blade.php:40` | `Web:InputWithoutLabelCheck` |
| `AaCix089WDjGJCQQisfk` | `resources/views/admin/discounts/_form.blade.php:16` | `Web:InputWithoutLabelCheck` |
| `AaCix089WDjGJCQQisfl` | `resources/views/admin/discounts/_form.blade.php:31` | `Web:InputWithoutLabelCheck` |
| `AaCix0-UWDjGJCQQisgT` | `resources/views/admin/goods-receipts/_form.blade.php:18` | `Web:InputWithoutLabelCheck` |
| `AaCix09TWDjGJCQQisgA` | `resources/views/admin/products/_form.blade.php:29` | `Web:InputWithoutLabelCheck` |
| `AaCix09TWDjGJCQQisgC` | `resources/views/admin/products/_form.blade.php:51` | `Web:InputWithoutLabelCheck` |
| `AaCix0-rWDjGJCQQisgW` | `resources/views/admin/suppliers/_form.blade.php:35` | `Web:InputWithoutLabelCheck` |
| `AaCix0_CWDjGJCQQisgY` | `resources/views/components/input.blade.php:3` | `Web:InputWithoutLabelCheck` |
| `AaCix0-4WDjGJCQQisgX` | `resources/views/components/label.blade.php:3` | `Web:S6853` |
Các form dùng `<x-label for="...">` và component `<x-input id="...">`. Blade tạo `<label for="...">` và chuyển `id` xuống input; Sonar đọc từng file template nên không thấy đầy đủ mối liên hệ này. `AdminInterfaceTest` kiểm tra mối liên hệ trên HTML đầu ra thay vì chỉ tìm chuỗi trong source Blade.

Sidebar và bảng có nội dung tràn. Bỏ tabindex có thể khiến vùng cuộn không sử dụng được bằng bàn phím trên một số trình duyệt; thêm role tương tác không đúng ngữ nghĩa cũng không phải cách sửa thích hợp.

## Nguồn đối chiếu

- [GitHub: pin action bằng commit SHA đầy đủ](https://docs.github.com/en/actions/reference/security/secure-use).
- [npm ci: tùy chọn ignore-scripts](https://docs.npmjs.com/cli/commands/npm-ci/). `npm run build` vẫn được chạy rõ ràng trong bước riêng.
- [W3C: vùng cuộn phải có đường truy cập bằng bàn phím](https://www.w3.org/WAI/standards-guidelines/act/rules/0ssw9k/), gồm ví dụ vùng cuộn có `tabindex="0"`.