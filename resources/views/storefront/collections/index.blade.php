@extends('layouts.app')
@section('title', 'Bộ sưu tập — ' . config('app.name', 'Fashion Store'))
@section('content')
    <div class="container page">
        <header class="catalog-head">
            <div class="catalog-title">
                <h1 id="collections-title">Bộ sưu tập</h1>
                <p class="caption">{{ $brands->count() }} thương hiệu, mỗi thương hiệu một kệ riêng.</p>
            </div>
        </header>

        <section aria-labelledby="collections-title">
            @if ($brands->isEmpty())
                <div class="catalog-empty">
                    <h2>Chưa có bộ sưu tập nào.</h2>
                    <p>Các thương hiệu sẽ xuất hiện ở đây khi có sản phẩm đang bán.</p>
                </div>
            @else
                <ul class="brand-grid" role="list">
                    @foreach ($brands as $brand)
                        <li>
                            <a href="{{ route('collections.show', $brand) }}" class="brand-card">
                                <div class="brand-card-tiles">
                                    @foreach ($brand->products->take(4) as $product)
                                        @php($image = $product->images->first())
                                        <div data-slot="brand-{{ $brand->id }}-{{ $loop->index }}" data-kind="product" data-alt="{{ $product->name }}">
                                            @if ($image)
                                                <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('filesystems.image_disk'))->url($image->path) }}"
                                                     alt="" width="400" height="500" loading="lazy">
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                <span class="brand-card-body">
                                    <strong>{{ $brand->name }}</strong>
                                    <span class="caption">{{ $brand->products_count }} sản phẩm</span>
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
@endsection
