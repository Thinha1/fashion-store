@extends('layouts.app')
@section('title', 'Fashion Store — Phong cách của riêng bạn')
@section('content')
    <section class="grid overflow-hidden rounded-3xl bg-[#e6efed] lg:grid-cols-2" aria-labelledby="hero-title">
        <div class="flex flex-col items-start justify-center px-7 py-12 sm:px-12 sm:py-16">
            <p class="eyebrow">Cảm hứng cho tủ đồ của bạn</p>
            <h1 id="hero-title" class="display-title mt-5 text-5xl leading-[1.12] text-gray-900 sm:text-6xl lg:text-7xl">Mặc đẹp theo<br>cách <span class="italic text-brand">của bạn.</span></h1>
            <p class="mt-6 max-w-sm text-base leading-7 text-gray-600">Từ một ngày đi làm đến buổi hẹn cuối tuần. Tìm cảm hứng cho những bộ đồ khiến bạn thấy thoải mái và tự tin.</p>
            <a href="#phong-cach" class="btn btn-primary mt-8">Khám phá phong cách <x-icon class="size-4" /></a>
            <p class="mt-5 text-xs text-gray-500">Những gợi ý nhỏ cho phong cách mỗi ngày.</p>
        </div>
        <div class="relative flex min-h-80 items-center justify-center overflow-hidden bg-[#ccded9] px-8 py-6 sm:min-h-96">
            <div class="absolute top-10 right-8 size-28 rounded-full border border-brand/15 sm:size-40" aria-hidden="true"></div>
            <div class="absolute bottom-0 left-8 h-[85%] w-[75%] rounded-t-full bg-[#b4cdc5]" aria-hidden="true"></div>
            <x-outfit-art class="relative w-full max-w-[390px] -rotate-6" />
            <div class="absolute right-5 bottom-7 rounded-2xl border border-white/60 bg-white/90 px-5 py-4 shadow-sm sm:right-8"><p class="text-[10px] font-semibold tracking-[0.18em] uppercase text-gray-500">Gợi ý hôm nay</p><p class="mt-1 text-sm font-semibold text-brand">Nhẹ nhàng. Dễ phối. Đúng chất.</p></div>
            <span class="absolute top-7 left-6 rounded-full bg-white/65 px-4 py-2 text-xs font-medium text-brand">Everyday essentials</span>
        </div>
    </section>
    @if ($featured->isNotEmpty())
        <section class="py-14 sm:py-16" aria-labelledby="featured-title">
            <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="eyebrow">Được yêu thích nhất</p>
                    <h2 id="featured-title" class="display-title mt-3 text-3xl sm:text-4xl">Sản phẩm nổi bật</h2>
                </div>
                <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-brand hover:underline">Xem tất cả sản phẩm <x-icon name="arrow" class="size-3.5" /></a>
            </div>
            <div class="grid grid-cols-2 gap-x-5 gap-y-9 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($featured as $product)
                    @php($image = $product->images->first())
                    <a href="{{ route('products.show', $product) }}" class="pg-card group">
                        <div class="pg-media">
                            <div class="pg-tags"><span class="product-card-badge">Nổi bật</span></div>
                            @if ($image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path) }}"
                                     alt="{{ $image->alt_text ?? $product->name }}" loading="lazy">
                            @else
                                <x-icon name="shirt" class="size-12" />
                            @endif
                        </div>
                        <div class="pg-body">
                            <div class="min-w-0">
                                <p class="pg-brand">{{ $product->brand?->name ?? 'Fashion Store' }}</p>
                                <h3>{{ $product->name }}</h3>
                            </div>
                            <span class="pg-price">{{ number_format((float) $product->base_price, 0) }} ₫</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section id="phong-cach" class="scroll-mt-8 py-14 sm:py-16" aria-labelledby="style-title">
        <div class="mb-7 flex flex-wrap items-end justify-between gap-4"><div><p class="eyebrow">Chọn theo nhịp sống</p><h2 id="style-title" class="display-title mt-3 text-3xl sm:text-4xl">Hôm nay, bạn muốn mặc gì?</h2></div><p class="max-w-xs text-sm leading-6 text-gray-500">Ba gợi ý phối đồ, để mỗi ngày đều có một chút mới mẻ.</p></div>
        <div class="grid gap-5 sm:grid-cols-3">
            @foreach ([['daily', 'bg-[#e6efed]', 'Thoải mái mỗi ngày', 'Sơ mi sáng màu, quần tông xanh và một chiếc túi nhỏ. Đơn giản để dễ dàng bắt đầu ngày mới.'], ['office', 'bg-[#e5ebee]', 'Chỉn chu đi làm', 'Áo khoác dáng gọn cùng quần tối màu. Một bộ đồ vừa lịch sự, vừa giữ được nét riêng.'], ['weekend', 'bg-[#f5e8e3]', 'Cuối tuần thảnh thơi', 'Áo thun tông ấm, quần denim và phụ kiện nhẹ nhàng. Sẵn sàng cho buổi cà phê hay dạo phố.']] as [$look, $background, $title, $description])
                <article class="style-card"><div class="{{ $background }} flex h-64 justify-center sm:h-72"><x-outfit-art :look="$look" class="h-full w-full p-4" /></div><div class="p-6"><h3>{{ $title }}</h3><p>{{ $description }}</p></div></article>
            @endforeach
        </div>
        <div class="mt-5 flex items-start gap-3 rounded-xl border border-gray-200 bg-white px-5 py-4 text-sm text-gray-500"><x-icon name="info" class="mt-0.5 shrink-0" /><p>Đây là các gợi ý phong cách bằng hình minh họa. Danh sách sản phẩm và tính năng mua sắm đang được hoàn thiện.</p></div>
    </section>
@endsection
