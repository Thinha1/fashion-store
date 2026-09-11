# Sơ đồ nghiệp vụ — Giai đoạn 3-7

Đây là phần "siết" tiếp theo của [`IMPLEMENTATION_SPEC.md`](IMPLEMENTATION_SPEC.md): thay vì mô tả bằng câu chữ, các luồng có nhiều nhánh rẽ hoặc nhiều bên liên quan được chốt bằng sơ đồ để 3 người (xem [`TEAM_SPLIT.md`](TEAM_SPLIT.md)) hiểu **giống hệt nhau** trước khi viết code — tránh tình trạng mỗi người hiểu một kiểu rồi phải sửa lại khi tích hợp. Số liệu/tên bảng/cột đều bám theo [`fashion-store-plan.md`](../fashion-store-plan.md) §3; nếu sơ đồ và plan lệch nhau, plan là nguồn đúng, báo lại để cập nhật sơ đồ.

Quy ước đọc: khối `alt`/`opt` trong sequence diagram là nhánh rẽ bắt buộc phải xử lý (không được bỏ qua case lỗi); mọi thao tác `INSERT/UPDATE` liệt kê trong 1 transaction phải nằm trong **cùng một `DB::transaction()`**.

## 1. Tổng quan luồng nghiệp vụ

Bức tranh toàn cảnh: khách hàng (bắt buộc đăng nhập, xem quyết định ở plan §"Quy tắc nghiệp vụ") đi từ catalog đến sau-bán-hàng, song song với các thao tác admin tương ứng ở từng bước.

```mermaid
flowchart TD
    subgraph KH["Khách hàng (bắt buộc đăng nhập)"]
        A1["Duyệt catalog / tìm kiếm / lọc"] --> A2["Xem chi tiết sản phẩm"]
        A2 --> A3["Thêm vào giỏ hàng"]
        A3 --> A4["Xem giỏ hàng"]
        A4 --> A5["Checkout: chọn địa chỉ + mã giảm giá + phương thức thanh toán"]
        A5 --> A6{"Phương thức thanh toán?"}
        A6 -->|COD| A7["Đặt hàng thành công (pending)"]
        A6 -->|Chuyển khoản| A8["Gửi chứng từ thanh toán"]
        A8 --> A9["Chờ Admin duyệt thanh toán"]
        A7 --> A10["Theo dõi đơn hàng"]
        A9 --> A10
        A10 --> A12["Khách tự hủy được khi status = pending"]
        A10 --> A13["Chờ giao hàng"]
        A13 --> A14["Đơn delivered"]
        A14 --> A15["Đánh giá sản phẩm đã mua"]
        A14 --> A16["Gửi yêu cầu đổi/trả theo từng dòng"]
    end

    subgraph AD["Admin / Nhân viên"]
        B1["Quản lý catalog, biến thể, tồn kho, giảm giá"]
        B2["Duyệt / từ chối chứng từ chuyển khoản"]
        B3["Xử lý đơn: confirmed → preparing → shipping → delivered"]
        B4["Duyệt / từ chối yêu cầu đổi trả"]
        B5["Duyệt / ẩn đánh giá"]
        B6["Xem Dashboard: doanh thu, đơn, tồn thấp"]
    end

    B1 -.-> A1
    B2 --> A9
    B3 --> A13
    B4 --> A16
    B5 --> A15
```

**Chốt lại**: không có nhánh nào cho khách vãng lai; mọi mũi tên trong khối `KH` đều đứng sau middleware `auth`.

## 2. Trạng thái đơn hàng (`orders.status`)

State machine đầy đủ của một đơn — người/hành động nào kích hoạt mỗi cạnh, và tác dụng phụ đi kèm. Đây là sơ đồ quan trọng nhất vì `Admin/OrderController` (Người 2), `PlaceOrder` (Người 2), `Actions/ApproveReturnRequest` (Người 3) và Dashboard (Người 3) đều đọc/ghi field này.

```mermaid
stateDiagram-v2
    [*] --> pending: PlaceOrder Action\n(trừ kho ngay lúc đặt hàng)

    pending --> confirmed: Admin xác nhận đơn
    pending --> cancelled: Khách tự hủy\n(hoàn kho)

    confirmed --> preparing: Admin chuyển sang chuẩn bị hàng
    confirmed --> cancelled: Admin hủy đơn\n(hoàn kho)

    preparing --> shipping: Admin bàn giao vận chuyển

    shipping --> delivered: Admin xác nhận đã giao

    delivered --> returned: Duyệt yêu cầu đổi/trả toàn bộ đơn\n(hoàn kho phần được duyệt)

    cancelled --> [*]
    returned --> [*]
    delivered --> [*]

    note right of pending
        Mỗi lần chuyển trạng thái append
        vào orders.status_history (JSON):
        trạng thái cũ/mới, actor, note, thời điểm.
        Ghi audit_logs song song.
    end note

    note right of confirmed
        payment_status là field độc lập
        (unpaid/pending_review/paid/rejected/refunded),
        không tự động đổi orders.status.
    end note
```

**Chốt lại**:
- Không có cạnh nào bỏ qua bước (vd. `pending` không thể nhảy thẳng sang `shipping`).
- `cancelled`/`returned`/`delivered` là trạng thái cuối — không có UI cho phép chuyển tiếp từ đó (trừ `delivered → returned` qua đúng luồng đổi/trả ở sơ đồ 5).
- Hoàn kho chỉ xảy ra ở 2 cạnh: hủy đơn (`→ cancelled`) và duyệt đổi/trả (`→ returned`) — cả hai đều phải chạy trong transaction kèm ghi `audit_logs`.

## 3. Checkout — trình tự giao dịch (`Actions/PlaceOrder`)

Đây là điểm rủi ro race-condition/tiền bạc cao nhất trong toàn hệ thống, nên chốt cứng thứ tự từng câu lệnh thay vì để mỗi người tự suy diễn.

```mermaid
sequenceDiagram
    actor KH as Khách hàng
    participant CC as CheckoutController
    participant PC as PriceCalculator
    participant PO as PlaceOrder (Action)
    participant DB as MySQL

    KH->>CC: GET /thanh-toan
    CC->>DB: Lấy cart + cart_items của user hiện tại
    CC->>PC: Tính giá từng dòng (áp discount biến thể)
    PC-->>CC: subtotal + từng dòng đã áp giá
    CC-->>KH: Trang review đơn (chọn địa chỉ, mã giảm giá, ptt toán)

    KH->>CC: POST /thanh-toan (address_id, coupon_code?, payment_method)
    activate CC
    CC->>PO: execute(user, cart, input)
    activate PO
    PO->>DB: BEGIN TRANSACTION
    PO->>DB: SELECT ... FOR UPDATE từng product_variant trong giỏ

    alt Tồn kho không đủ cho 1 hoặc nhiều dòng
        PO->>DB: ROLLBACK
        PO-->>CC: InsufficientStockException
        CC-->>KH: 422 quay lại giỏ, báo dòng nào hết hàng
    else Đủ tồn kho
        opt Có nhập coupon
            PO->>PC: Kiểm tra + áp coupon (scope=order)
            alt Coupon không hợp lệ (hết hạn / vượt lượt dùng / dưới min_order_amount)
                PO->>DB: ROLLBACK
                PO-->>CC: InvalidCouponException
                CC-->>KH: 422 báo mã giảm giá không hợp lệ
            end
        end
        PO->>DB: INSERT orders (status=pending, snapshot subtotal/discount/shipping_fee/grand_total/địa chỉ)
        PO->>DB: INSERT order_items (snapshot tên/sku/size/color/giá từng dòng)
        PO->>DB: UPDATE product_variants SET stock_quantity -= quantity
        PO->>DB: UPDATE discounts SET used_count += 1 (nếu dùng coupon)
        PO->>DB: UPDATE carts SET status = converted
        PO->>DB: COMMIT
        PO-->>CC: order (order_number)
        CC-->>KH: Redirect /don-hang/{order_number} + flash "Đặt hàng thành công"
    end
    deactivate PO
    deactivate CC
```

**Chốt lại**:
- `SELECT ... FOR UPDATE` chạy **trước** mọi kiểm tra khác (kể cả coupon) — khóa tồn kho là ưu tiên số 1 để chống bán vượt tồn khi nhiều người checkout cùng lúc.
- Coupon không hợp lệ hay hết hàng đều `ROLLBACK` toàn bộ — không có trạng thái đơn hàng "nửa vời".
- `carts.status = converted` (không xóa cart) để giữ lịch sử; giỏ mới sẽ được tạo ở lần thêm hàng tiếp theo.

## 4. Tính giá & áp dụng giảm giá (`Services/PriceCalculator`)

Người 2 sở hữu service này (xem `TEAM_SPLIT.md`); Người 1 (hiển thị giá ở catalog/giỏ hàng) và Người 3 (test coupon admin) chỉ gọi lại, không viết logic riêng. Sơ đồ này là "hợp đồng" giữa 3 người.

```mermaid
flowchart TD
    S(["Bắt đầu: tính giá 1 dòng cart/order"]) --> V{"Biến thể có discount\nscope=variant đang active?\n(is_active + trong khoảng starts_at..ends_at)"}
    V -->|Có| VP["Áp % hoặc số tiền cố định\n(giới hạn bởi max_discount_amount)"]
    V -->|Không| VN["Dùng giá gốc:\nvariant.price ?? product.base_price"]
    VP --> UP["unit_price = giá sau giảm biến thể"]
    VN --> UP

    UP --> T["subtotal = Σ (unit_price × quantity)"]

    T --> C{"Checkout có nhập mã coupon?"}
    C -->|Không| G["grand_total = subtotal + shipping_fee"]
    C -->|Có| CV{"Coupon hợp lệ?\nis_active, còn hiệu lực thời gian,\nusage_limit chưa đạt,\nusage_limit_per_customer chưa đạt cho user này"}
    CV -->|Không| CE["Báo lỗi, không áp dụng, giữ nguyên subtotal"]
    CV -->|Có| CM{"subtotal >= min_order_amount?"}
    CM -->|Không đủ| CE
    CM -->|Đủ| CA["Áp giảm giá coupon lên subtotal\n(giới hạn bởi max_discount_amount)\ndiscount_amount = phần đã giảm"]

    CE --> G
    CA --> G2["grand_total = subtotal - discount_amount + shipping_fee"]
```

**Chốt lại**:
- Giảm giá biến thể (`scope=variant`) luôn tính **trước**, ảnh hưởng `unit_price` từng dòng; coupon (`scope=order`) tính **sau**, ảnh hưởng `subtotal` toàn đơn — hai loại không cộng dồn theo cách khác.
- `usage_limit_per_customer` kiểm tra theo **user hiện tại**, không phải tổng toàn hệ thống (đó là `usage_limit`).
- Kết quả cuối luôn là 2 số: `discount_amount` và `grand_total` — đây chính là 2 cột snapshot vào `orders`/`order_items`, không tính lại khác đi ở bất kỳ đâu khác (kể cả hiển thị hoá đơn sau này).

## 5. Đổi/trả hàng

```mermaid
flowchart TD
    A(["Đơn ở status = delivered"]) --> B["Khách chọn order_item cần đổi/trả"]
    B --> C{"Còn trong thời hạn đổi/trả?\nvà quantity yêu cầu <= đã mua - đã đổi/trả trước đó"}
    C -->|Không| X["Không cho tạo yêu cầu"]
    C -->|Có| D["Tạo return_requests\n(type=exchange|return, quantity, reason,\nimage_paths, status=pending)"]
    D --> E["Admin xem danh sách yêu cầu chờ duyệt"]
    E --> F{"Admin quyết định"}
    F -->|Từ chối| G["status = rejected\n(bắt buộc admin_note)"]
    F -->|Duyệt| H["status = approved"]
    H --> I["Admin xác nhận hoàn tất: status = completed"]
    I --> J["Transaction: product_variants.stock_quantity += restock_quantity\nghi audit_logs (before/after)"]
    J --> K(["Nếu đổi/trả hết toàn bộ order_item của đơn\ncó thể chuyển orders.status = returned"])
```

**Chốt lại**:
- `quantity yêu cầu <= đã mua - đã đổi/trả trước đó` nghĩa là phải cộng dồn các `return_requests` cũ của cùng `order_item` (kể cả những cái `rejected` không tính, chỉ trừ những cái đã `approved`/`completed`) trước khi cho tạo yêu cầu mới.
- `approved` và `completed` là 2 bước tách biệt (duyệt về mặt quyết định, xong về mặt vận hành — vd. nhận lại hàng vật lý) — chỉ `completed` mới hoàn kho, không hoàn kho ngay lúc `approved`.
