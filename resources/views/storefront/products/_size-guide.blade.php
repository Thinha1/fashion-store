{{-- "Bảng size" dialog — included inside productDetail's x-data, so pickSize/hasVariant/selectedColor are in scope. --}}
@php
    $range = fn ($min, $max) => match (true) {
        $min !== null && $max !== null && $min !== $max => $min.'–'.$max,
        $min !== null || $max !== null => (string) ($min ?? $max),
        default => '—',
    };
    $canSuggest = $sizeChart->canSuggest();
@endphp
<div x-data="sizeGuide({ rows: {{ Js::from($sizeChart->rows) }} })" class="contents">
    <button type="button" class="text-xs text-brand hover:underline" x-on:click="open()">
        <x-icon name="table" class="size-3.5" /> Bảng size
    </button>

    <dialog x-ref="sizeGuide" class="size-guide-dialog" aria-labelledby="size-guide-title"
            x-on:click="if ($event.target === $refs.sizeGuide) close()" x-on:cancel.prevent="close()">
        <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
            <div>
                <h2 id="size-guide-title" class="text-base font-semibold">Bảng size</h2>
                <p class="mt-0.5 text-xs text-gray-500">{{ $sizeChart->name }}</p>
            </div>
            <button type="button" class="flex size-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100" x-on:click="close()" aria-label="Đóng">
                <x-icon name="close" class="size-4" />
            </button>
        </div>

        <div class="space-y-5 px-5 py-5">
            @if ($canSuggest)
                <section class="rounded-xl bg-brand-soft/40 p-4" aria-labelledby="size-suggest-title">
                    <h3 id="size-suggest-title" class="text-sm font-semibold">Gợi ý size cho bạn</h3>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <label class="text-xs text-gray-600">Chiều cao (cm)
                            <input type="number" inputmode="numeric" min="50" max="250" x-model="height" placeholder="VD: 170"
                                   class="mt-1 block min-h-11 w-full rounded-xl border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-brand focus:outline-none focus:ring-3 focus:ring-brand/15">
                        </label>
                        <label class="text-xs text-gray-600">Cân nặng (kg)
                            <input type="number" inputmode="numeric" min="10" max="250" x-model="weight" placeholder="VD: 65"
                                   class="mt-1 block min-h-11 w-full rounded-xl border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-brand focus:outline-none focus:ring-3 focus:ring-brand/15">
                        </label>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm" aria-live="polite">
                        <template x-if="suggestion">
                            <p>
                                Size phù hợp: <strong class="text-brand" x-text="suggestion.size"></strong>
                                <span class="text-xs text-gray-500" x-show="!suggestion.exact">(gần nhất trong bảng, nên thử trước khi mua)</span>
                            </p>
                        </template>
                        <template x-if="suggestion && hasVariant(suggestion.size, selectedColor)">
                            <button type="button" class="rounded-full bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand/90"
                                    x-on:click="pickSize(suggestion.size); close()">
                                Chọn size <span x-text="suggestion.size"></span>
                            </button>
                        </template>
                        <template x-if="suggestion && !hasVariant(suggestion.size, selectedColor)">
                            <p class="text-xs text-amber-700">Màu đang chọn chưa có size này.</p>
                        </template>
                        <p x-show="!suggestion" class="text-xs text-gray-500">Nhập chiều cao và/hoặc cân nặng để xem size phù hợp.</p>
                    </div>
                </section>
            @endif

            <div class="overflow-x-auto rounded-xl border border-gray-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold text-gray-600">
                        <tr>
                            <th scope="col" class="px-3 py-2.5">Size</th>
                            @if ($canSuggest)
                                <th scope="col" class="px-3 py-2.5 whitespace-nowrap">Chiều cao (cm)</th>
                                <th scope="col" class="px-3 py-2.5 whitespace-nowrap">Cân nặng (kg)</th>
                            @endif
                            @foreach ($sizeChart->columns as $column)
                                <th scope="col" class="px-3 py-2.5 whitespace-nowrap">{{ $column }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($sizeChart->rows as $row)
                            <tr :class="suggestion?.size === {{ Js::from($row['size']) }} ? 'bg-brand-soft/60 font-semibold' : ''">
                                <th scope="row" class="px-3 py-2.5 text-left font-semibold">{{ $row['size'] }}</th>
                                @if ($canSuggest)
                                    <td class="px-3 py-2.5 text-gray-700">{{ $range($row['height_min'] ?? null, $row['height_max'] ?? null) }}</td>
                                    <td class="px-3 py-2.5 text-gray-700">{{ $range($row['weight_min'] ?? null, $row['weight_max'] ?? null) }}</td>
                                @endif
                                @foreach ($sizeChart->columns as $index => $column)
                                    <td class="px-3 py-2.5 text-gray-700">{{ ($row['values'][$index] ?? '') !== '' ? $row['values'][$index] : '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($sizeChart->note)
                <p class="text-xs text-gray-500">{{ $sizeChart->note }}</p>
            @endif
        </div>
    </dialog>
</div>
