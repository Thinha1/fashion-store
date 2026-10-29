@extends('layouts.admin')

@section('title', 'Sửa giảm giá')

@section('content')
    @include('admin.partials.page-header', ['title' => 'Sửa giảm giá'])
    @include('admin.discounts._form', [
        'route' => route('admin.discounts.update', $discount),
        'method' => 'PUT',
    ])
@endsection
