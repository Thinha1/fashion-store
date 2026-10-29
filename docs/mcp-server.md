# MCP server: hỏi số liệu, đơn và tồn kho của shop từ Claude

Nhân viên hỏi số liệu Dashboard, tra đơn và tồn kho bằng lời thường từ Claude Code hoặc Claude Desktop, ví dụ "doanh thu 7 ngày qua so với tuần trước thế nào?". Server chỉ đọc, chạy tại đường dẫn `/ai/shop` của web. Đặc tả ở mục 3 của `yeu-cau-con-lai.md`.

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

## Cấp token

Token gắn với một vai trò. Tool nào hiện ra và gọi được là tuỳ quyền của vai trò **tại lúc gọi**.

```bash
docker compose exec app php artisan mcp:token admin            # 30 ngày
docker compose exec app php artisan mcp:token staff --days=7 --name="Laptop kho"
docker compose exec app php artisan mcp:token staff --revoke    # thu hồi mọi token của vai trò
```

Token (dạng `fsm_…`) chỉ hiện một lần; hệ thống chỉ lưu bản băm SHA-256. Mỗi token gọi tối đa 60 lần mỗi phút. Mỗi lần gọi tool có một dòng "MCP: tra cứu số liệu" trong Nhật ký hệ thống.

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

### Claude Desktop và claude.ai

Có hai cách. Cách A gọn nhất nhưng tuỳ tài khoản có ô "Request headers" hay không; cách B luôn dùng được.

**Cách A: connector tuỳ chỉnh theo URL** (Customize → Connectors → Add custom connector; gói Team/Enterprise thì Owner thêm ở Organization settings → Connectors):

1. MCP server URL: `https://<tên-máy>.<tailnet>.ts.net/ai/shop`.
2. Authentication: **No sign-in**.
3. Request headers: tên `authorization`, giá trị `Bearer fsm_...` (gõ cả chữ `Bearer ` và dấu cách; Claude gửi đúng nguyên giá trị, không tự thêm).
4. Add, rồi bật connector trong cuộc trò chuyện (nút +, mục Connectors).

Lưu ý:

- Request headers đang ở dạng beta và chỉ một số tổ chức thấy. Không thấy ô này thì dùng cách B. Không chọn "No sign-in" mà để trống header: server sẽ trả 401 nên connector không kết nối được.
- Claude gọi server từ **máy chủ của Anthropic** (dải IP `160.79.104.0/21`), không phải từ máy bro. Vì vậy URL phải truy cập được từ internet (Funnel), địa chỉ chỉ nằm trong tailnet thì không dùng được.
- Không đưa token vào URL (`?token=`): đặc tả MCP cấm và URL bị ghi vào nhật ký.
- Sau khi thêm, không sửa được header; muốn đổi token thì xoá connector rồi thêm lại.

**Cách B: cầu nối `mcp-remote` trong `claude_desktop_config.json`.** `mcp-remote` là chương trình chạy trên máy bro (cần Node.js), nhận lệnh từ Claude Desktop rồi gọi server qua HTTP kèm header. Vì gọi từ máy bro nên cách này dùng được cả khi server chỉ nằm trong tailnet. Mở file ở Settings → Developer → Edit Config (Windows: `%APPDATA%\Claude\claude_desktop_config.json`), thêm mục dưới rồi thoát hẳn và mở lại Claude Desktop:

```json
{
  "mcpServers": {
    "fashion-store": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "https://<tên-máy>.<tailnet>.ts.net/ai/shop", "--header", "Authorization:${MCP_AUTH}"],
      "env": { "MCP_AUTH": "Bearer fsm_..." }
    }
  }
}
```

Chữ `Bearer ` (có dấu cách) nằm trong biến môi trường, không nằm trong `args`: Claude Desktop trên Windows có thể làm hỏng tham số chứa dấu cách. Claude Desktop không nhận mục kiểu `"url": ...` trong file này, chỉ nhận mục chạy lệnh (`command`).

Lỗi kết nối thì xem `%APPDATA%\Claude\logs\mcp.log` và `mcp-server-fashion-store.log`.

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
- `app/Mcp/Tools/`: các tool; `ShopTool` lo kiểm quyền và ghi nhật ký, `DashboardTool` cho các tool số liệu.
- `app/Models/McpToken.php`, `app/Console/Commands/McpTokenCommand.php`: token.
- `tests/Feature/McpServerTest.php`, `McpLookupToolsTest.php`.
