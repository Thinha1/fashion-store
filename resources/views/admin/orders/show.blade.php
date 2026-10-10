@extends('layouts.admin')

@section('title', 'Đơn '.$order->order_number)

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Đơn '.$order->order_number,
        'subtitle' => 'Đặt lúc '.$order->placed_at->format('H:i d/m/Y').' · '.$order->paymentMethodLabel(),
    ])

    @php
        $nextSteps = collect($order->nextStaffStatuses());
        $forwardSteps = $nextSteps->reject(fn ($next) => $next === 'cancelled');
        $canCancel = $nextSteps->contains('cancelled');
    @endphp

    <div class="admin-detail-grid">
        <div class="space-y-6">
            <x-admin.panel title="Sản phẩm ({{ $order->items->count() }})" icon="box">
                <x-admin-table :header="['Sản phẩm', 'Màu / Size', 'SKU', 'SL', 'Đơn giá', 'Thành tiền']">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="px-4 py-3 text-gray-900">{{ $item->product_name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $item->color_name }} / {{ $item->size_name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $item->sku }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                <x-money :amount="$item->unit_price" />
                                @if ((float) $item->discount_amount > 0)
                                    <s class="block text-xs text-gray-400"><x-money :amount="$item->original_unit_price" /></s>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-medium text-gray-900"><x-money :amount="$item->line_total" /></td>
                        </tr>
                    @endforeach
                </x-admin-table>

                <dl class="mt-5 ml-auto max-w-xs space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Tạm tính</dt><dd><x-money :amount="$order->subtotal" /></dd></div>
                    @if ((float) $order->discount_amount > 0)
                        <div class="flex justify-between"><dt class="text-gray-500">Mã giảm giá {{ $order->discount?->code }}</dt><dd>−<x-money :amount="$order->discount_amount" /></dd></div>
                    @endif
                    <div class="flex justify-between"><dt class="text-gray-500">Phí vận chuyển</dt><dd><x-money :amount="$order->shipping_fee" /></dd></div>
                    <div class="flex justify-between border-t border-gray-100 pt-2 font-semibold"><dt>Tổng thanh toán</dt><dd><x-money :amount="$order->grand_total" /></dd></div>
                </dl>
            </x-admin.panel>

            <x-admin.panel title="Lịch sử trạng thái" icon="calendar">
                <ol class="space-y-4">
                    @foreach (array_reverse($order->status_history ?? []) as $change)
                        <li class="flex gap-3 text-sm">
                            <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand" aria-hidden="true"></span>
                            <div class="min-w-0">
                                <p class="text-gray-900">
                                    @if ($change['from'] ?? null)
                                        {{ \App\Models\Order::STATUS_LABELS[$change['from']] ?? $change['from'] }} →
                                    @endif
                                    <span class="font-semibold">{{ \App\Models\Order::STATUS_LABELS[$change['to']] ?? $change['to'] }}</span>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ \Illuminate\Support\Carbon::parse($change['at'])->timezone(config('app.timezone'))->format('H:i d/m/Y') }}
                                    · {{ $actorNames[$change['actor_id'] ?? 0] ?? 'Hệ thống' }}
                                </p>
                                @if ($change['note'] ?? null)
                                    <p class="mt-1 text-gray-600">{{ $change['note'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-admin.panel>
        </div>

        <div class="space-y-6">
            <x-admin.panel title="Trạng thái" icon="orders">
                <x-admin.order-status :status="$order->status" />

                @if ($forwardSteps->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        @foreach ($forwardSteps as $next)
                            <form method="POST" action="{{ route('admin.orders.status', $order) }}"
                                  x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: {{ Js::from('Chuyển đơn sang "'.\App\Models\Order::STATUS_LABELS[$next].'"?') }} })">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $next }}">
                                <x-admin.action type="submit" icon="check" class="w-full">{{ \App\Models\Order::TRANSITION_LABELS[$next] }}</x-admin.action>
                            </form>
                        @endforeach
                    </div>
                @elseif (! $canCancel)
                    <p class="mt-3 text-xs text-gray-500">Đơn đã ở trạng thái cuối, không còn bước xử lý nào.</p>
                @endif

                @if ($canCancel)
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mt-5 border-t border-gray-100 pt-4"
                          x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Hủy đơn này? Hàng sẽ được hoàn lại kho.' })">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="cancelled">
                        <label for="cancel-note" class="text-xs font-medium text-gray-600">Lý do hủy</label>
                        <textarea id="cancel-note" name="note" rows="2" maxlength="500" required class="field mt-1"
                                  placeholder="Ví dụ: khách yêu cầu hủy qua điện thoại">{{ old('note') }}</textarea>
                        <x-input-error :messages="$errors->get('note')" />
                        <x-admin.action type="submit" variant="danger" icon="close" class="mt-2 w-full">Hủy đơn</x-admin.action>
                    </form>
                @endif
            </x-admin.panel>

            <x-admin.panel title="Khách hàng" icon="user">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs text-gray-500">Người nhận</dt><dd class="text-gray-900">{{ $order->customer_name }} · {{ $order->customer_phone }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Email</dt><dd class="break-all text-gray-900">{{ $order->customer_email }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Địa chỉ giao hàng</dt><dd class="text-gray-900">{{ $order->shipping_address }}, {{ $order->ward_name }}, {{ $order->district_name }}, {{ $order->province_name }}</dd></div>
                    @if ($order->customer_note)
                        <div><dt class="text-xs text-gray-500">Ghi chú của khách</dt><dd class="whitespace-pre-line text-gray-900">{{ $order->customer_note }}</dd></div>
                    @endif
                    @if ($order->user)
                        <div><dt class="text-xs text-gray-500">Tài khoản đặt hàng</dt><dd class="text-gray-900">{{ $order->user->name }} ({{ $order->user->email }})</dd></div>
                    @endif
                </dl>
            </x-admin.panel>

            <x-admin.panel title="Thanh toán" icon="revenue">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs text-gray-500">Phương thức</dt><dd class="text-gray-900">{{ $order->paymentMethodLabel() }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Trạng thái</dt><dd class="text-gray-900">{{ $order->paymentStatusLabel() }}</dd></div>
                    @if ($order->transaction_code)
                        <div><dt class="text-xs text-gray-500">Mã giao dịch</dt><dd class="break-all text-gray-900">{{ $order->transaction_code }}</dd></div>
                    @endif
                    @if ($order->payment_reviewed_at)
                        <div><dt class="text-xs text-gray-500">Xác nhận lúc</dt><dd class="text-gray-900">{{ $order->payment_reviewed_at->format('H:i d/m/Y') }} · {{ $order->paymentReviewer?->name ?? 'Tự động (SePay)' }}</dd></div>
                    @endif
                    @if ($order->payment_method === 'cod' && $order->payment_status === 'unpaid')
                        <p class="text-xs text-gray-500">Đơn COD tự chuyển sang "Đã thanh toán" khi đánh dấu giao thành công.</p>
                    @endif
                    @if ($order->payment_method === 'bank_transfer' && $order->payment_status !== 'paid' && $order->status !== 'cancelled')
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">Chưa nhận được tiền chuyển khoản — nên chờ thanh toán trước khi chuẩn bị hàng.</p>
                    @endif
                    @if ($order->status === 'cancelled' && $order->payment_status === 'paid')
                        <p class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">Đơn đã hủy nhưng khách đã thanh toán — cần hoàn tiền cho khách.</p>
                    @endif
                </dl>
            </x-admin.panel>
        </div>
    </div>

    <div class="mt-8">
        <a href="{{ route('admin.orders.index') }}" class="text-sm text-gray-600 hover:underline">&larr; Danh sách đơn hàng</a>
    </div>
@endsection
