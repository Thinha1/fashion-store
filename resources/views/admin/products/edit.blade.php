@extends('layouts.admin')

@section('title', 'Sửa sản phẩm: '.$product->name)

@section('content')
    @include('admin.partials.page-header', ['title' => "Sửa sản phẩm: {$product->name}"])
    @include('admin.products._form', [
        'route' => route('admin.products.update', $product),
        'method' => 'PUT',
        'variants' => $variants,
        'images' => $images,
    ])
@endsection
