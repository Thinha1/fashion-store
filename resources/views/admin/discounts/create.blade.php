@extends('layouts.admin')

@section('title', 'Thêm giảm giá biến thể')

@section('content')
    @include('admin.partials.page-header', ['title' => 'Thêm giảm giá biến thể'])
    @include('admin.discounts._form', ['route' => route('admin.discounts.store')])
@endsection
