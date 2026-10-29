<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AttentionCountsTool;
use App\Mcp\Tools\FindCustomerTool;
use App\Mcp\Tools\FindStockTool;
use App\Mcp\Tools\GetCustomerTool;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\LapsedCustomersTool;
use App\Mcp\Tools\LatestOrdersTool;
use App\Mcp\Tools\LowStockTool;
use App\Mcp\Tools\OrderStatusBreakdownTool;
use App\Mcp\Tools\OrdersToProcessTool;
use App\Mcp\Tools\SalesSeriesTool;
use App\Mcp\Tools\SalesSummaryTool;
use App\Mcp\Tools\TopCustomersTool;
use App\Mcp\Tools\TopProductsTool;
use Laravel\Mcp\Server;

/**
 * The shop's MCP server at /ai/shop (routes/ai.php): read-only numbers from
 * the admin dashboard and order and stock look-ups for staff asking from
 * Claude. Which tools a client sees depends on the role behind its token
 * (see ShopTool).
 */
class ShopServer extends Server
{
    protected string $name = 'Fashion Store';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Số liệu bán hàng, đơn hàng và tồn kho của shop thời trang Fashion Store, giống trang Dashboard trong admin. Chỉ đọc.

        - Tiền tính bằng VND, số nguyên. Giờ theo múi giờ Asia/Ho_Chi_Minh.
        - Doanh thu (revenue) chỉ tính đơn đã giao và đã thanh toán; doanh số (sales) tính mọi đơn không bị huỷ.
          Khi người dùng hỏi "doanh thu" chung chung, nêu cả hai và giải thích ngắn sự khác nhau.
        - Khoảng thời gian (period): hom-nay, 7-ngay, 30-ngay (mặc định), 90-ngay, tat-ca. Câu hỏi về khoảng khác
          (ví dụ "tháng 9") không trả lời chính xác được bằng các tool này; hãy nói rõ và dùng khoảng gần nhất.
        - Tên khách hàng đã được viết tắt. Không suy đoán thông tin cá nhân của khách. Không có email, số điện thoại
          đầy đủ hay địa chỉ: muốn xem đủ, mở admin_url trong trang quản trị.
        - Với khách hàng: "số đơn" gồm cả đơn đã huỷ, "đã chi" chỉ tính đơn đã giao và đã thanh toán.
          Tìm khách bằng số điện thoại, email, tên hoặc mã đơn mà người dùng đưa; không có cách liệt kê mọi khách.
        - Không có công cụ nào thay đổi dữ liệu. Xác nhận đơn, soạn phiếu nhập, tạo voucher làm trong trang quản trị
          hoặc qua trợ lý AI trong admin; nếu người dùng nhờ, hãy nói rõ là việc đó không làm được từ đây.
        - Trả lời bằng tiếng Việt.
        MARKDOWN;

    protected array $tools = [
        SalesSummaryTool::class,
        SalesSeriesTool::class,
        TopProductsTool::class,
        OrderStatusBreakdownTool::class,
        AttentionCountsTool::class,
        LowStockTool::class,
        LatestOrdersTool::class,
        OrdersToProcessTool::class,
        GetOrderTool::class,
        FindStockTool::class,
        FindCustomerTool::class,
        GetCustomerTool::class,
        TopCustomersTool::class,
        LapsedCustomersTool::class,
    ];
}
