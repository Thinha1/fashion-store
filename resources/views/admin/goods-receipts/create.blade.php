@extends('layouts.admin')

@section('title', 'Thêm phiếu nhập')

@section('content')
    @include('admin.partials.page-header', ['title' => 'Thêm phiếu nhập'])
    @include('admin.goods-receipts._form', ['route' => route('admin.goods-receipts.store')])
@endsection
