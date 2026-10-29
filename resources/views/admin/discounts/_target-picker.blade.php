{{-- Picks brands, products and variants (see resources/js/discount-targets.js).
     $options: from DiscountController::targetOptions. $always: true when the picks
     are the whole form (new variant discounts); false when the parent's appliesTo
     switch decides whether they count (order vouchers). $bulk: say how many
     variants the picks will get a discount. --}}
@php($inputClass = 'block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-gray-500 focus:outline-none focus:ring-1 focus:ring-gray-500')
<div class="rounded-lg border border-gray-200" x-data="discountTargets({{ Js::from($options) }}, {{ Js::from($always) }})">
    <div class="flex flex-wrap gap-2 border-b border-gray-200 p-3" role="tablist" aria-label="Chọn theo">
        <template x-for="item in tabs" :key="item.key">
            <button type="button" role="tab" class="btn" :class="tab === item.key ? 'btn-primary' : 'btn-secondary'"
                    :aria-selected="tab === item.key" x-on:click="setTab(item.key)">
                <span x-text="item.label"></span><span x-show="count(item.key) > 0" x-text="' (' + count(item.key) + ')'"></span>
            </button>
        </template>
    </div>

    <div class="space-y-3 p-3">
        <div class="target-picker-bar flex flex-wrap items-center gap-2">
            <label for="target-search" class="sr-only">Tìm trong danh sách đang chọn</label>
            <input type="search" id="target-search" x-model.debounce.150ms="search" placeholder="Tìm theo tên, không cần gõ dấu" class="{{ $inputClass }} sm:max-w-xs">
            <button type="button" class="btn btn-secondary" x-on:click="selectShown()" x-bind:disabled="!matches.length" x-text="'Chọn tất cả đang hiện (' + matches.length + ')'"></button>
            <button type="button" class="btn btn-secondary" x-on:click="clearTab()" x-bind:disabled="!count(tab)">Bỏ chọn</button>
            <label class="ml-auto flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" x-model="onlySelected" class="rounded border-gray-300 text-gray-900 focus:ring-gray-500">
                Chỉ hiện mục đã chọn
            </label>
        </div>

        <ul class="max-h-72 divide-y divide-gray-100 overflow-y-auto rounded-md border border-gray-200" aria-label="Danh sách để chọn">
            <template x-for="item in shown" :key="tab + item.id">
                <li>
                    <label class="flex cursor-pointer items-start gap-2 px-3 py-2 text-sm hover:bg-gray-50">
                        <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-gray-900 focus:ring-gray-500"
                               :checked="isSelected(item.id)" x-on:change="toggle(item.id)">
                        <span><span class="font-medium text-gray-900" x-text="item.name"></span>
                            <span class="ml-1 text-xs text-gray-500" x-show="item.detail" x-text="item.detail"></span></span>
                    </label>
                </li>
            </template>
            <li class="px-3 py-4 text-sm text-gray-500" x-show="!shown.length">Không có mục nào khớp.</li>
        </ul>
        <p class="text-xs text-gray-500" x-show="hidden > 0">Còn <span x-text="hidden"></span> mục nữa. Gõ tên để lọc, hoặc bấm "Chọn tất cả đang hiện" để chọn cả danh sách khớp.</p>
    </div>

    {{-- Only what is ticked is posted; an order voucher that applies to the whole order posts nothing. --}}
    <template x-if="always || appliesTo === 'selected'">
        <div>
            <template x-for="pick in picks" :key="pick.type + pick.id">
                <input type="hidden" :name="'targets[' + pick.type + '][]'" :value="pick.id">
            </template>
        </div>
    </template>

    <p class="border-t border-gray-200 px-3 py-2 text-sm text-gray-700" aria-live="polite">
        @if ($bulk)
            <span x-show="total === 0">Chọn thương hiệu, sản phẩm hoặc biến thể để giảm giá.</span>
            <span x-show="total > 0 && !overLimit">Sẽ tạo giảm giá cho <strong x-text="covered"></strong> biến thể (mỗi biến thể một giảm giá riêng).</span>
            <span x-show="overLimit" class="text-red-700">Đã chọn <strong x-text="covered"></strong> biến thể, nhiều hơn giới hạn <span x-text="maxVariants"></span> một lần. Hãy chọn ít hơn.</span>
        @else
            <span x-show="total === 0">Chưa chọn mục nào.</span>
            <span x-show="total > 0">Voucher chỉ tính trên hàng thuộc <strong x-text="total"></strong> mục đã chọn.</span>
        @endif
    </p>
</div>
