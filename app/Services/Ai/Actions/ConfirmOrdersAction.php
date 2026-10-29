<?php

namespace App\Services\Ai\Actions;

use App\Actions\ChangeOrderStatus;
use App\Actions\OrderStatusException;
use App\Models\Order;
use App\Models\User;
use App\Services\Ai\Actions\Concerns\DescribesAmounts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * "Xác nhận các đơn COD chờ xác nhận hôm nay" → pending orders moved to
 * "confirmed", one by one through ChangeOrderStatus like the order page's
 * own button. A bank transfer that has not been paid is never confirmed.
 */
class ConfirmOrdersAction implements AgentAction
{
    use DescribesAmounts;

    private const MAX_ORDERS = 50;

    private const NOTE = 'Xác nhận hàng loạt qua trợ lý AI';

    public function __construct(private readonly ChangeOrderStatus $status) {}

    public function type(): string
    {
        return 'confirm_orders';
    }

    public function permission(): string
    {
        return 'orders.manage';
    }

    public function promptSpec(): string
    {
        return <<<'SPEC'
            - "confirm_orders": xác nhận hàng loạt đơn đang "Chờ xác nhận", ví dụ "xác nhận các đơn COD hôm nay",
              "duyệt hết đơn chờ dưới 1 triệu", "xác nhận đơn DH261024YZEBRU". Đơn chuyển khoản chưa nhận tiền luôn bị
              bỏ qua. Dạng:
              {"type": "confirm_orders",
               "order_numbers": ["mã đơn nhân viên nêu cụ thể, hoặc mảng rỗng"],
               "payment_method": "cod" | "bank_transfer" | "" (mọi cách),
               "placed_from": "YYYY-MM-DD HH:MM" hoặc "", "placed_to": "YYYY-MM-DD HH:MM" hoặc "",
               "max_total": tổng tiền tối đa của đơn hoặc null}
            SPEC;
    }

    public function preview(array $params, User $actor): ActionPreview
    {
        $matching = $this->matchingOrders($params);
        [$ready, $unpaid] = $matching->partition(fn (Order $order) => $this->canConfirm($order));

        if ($ready->isEmpty()) {
            throw new InvalidActionException($unpaid->isNotEmpty()
                ? 'Có '.$unpaid->count().' đơn khớp nhưng đều là chuyển khoản chưa nhận tiền, nên chưa xác nhận được.'
                : 'Không có đơn nào đang chờ xác nhận khớp yêu cầu.');
        }

        $warnings = [];
        if ($ready->count() > self::MAX_ORDERS) {
            $warnings[] = 'Có '.$ready->count().' đơn khớp; lần này xác nhận '.self::MAX_ORDERS.' đơn cũ nhất.';
            $ready = $ready->take(self::MAX_ORDERS);
        }
        if ($unpaid->isNotEmpty()) {
            $warnings[] = 'Bỏ qua '.$unpaid->count().' đơn chuyển khoản chưa nhận tiền.';
        }

        return new ActionPreview(
            title: 'Xác nhận '.$ready->count().' đơn hàng',
            lines: $ready->map(fn (Order $order) => $order->order_number.': '.$order->customer_name.', '.$this->money($order->grand_total)
                .', '.($order->payment_method === 'cod' ? 'COD' : 'đã chuyển khoản').', đặt lúc '.$order->placed_at->format('H:i d/m'))->values()->all(),
            confirmLabel: 'Xác nhận '.$ready->count().' đơn',
            params: ['order_ids' => $ready->modelKeys()],
            warnings: $warnings,
        );
    }

    public function execute(array $params, User $actor): ActionResult
    {
        $confirmed = [];
        $problems = [];

        foreach (Order::query()->whereKey($params['order_ids'] ?? [])->orderBy('placed_at')->get() as $order) {
            if (! $this->canConfirm($order)) {
                $problems[] = $order->order_number.': chưa nhận tiền chuyển khoản.';

                continue;
            }

            try {
                $this->status->execute($order, 'confirmed', $actor, self::NOTE);
                $confirmed[] = $order;
            } catch (OrderStatusException $exception) {
                $problems[] = $order->order_number.': '.$exception->getMessage();
            }
        }

        if ($confirmed === []) {
            throw new InvalidActionException('Không xác nhận được đơn nào. '.implode(' ', $problems));
        }

        return new ActionResult(
            message: 'Đã xác nhận '.count($confirmed).' đơn hàng.',
            links: [['label' => 'Xem các đơn đã xác nhận', 'url' => route('admin.orders.index', ['status' => 'confirmed'])]],
            problems: $problems,
        );
    }

    /**
     * Pending orders matching the request, oldest first.
     *
     * @param  array<string, mixed>  $params
     * @return Collection<int, Order>
     */
    private function matchingOrders(array $params): Collection
    {
        $numbers = array_map('mb_strtoupper', $this->strings($params['order_numbers'] ?? []));
        $method = in_array($params['payment_method'] ?? '', array_keys(Order::PAYMENT_METHOD_LABELS), true) ? $params['payment_method'] : null;
        $from = ($params['placed_from'] ?? '') !== '' ? $this->moment($params['placed_from'], 'thời điểm bắt đầu') : null;
        $to = ($params['placed_to'] ?? '') !== '' ? $this->moment($params['placed_to'], 'thời điểm kết thúc', endOfDay: true) : null;
        $maxTotal = $this->number($params['max_total'] ?? null);

        return Order::query()
            ->where('status', 'pending')
            ->when($numbers, fn (Builder $query) => $query->whereIn('order_number', $numbers))
            ->when($method, fn (Builder $query) => $query->where('payment_method', $method))
            ->when($from, fn (Builder $query) => $query->where('placed_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('placed_at', '<=', $to))
            ->when($maxTotal !== null, fn (Builder $query) => $query->where('grand_total', '<=', $maxTotal))
            ->orderBy('placed_at')
            ->get();
    }

    private function canConfirm(Order $order): bool
    {
        return $order->payment_method === 'cod' || $order->payment_status === 'paid';
    }
}
