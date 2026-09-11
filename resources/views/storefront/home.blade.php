@extends('layouts.app')

@section('title', 'Trang chủ')

@section('content')
    <div class="text-center">
        <h1 class="text-2xl font-semibold">Chào mừng đến với {{ config('app.name', 'Fashion Store') }}</h1>
        <p class="mt-2 text-gray-600">Catalog sản phẩm sẽ có ở giai đoạn tiếp theo.</p>
    </div>
@endsection
