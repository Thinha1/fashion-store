<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ApplyProposalTool;
use App\Mcp\Tools\AttentionCountsTool;
use App\Mcp\Tools\FindStockTool;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\LatestOrdersTool;
use App\Mcp\Tools\LowStockTool;
use App\Mcp\Tools\OrderStatusBreakdownTool;
use App\Mcp\Tools\OrdersToProcessTool;
use App\Mcp\Tools\ProposeBulkVariantDiscountTool;
use App\Mcp\Tools\ProposeConfirmOrdersTool;
use App\Mcp\Tools\ProposeCreateVouchersTool;
use App\Mcp\Tools\ProposeDraftGoodsReceiptsTool;
use App\Mcp\Tools\SalesSeriesTool;
use App\Mcp\Tools\SalesSummaryTool;
use App\Mcp\Tools\TopProductsTool;
use Laravel\Mcp\Server;

/**
 * The shop's MCP server at /ai/shop (routes/ai.php) for staff asking from
 * Claude: the admin dashboard's numbers, order and stock look-ups, and, for
 * tokens issued with --write, the admin assistant's actions (propose, then
 * apply). Which tools a client sees depends on the role behind its token
 * (see ShopTool).
 */
class ShopServer extends Server
{
    protected string $name = 'Fashion Store';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        Công cụ quản lý shop thời trang Fashion Store cho nhân viên: số liệu giống trang Dashboard trong admin, tra cứu đơn và tồn kho, và (chỉ với token có quyền ghi) các việc lặp lại hằng ngày.

        - Tiền tính bằng VND, số nguyên. Giờ theo múi giờ Asia/Ho_Chi_Minh.
        - Doanh thu (revenue) chỉ tính đơn đã giao và đã thanh toán; doanh số (sales) tính mọi đơn không bị huỷ.
          Khi người dùng hỏi "doanh thu" chung chung, nêu cả hai và giải thích ngắn sự khác nhau.
        - Khoảng thời gian (period): hom-nay, 7-ngay, 30-ngay (mặc định), 90-ngay, tat-ca. Câu hỏi về khoảng khác
          (ví dụ "tháng 9") không trả lời chính xác được bằng các tool này; hãy nói rõ và dùng khoảng gần nhất.
        - Tên khách hàng đã được viết tắt. Không suy đoán thông tin cá nhân của khách.
        - Việc làm thay đổi dữ liệu (xác nhận đơn, soạn phiếu nhập nháp, tạo voucher, giảm giá hàng loạt) luôn hai bước:
          gọi công cụ propose_* để có bản xem trước và proposal_id (chưa thay đổi gì), trình bày bản xem trước cho
          người dùng, và CHỈ khi họ đồng ý rõ ràng mới gọi apply_proposal. Không tự ý gọi apply_proposal, và không
          dùng lại proposal_id đã thực hiện.
        - Phiếu nhập chỉ được soạn ở dạng nháp; người có quyền xác nhận phiếu trong trang quản trị để cộng tồn kho.
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
        ProposeConfirmOrdersTool::class,
        ProposeDraftGoodsReceiptsTool::class,
        ProposeCreateVouchersTool::class,
        ProposeBulkVariantDiscountTool::class,
        ApplyProposalTool::class,
    ];
}
