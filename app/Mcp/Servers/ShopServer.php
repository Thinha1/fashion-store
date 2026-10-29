<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AttentionCountsTool;
use App\Mcp\Tools\LatestOrdersTool;
use App\Mcp\Tools\LowStockTool;
use App\Mcp\Tools\OrderStatusBreakdownTool;
use App\Mcp\Tools\SalesSeriesTool;
use App\Mcp\Tools\SalesSummaryTool;
use App\Mcp\Tools\TopProductsTool;
use Laravel\Mcp\Server;

/**
 * The shop's MCP server at /ai/shop (routes/ai.php): read-only numbers from
 * the admin dashboard for staff asking from Claude. Which tools a client
 * sees depends on the role behind its token (see DashboardTool).
 */
class ShopServer extends Server
{
    protected string $name = 'Fashion Store';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Số liệu bán hàng của shop thời trang Fashion Store, giống trang Dashboard trong admin. Chỉ đọc.

        - Tiền tính bằng VND, số nguyên. Giờ theo múi giờ Asia/Ho_Chi_Minh.
        - Doanh thu (revenue) chỉ tính đơn đã giao và đã thanh toán; doanh số (sales) tính mọi đơn không bị huỷ.
          Khi người dùng hỏi "doanh thu" chung chung, nêu cả hai và giải thích ngắn sự khác nhau.
        - Khoảng thời gian (period): hom-nay, 7-ngay, 30-ngay (mặc định), 90-ngay, tat-ca. Câu hỏi về khoảng khác
          (ví dụ "tháng 9") không trả lời chính xác được bằng các tool này; hãy nói rõ và dùng khoảng gần nhất.
        - Tên khách hàng đã được viết tắt. Không suy đoán thông tin cá nhân của khách.
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
    ];
}
