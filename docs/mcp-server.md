# MCP server: hỏi và làm việc với shop từ Claude

Nhân viên hỏi số liệu Dashboard, tra đơn và tồn kho bằng lời thường từ Claude Code hoặc Claude Desktop, ví dụ "doanh thu 7 ngày qua so với tuần trước thế nào?". Với token có quyền ghi, Claude còn làm giúp các việc lặp lại mỗi ngày: duyệt đơn hàng loạt, soạn phiếu nhập cho hàng sắp hết, tạo voucher, giảm giá hàng loạt. Server chạy tại đường dẫn `/ai/shop` của web. Đặc tả ở mục 3 của `yeu-cau-con-lai.md`.

## Tool đọc

| Tool | Trả về | Quyền cần có |
|---|---|---|
| `sales_summary` | Doanh thu, doanh số, số đơn, giá trị đơn trung bình, % thay đổi so với kỳ trước | `dashboard.view` |
| `sales_series` | Doanh số và số đơn theo giờ, ngày hoặc tháng | `dashboard.view` |
| `top_products` | Sản phẩm bán chạy (tối đa 20) | `dashboard.view` |
| `order_status_breakdown` | Số đơn theo trạng thái | `dashboard.view` |
| `attention_counts` | Đơn chờ xác nhận, biến thể hết hàng, sắp hết hàng | `dashboard.view` |
| `low_stock` | Biến thể sắp hết hàng kèm số lượng nên nhập (tối đa 50) | `dashboard.view` hoặc `inventory.manage` |
| `latest_orders` | Đơn mới nhất, tên khách viết tắt (tối đa 20) | `dashboard.view` hoặc `orders.view` |
| `orders_to_process` | Đơn "Chờ xác nhận" cũ nhất trước: xác nhận được ngay hay còn chờ tiền, đã chờ bao nhiêu giờ (tối đa 50) | `orders.view` hoặc `orders.manage` |
| `get_order` | Chi tiết một đơn theo mã: dòng hàng, các khoản tiền, lịch sử trạng thái; không có thông tin liên hệ của khách | `orders.view` hoặc `orders.manage` |
| `find_stock` | Tồn kho theo tên sản phẩm hoặc SKU, thu hẹp theo thương hiệu (tối đa 50) | `dashboard.view` hoặc `inventory.manage` |

Kỳ (`period`): `hom-nay`, `7-ngay`, `30-ngay` (mặc định), `90-ngay`, `tat-ca`.

## Tool ghi (hai bước)

Chỉ token tạo với `--write` mới thấy các tool này, và chỉ những tool role của token có quyền. Mỗi việc gồm một tool `propose_*` và `apply_proposal`:

| Tool | Việc làm | Quyền cần có |
|---|---|---|
| `propose_confirm_orders` | Xác nhận hàng loạt đơn chờ xác nhận (tối đa 50). Đơn chuyển khoản chưa nhận tiền luôn bị bỏ qua | `orders.manage` |
| `propose_draft_goods_receipts` | Soạn phiếu nhập **nháp** cho hàng sắp hết, mỗi nhà cung cấp một phiếu (tối đa 200 biến thể). Chưa cộng tồn kho | `inventory.manage` |
| `propose_create_vouchers` | Tạo voucher giảm đơn hoặc freeship (tối đa 10) | `discounts.manage` |
| `propose_bulk_variant_discount` | Giảm giá hàng loạt biến thể theo thương hiệu, danh mục, tên, size, màu (tối đa 500) | `discounts.manage` |
| `apply_proposal` | Thực hiện đề xuất đã xem trước | quyền của đề xuất đó |

1. `propose_*` kiểm tra bằng đúng quy tắc của form trong admin và trả bản xem trước kèm `proposal_id`. **Chưa có gì thay đổi**; các tool này được đánh dấu chỉ đọc nên Claude không hỏi quyền ở bước này.
2. Claude cho nhân viên xem bản xem trước. Nếu đồng ý, Claude gọi `apply_proposal` với `proposal_id`; đây là chỗ duy nhất đổi dữ liệu và Claude sẽ hỏi xác nhận.
3. Đề xuất sống 30 phút, dùng được một lần, chỉ role đã tạo nó dùng được, và quyền được kiểm tra lại lúc thực hiện.

Các tool này dùng lại `AgentAction` của trợ lý AI trong admin nên giới hạn số lượng và quy tắc kiểm tra giống hệt. Phiếu nhập luôn chỉ ở dạng nháp: người có quyền vẫn bấm xác nhận trong admin vì bước đó cộng tồn kho.

Người thực hiện được ghi là tài khoản hệ thống "Claude (MCP) · {tên role}" (mỗi role một tài khoản, tạo ở lần ghi đầu tiên). Tài khoản này không đăng nhập được, không hiện ở danh sách nhân viên và không tính là người phân quyền còn lại. Nó xuất hiện trong lịch sử đơn, người tạo phiếu nhập và Nhật ký hệ thống (`ai.action`, kèm `via: mcp` và tên token).

## Cấp token

Token gắn với một vai trò. Tool nào hiện ra và gọi được là tuỳ quyền của vai trò **tại lúc gọi**.

```bash
docker compose exec app php artisan mcp:token admin            # chỉ đọc, 30 ngày
docker compose exec app php artisan mcp:token staff --days=7 --name="Laptop kho"
docker compose exec app php artisan mcp:token admin --write     # cho phép tool ghi
docker compose exec app php artisan mcp:token staff --revoke    # thu hồi mọi token của vai trò
```

Token (dạng `fsm_…`) chỉ hiện một lần; hệ thống chỉ lưu bản băm SHA-256. Mặc định token chỉ đọc; thêm `--write` thì token thấy thêm các tool ghi mà vai trò có quyền. Mỗi token gọi tối đa 60 lần mỗi phút. Mỗi lần gọi tool có một dòng "MCP: gọi công cụ" trong Nhật ký hệ thống.

## Kết nối

Địa chỉ:

- Trên máy chạy Docker: `http://localhost:8080/ai/shop`
- Qua Tailscale: `https://<tên-máy>.<tailnet>.ts.net/ai/shop`

Không dùng đường dẫn `/mcp`: trên Funnel, đường dẫn này thuộc một MCP server khác.

### Claude Code

```bash
claude mcp add --transport http fashion-store https://<tên-máy>.<tailnet>.ts.net/ai/shop \
  --header "Authorization: Bearer fsm_..."
```

### Claude Desktop

Màn hình Connectors của Claude Desktop chỉ nhận máy chủ dùng đăng nhập OAuth, nên ta đi qua cầu nối `mcp-remote` (cần Node.js). Thêm vào `claude_desktop_config.json` (Settings → Developer → Edit Config) rồi khởi động lại Claude Desktop:

```json
{
  "mcpServers": {
    "fashion-store": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "https://<tên-máy>.<tailnet>.ts.net/ai/shop", "--header", "Authorization:Bearer ${MCP_TOKEN}"],
      "env": { "MCP_TOKEN": "fsm_..." }
    }
  }
}
```

`Authorization:Bearer` viết liền, không có dấu cách sau dấu hai chấm, để tránh lỗi tách tham số trên Windows.

## Thử nhanh bằng curl

```bash
curl -s http://localhost:8080/ai/shop \
  -H "Authorization: Bearer fsm_..." -H "Content-Type: application/json" \
  -H "Accept: application/json, text/event-stream" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"sales_summary","arguments":{"period":"7-ngay"}}}'
```

## Code

- `routes/ai.php`: route và middleware (`AuthenticateMcpToken`, `throttle:mcp`).
- `app/Mcp/Servers/ShopServer.php`: server và hướng dẫn cho model.
- `app/Mcp/Tools/`: các tool; `ShopTool` lo kiểm quyền và ghi nhật ký, `DashboardTool` cho các tool số liệu, `ProposeActionTool` và `ApplyProposalTool` cho hai bước ghi.
- `app/Mcp/McpActor.php`: tài khoản hệ thống của role khi ghi.
- `app/Services/Ai/Actions/`: các hành động dùng chung với trợ lý AI trong admin.
- `app/Models/McpToken.php`, `app/Console/Commands/McpTokenCommand.php`: token.
- `tests/Feature/McpServerTest.php`, `McpLookupToolsTest.php`, `McpWriteToolsTest.php`.
