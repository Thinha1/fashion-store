@extends('layouts.admin')

@section('title', 'Thêm sản phẩm')

@section('content')
    @include('admin.partials.page-header', ['title' => 'Thêm sản phẩm'])
    @include('admin.products._form', [
        'route' => route('admin.products.store'),
        'variants' => $variants,
        'images' => $images,
    ])
@endsection
