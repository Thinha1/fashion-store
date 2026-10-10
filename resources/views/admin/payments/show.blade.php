@extends('layouts.admin')

@section('title', 'Chứng từ đơn '.$order->order_number)

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Chứng từ đơn '.$order->order_number,
        'subtitle' => 'Đối chiếu biên lai với sao kê tài khoản trước khi xác nhận.',
    ])

    <div class="admin-detail-grid">
        <x-admin.panel title="Biên lai khách gửi" icon="image">
            @if ($order->payment_proof_path)
                <a href="{{ route('admin.payments.receipt', $order) }}" target="_blank" rel="noopener" class="block">
                    <img src="{{ route('admin.payments.receipt', $order) }}" alt="Biên lai chuyển khoản đơn {{ $order->order_number }}"
                         class="mx-auto max-h-[36rem] rounded-lg border border-gray-200 object-contain">
                </a>
                <p class="mt-2 text-center text-xs text-gray-500">Bấm vào ảnh để xem kích thước đầy đủ.</p>
            @else
                <p class="text-sm text-gray-500">Đơn này không có ảnh biên lai.</p>
            @endif
        </x-admin.panel>

        <div class="space-y-6">
            <x-admin.panel title="Đối chiếu" icon="revenue">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs text-gray-500">Số tiền phải trả</dt><dd class="text-lg font-semibold text-gray-900"><x-money :amount="$order->grand_total" /></dd></div>
                    <div><dt class="text-xs text-gray-500">Nội dung chuyển khoản đúng</dt><dd class="font-medium text-gray-900">{{ $order->order_number }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Mã giao dịch khách nhập</dt><dd class="break-all text-gray-900">{{ $order->transaction_code ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Gửi lúc</dt><dd class="text-gray-900">{{ $order->payment_proof_submitted_at?->format('H:i d/m/Y') ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Trạng thái thanh toán</dt><dd class="text-gray-900">{{ $order->paymentStatusLabel() }}</dd></div>
                    <div><dt class="text-xs text-gray-500">Khách hàng</dt><dd class="text-gray-900">{{ $order->customer_name }} · {{ $order->customer_phone }}</dd></div>
                </dl>
            </x-admin.panel>

            @if ($order->payment_status === 'pending_review')
                <x-admin.panel title="Quyết định" icon="check">
                    <form method="POST" action="{{ route('admin.payments.review', $order) }}"
                          x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Xác nhận đã nhận đủ tiền cho đơn này?' })">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" value="approve">
                        <x-admin.action type="submit" icon="check" class="w-full">Xác nhận đã nhận tiền</x-admin.action>
                    </form>

                    <form method="POST" action="{{ route('admin.payments.review', $order) }}" class="mt-5 border-t border-gray-100 pt-4">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="decision" value="reject">
                        <label for="reject-reason" class="text-xs font-medium text-gray-600">Lý do từ chối (khách sẽ thấy)</label>
                        <textarea id="reject-reason" name="reason" rows="2" maxlength="500" required class="field mt-1"
                                  placeholder="Ví dụ: chưa thấy giao dịch trong sao kê, vui lòng kiểm tra lại số tiền">{{ old('reason') }}</textarea>
                        <x-input-error :messages="$errors->get('reason')" />
                        <x-admin.action type="submit" variant="danger" icon="close" class="mt-2 w-full">Từ chối chứng từ</x-admin.action>
                    </form>
                </x-admin.panel>
            @else
                <p class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600">Đơn không còn chờ duyệt — có thể webhook SePay đã khớp tiền hoặc chứng từ đã được xử lý.</p>
            @endif
        </div>
    </div>

    <div class="mt-8">
        <a href="{{ route('admin.payments.index') }}" class="text-sm text-gray-600 hover:underline">&larr; Danh sách chờ duyệt</a>
    </div>
@endsection
