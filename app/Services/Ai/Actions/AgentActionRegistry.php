<?php

namespace App\Services\Ai\Actions;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Every action the assistant knows, and which of them a given staff member
 * may use: an action is offered in the prompt, previewed and executed only
 * when the staff member holds its permission.
 */
class AgentActionRegistry
{
    /** @var array<string, AgentAction> */
    private array $actions = [];

    public function __construct(
        CreateVouchersAction $vouchers,
        BulkVariantDiscountAction $variantDiscounts,
        ConfirmOrdersAction $orders,
        DraftGoodsReceiptsAction $receipts,
    ) {
        foreach ([$vouchers, $variantDiscounts, $orders, $receipts] as $action) {
            $this->actions[$action->type()] = $action;
        }
    }

    /** The action of this type if the staff member may use it. */
    public function forUser(string $type, User $actor): ?AgentAction
    {
        $action = $this->actions[$type] ?? null;

        return $action && $actor->hasPermission($action->permission()) ? $action : null;
    }

    /**
     * The prompt section on actions, listing only the ones this staff member
     * may use; empty when there are none.
     */
    public function describeForPrompt(User $actor): string
    {
        $specs = collect($this->actions)
            ->filter(fn (AgentAction $action) => $actor->hasPermission($action->permission()))
            ->map(fn (AgentAction $action) => $action->promptSpec())
            ->implode("\n\n");

        if ($specs === '') {
            return '';
        }

        $now = CarbonImmutable::now('Asia/Ho_Chi_Minh');
        $weekday = ['Chủ nhật', 'Thứ hai', 'Thứ ba', 'Thứ tư', 'Thứ năm', 'Thứ sáu', 'Thứ bảy'][$now->dayOfWeek];

        return <<<PROMPT
            HÀNH ĐỘNG TRONG KHU QUẢN TRỊ. Ngoài việc soạn sản phẩm, bạn có thể đề xuất các hành động dưới đây khi
            nhân viên yêu cầu RÕ RÀNG. Hành động KHÔNG chạy ngay: hệ thống kiểm tra, hiện bản xem trước, và chỉ làm
            khi nhân viên bấm xác nhận. Ghi vào key "actions" của JSON trả lời: một mảng object, mỗi object có
            "type" và các tham số như mô tả. Để "actions": [] khi không có hành động nào. Khi trả lời bằng
            "actions", để trống các field soạn sản phẩm. Không lặp lại một hành động ở lượt sau nếu nhân viên không
            yêu cầu lại. Không bịa dữ liệu nhân viên không nói tới: thiếu thông tin bắt buộc thì vẫn đề xuất với
            phần đã rõ, hệ thống sẽ báo phần còn thiếu.

            Bây giờ là {$weekday}, {$now->format('d/m/Y H:i')} (giờ Việt Nam). Ngày giờ trong tham số ghi dạng
            "YYYY-MM-DD HH:MM" theo giờ Việt Nam; hiểu "cuối tuần này", "hết tháng", "từ mai" theo mốc này.

            {$specs}
            PROMPT;
    }
}
