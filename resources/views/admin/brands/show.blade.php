@extends('layouts.admin')

@section('title', $brand->name)

@section('content')
    @include('admin.partials.page-header', [
        'title' => $brand->name,
        'subtitle' => 'ID '.$brand->id.' • slug /'.$brand->slug,
        'actions' => '<a href="'.route('admin.brands.edit', $brand).'" class="text-sm text-gray-700 hover:underline">Sửa</a>',
    ])

    <dl class="grid max-w-2xl grid-cols-1 gap-4 sm:grid-cols-2">
        @if ($brand->logo_path)
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Logo</dt>
                <dd class="mt-1">
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('s3')->url($brand->logo_path) }}" alt="Logo {{ $brand->name }}" class="h-20 w-20 rounded object-cover">
                </dd>
            </div>
        @endif
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Tên</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $brand->name }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Quốc gia</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $brand->country ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Trạng thái</dt>
            <dd class="mt-1 text-sm text-gray-900">
                {{ $brand->is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}
            </dd>
        </div>
        <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Số sản phẩm</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ $brand->products_count }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Mô tả</dt>
            <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $brand->description ?: '—' }}</dd>
        </div>
    </dl>

    <div class="mt-8">
        <a href="{{ route('admin.brands.index') }}" class="text-sm text-gray-600 hover:underline">&larr; Danh sách thương hiệu</a>
    </div>
@endsection
