@extends('layouts.app')
@section('title', 'Bộ sưu tập — ' . config('app.name', 'Fashion Store'))
@section('content')
    <section aria-labelledby="collections-title">
        <div class="mb-9">
            <p class="eyebrow">Mỗi thương hiệu, một câu chuyện riêng</p>
            <h1 id="collections-title" class="display-title mt-3 text-3xl sm:text-4xl">Bộ sưu tập</h1>
        </div>

        @if ($brands->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center">
                <x-icon name="box" class="size-8 text-gray-300" />
                <p class="text-sm text-gray-500">Chưa có bộ sưu tập nào.</p>
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($brands as $brand)
                    <a href="{{ route('collections.show', $brand) }}" class="collection-card group">
                        <div class="collection-card-grid">
                            @forelse ($brand->products->take(4) as $product)
                                @php($image = $product->images->first())
                                <div class="collection-card-tile">
                                    @if ($image)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path) }}" alt="" loading="lazy">
                                    @else
                                        <x-icon name="shirt" class="size-6" />
                                    @endif
                                </div>
                            @empty
                                <div class="collection-card-tile"><x-icon name="shirt" class="size-6" /></div>
                            @endforelse
                        </div>
                        <div class="collection-card-body">
                            <h3>{{ $brand->name }}</h3>
                            <p>{{ $brand->products_count }} sản phẩm</p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
