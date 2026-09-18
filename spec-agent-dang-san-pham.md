# Spec: Agent AI hỗ trợ đăng sản phẩm qua chat widget (PHP thuần)

## Bối cảnh
Website bán quần áo thời trang, code bằng PHP thuần, có trang quản trị (admin) cho nhân viên
thêm sản phẩm mới qua một form (tên, mô tả, giá, size, màu, ảnh...).

## Mục tiêu
Xây một AI agent giúp nhân viên đăng sản phẩm nhanh hơn, thay vì tự gõ tay mô tả sản phẩm.

## Ý tưởng chính
Thêm một khung chat nhỏ (widget) đặt cố định ở góc dưới bên phải màn hình, hiển thị trên các
trang quản trị. Nhân viên dùng khung chat này để:

1. Đính kèm 1 ảnh sản phẩm + gõ tự do thông tin cơ bản (VD: "áo sơ mi này giá 350k, có size S M L, màu trắng").
2. Agent (gọi AI có khả năng đọc ảnh) phân tích ảnh + thông tin, sinh ra:
   - Tên sản phẩm
   - Mô tả sản phẩm (giọng văn trẻ trung, phù hợp shop thời trang)
   - 3-4 bullet điểm nổi bật
   - Tiêu đề SEO
   - Giá, size, màu (nếu nhân viên đã cung cấp, hoặc để trống nếu chưa)
3. Agent hiển thị bản nháp nội dung ngay trong khung chat dưới dạng thẻ preview.
4. Nhân viên có thể tiếp tục gõ để yêu cầu chỉnh sửa (VD: "đổi tên cho sang trọng hơn",
   "viết mô tả ngắn lại") — agent cập nhật lại nội dung dựa trên toàn bộ ngữ cảnh hội thoại trước đó.
5. Khi nhân viên bấm nút "Điền vào form" trong thẻ preview:
   - Agent **tự động điền** các trường tương ứng trong form thêm sản phẩm đang mở trên trang
     (tên, mô tả, giá, size, màu, và cả ảnh đã đính kèm trong chat cũng được chuyển vào ô upload ảnh chính).
   - Agent **KHÔNG tự động submit form**. Nhân viên xem lại, chỉnh tay nếu cần, rồi tự bấm nút
     "Đăng sản phẩm" như quy trình bình thường.

## Nguyên tắc thiết kế quan trọng
- Agent chỉ thao tác trên giao diện (DOM), không được tự ý ghi thẳng vào database — giữ nguyên
  toàn bộ validate và luồng duyệt sẵn có của hệ thống.
- Luôn có bước con người xác nhận trước khi dữ liệu được lưu chính thức.
- Toàn bộ hội thoại (kể cả ảnh gửi lần đầu) cần được gửi lại đầy đủ ở mỗi lượt gọi AI, vì AI
  không tự nhớ giữa các lần gọi API — phải tự quản lý lịch sử hội thoại ở phía client hoặc session.

## Yêu cầu kỹ thuật
- **Ngôn ngữ**: PHP thuần cho backend (không dùng framework), JavaScript thuần cho widget
  (không dùng React/Vue). Không dùng Python ở bất kỳ khâu nào.
- **Bảo mật**:
  - API key (hoặc thông tin kết nối AI) không được để lộ ở phía client — luôn gọi qua một
    PHP endpoint trung gian.
  - Endpoint gọi AI phải đặt sau lớp xác thực đăng nhập admin, chỉ nhân viên đã đăng nhập mới gọi được.
  - Giới hạn kích thước ảnh upload và số lần gọi trong 1 phút để tránh lạm dụng/tốn phí.
- **Khả năng mở rộng model AI đứng sau**: hệ thống cần dễ dàng đổi giữa 2 chế độ:
  - Gọi API AI có sẵn qua HTTP (dạng Anthropic Messages API: model, system, messages, hỗ trợ
    gửi ảnh dạng base64 trong content block `type: image`).
  - Hoặc gọi một model AI tự host nội bộ (expose qua HTTP endpoint dạng tương thích OpenAI-style
    chat completions), phòng trường hợp công ty muốn tự host model sau này vì lý do bảo mật dữ liệu.
  - Vì vậy: phần code build "payload" gửi cho AI và phần "parse kết quả trả về" nên tách riêng
    thành 1-2 hàm/module độc lập, để sau này chỉ cần thay đổi phần này khi đổi nhà cung cấp model,
    không phải sửa lại toàn bộ luồng widget/form.
- **Cấu trúc dữ liệu trả về từ AI** (bắt buộc đúng format để widget parse được):
```json
{
  "name": "string",
  "description": "string",
  "bullets": ["string", "string", "string"],
  "seo_title": "string",
  "price": "string",
  "sizes": ["string"],
  "colors": ["string"]
}
```
- **Điền form**: dùng cơ chế set giá trị input kèm bắn `input`/`change` event, để tương thích
  cả với form PHP thuần lẫn trường hợp sau này form được viết lại bằng JS framework nào đó.
- **Ảnh đính kèm trong chat**: cần tự động gán vào input file ảnh chính của form (dùng
  `DataTransfer` API), tránh nhân viên phải upload ảnh 2 lần.

## Cấu trúc file gợi ý
```
admin/
  ai-agent/
    config.php                 → cấu hình kết nối AI (key, endpoint, model)
    generate-description.php   → endpoint PHP nhận hội thoại, gọi AI, trả JSON
    chat-widget.js              → giao diện + logic chat, gọi endpoint, điền form
    chat-widget.css             → giao diện widget
  products/
    add.php                     → trang thêm sản phẩm hiện có (nhúng thêm widget)
```

## Việc cần làm (task cho AI code)
1. Viết widget chat góc dưới phải: nút bật/tắt, khung chat, input text + đính kèm ảnh.
2. Viết endpoint PHP nhận `messages` (lịch sử hội thoại), forward tới AI, trả về text/JSON kết quả.
3. Viết logic parse kết quả AI thành object, hiển thị thẻ preview trong chat kèm nút
   "Điền vào form" và cho phép tiếp tục yêu cầu chỉnh sửa qua chat.
4. Viết hàm điền form: map các field JSON vào đúng `name` input thật của form, xử lý riêng
   cho checkbox size (nhiều lựa chọn), input text màu, và input file ảnh.
5. Thêm cấu hình dễ chỉnh (map tên field, endpoint URL) ở đầu file JS để dễ tuỳ biến theo
   từng hệ thống form khác nhau mà không phải sửa sâu trong logic.
6. Đảm bảo không tự động submit form — luôn dừng lại ở bước điền, chờ nhân viên tự xác nhận.

## Ngoài phạm vi (không cần làm ở bản này)
- Không cần tự động ghi database.
- Không cần phân tích xu hướng tồn kho hay báo cáo (đây là tính năng khác, làm sau).
- Không cần giao diện chăm sóc khách hàng phía người mua.
