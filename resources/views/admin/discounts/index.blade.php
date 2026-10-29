@extends('layouts.admin')

@section('title', 'Giảm giá & voucher')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Giảm giá & voucher',
        'actionUrl' => route('admin.discounts.create', $filters['type'] ? ['loai' => $filters['type']] : []),
        'actionLabel' => 'Thêm giảm giá',
    ])

    <x-excel-tools resource="discounts" />

    @php
        $filtering = $filters['type'] || $filters['status'] || $filters['q'] !== '';
        // Sort and page size survive a filter change; plain values only.
        $kept = array_filter(request()->only(['sort', 'direction', 'per_page']), fn ($value) => is_string($value) && $value !== '');
        $lifecycleTone = ['running' => 'success', 'upcoming' => 'warning', 'expired' => 'neutral', 'disabled' => 'neutral'];
    @endphp
    <form method="GET" action="{{ route('admin.discounts.index') }}" class="mb-4 flex flex-wrap items-center gap-2" aria-label="Lọc giảm giá">
        @foreach ($kept as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <label for="discount-type" class="sr-only">Loại</label>
        <select id="discount-type" name="loai" class="field min-h-10 sm:w-auto" onchange="this.form.requestSubmit()">
            <option value="">Mọi loại</option>
            @foreach (\App\Models\Discount::SCOPE_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label for="discount-status" class="sr-only">Trạng thái</label>
        <select id="discount-status" name="trang-thai" class="field min-h-10 sm:w-auto" onchange="this.form.requestSubmit()">
            <option value="">Mọi trạng thái</option>
            @foreach (\App\Http\Controllers\Admin\DiscountController::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label for="discount-q" class="sr-only">Tìm theo tên sản phẩm, tên hoặc mã voucher</label>
        <input id="discount-q" type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="Tên sản phẩm, tên hoặc mã voucher" class="field min-h-10 sm:w-72">
        <x-admin.action type="submit" variant="secondary" icon="search">Lọc</x-admin.action>
        @if ($filtering)
            <span class="text-sm text-gray-500" role="status">{{ $discounts->total() }} kết quả</span>
            <a href="{{ route('admin.discounts.index', $kept) }}" class="px-2 text-sm font-medium text-gray-500 hover:text-gray-900 hover:underline">Xoá lọc</a>
        @endif
    </form>

    <x-admin-table :paginator="$discounts" :sorting="$sorting"
        :sortable="['Áp dụng cho' => 'variant', 'Loại' => 'scope', 'Giá trị' => 'discount_value', 'Đã dùng' => 'used_count', 'Bắt đầu' => 'starts_at', 'Kết thúc' => 'ends_at', 'Trạng thái' => 'is_active']"
        :header="['Áp dụng cho', 'Loại', 'Giá trị', 'Đã dùng', 'Bắt đầu', 'Kết thúc', 'Trạng thái', 'Thao tác']">
        @forelse ($discounts as $discount)
            <tr>
                <td class="px-4 py-3">
                    @if ($discount->isVoucher())
                        <span class="font-medium text-gray-900">{{ $discount->voucherLabel() }}</span>
                        <span class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                            @if ($discount->code)<span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-gray-700">{{ $discount->code }}</span>@endif
                            @if ($discount->is_auto_apply)<span class="rounded bg-accent-soft px-1.5 py-0.5 text-gray-800">Tự động áp dụng</span>@endif
                            @if ($discount->min_order_amount !== null && (float) $discount->min_order_amount > 0)<span>Đơn từ <x-money :amount="$discount->min_order_amount" /></span>@endif
                        </span>
                    @elseif ($discount->productVariant)
                        <span class="font-medium text-gray-900">{{ $discount->productVariant->product?->name }}</span>
                        <span class="ml-2 text-xs text-gray-500">{{ $discount->productVariant->size }} / {{ $discount->productVariant->color }}</span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600">{{ \App\Models\Discount::SCOPE_LABELS[$discount->scope] ?? $discount->scope }}</td>
                <td class="px-4 py-3 text-gray-600 tabular-nums">
                    @if ($discount->discount_type === 'percent')
                        {{ rtrim(rtrim(number_format((float) $discount->discount_value, 2, ',', '.'), '0'), ',') }}%
                    @else
                        <x-money :amount="$discount->discount_value" />
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-600 tabular-nums">
                    @if ($discount->isVoucher()){{ $discount->used_count }}{{ $discount->usage_limit ? ' / '.$discount->usage_limit : '' }}@else — @endif
                </td>
                <td class="px-4 py-3 text-gray-600">{{ $discount->starts_at?->format('d/m/Y') }}</td>
                <td class="px-4 py-3 text-gray-600">{{ $discount->ends_at?->format('d/m/Y') }}</td>
                <td class="px-4 py-3">
                    @php($lifecycle = $discount->lifecycle())
                    <span class="admin-status admin-status-{{ $lifecycleTone[$lifecycle] }}"><span aria-hidden="true"></span>{{ \App\Http\Controllers\Admin\DiscountController::STATUS_LABELS[$lifecycle] }}</span>
                </td>
                <td class="px-4 py-3 text-right"><div class="admin-row-actions">
                    <a href="{{ route('admin.discounts.edit', $discount) }}" class="admin-row-action"><x-icon name="edit" class="size-3.5" /> Sửa</a>
                    <form method="POST" action="{{ route('admin.discounts.destroy', $discount) }}" class="inline"
                          x-on:submit.prevent="$dispatch('admin-confirm', { form: $el, message: 'Xóa giảm giá này?' })">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="admin-row-action"><x-icon name="delete" class="size-3.5" /> Xóa</button>
                    </form>
                </div></td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="px-4 py-8 text-center text-gray-500">{{ $filtering ? 'Không có giảm giá nào khớp bộ lọc.' : 'Chưa có giảm giá nào.' }}</td>
            </tr>
        @endforelse
    </x-admin-table>
@endsection
