@props(['header' => []])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm']) }}>
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        @if ($header)
            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-600">
                <tr>
                    @foreach ($header as $label)
                        <th class="px-4 py-3">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-gray-100 bg-white">
            {{ $slot }}
        </tbody>
    </table>
</div>
