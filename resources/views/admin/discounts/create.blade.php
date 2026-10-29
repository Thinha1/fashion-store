@extends('layouts.admin')

@section('title', 'Thêm giảm giá')

@section('content')
    @include('admin.partials.page-header', ['title' => 'Thêm giảm giá'])
    @include('admin.discounts._form', ['route' => route('admin.discounts.store')])
@endsection
