# MCP server: hỏi số liệu shop từ Claude

Nhân viên hỏi số liệu Dashboard bằng lời thường từ Claude Code hoặc Claude Desktop, ví dụ "doanh thu 7 ngày qua so với tuần trước thế nào?". Server chỉ đọc, chạy tại đường dẫn `/ai/shop` của web. Đặc tả ở mục 3 của `yeu-cau-con-lai.md`.

## Tool

| Tool | Trả về | Quyền cần có |
|---|---|---|
| `sales_summary` | Doanh thu, doanh số, số đơn, giá trị đơn trung bình, % thay đổi so với kỳ trước | `dashboard.view` |
| `sales_series` | Doanh số và số đơn theo giờ, ngày hoặc tháng | `dashboard.view` |
| `top_products` | Sản phẩm bán chạy (tối đa 20) | `dashboard.view` |
| `order_status_breakdown` | Số đơn theo trạng thái | `dashboard.view` |
| `attention_counts` | Đơn chờ xác nhận, biến thể hết hàng, sắp hết hàng | `dashboard.view` |
| `low_stock` | Biến thể sắp hết hàng (tối đa 50) | `dashboard.view` hoặc `inventory.manage` |
| `latest_orders` | Đơn mới nhất, tên khách viết tắt (tối đa 20) | `dashboard.view` hoặc `orders.view` |

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
- `app/Mcp/Tools/`: các tool; `DashboardTool` lo kiểm quyền và ghi nhật ký.
- `app/Models/McpToken.php`, `app/Console/Commands/McpTokenCommand.php`: token.
- `tests/Feature/McpServerTest.php`.
