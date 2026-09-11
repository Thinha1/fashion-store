@extends('layouts.admin')

@section('title', 'Sửa phiếu nhập: '.$receipt->receipt_number)

@section('content')
    @include('admin.partials.page-header', ['title' => "Sửa phiếu nhập: {$receipt->receipt_number}"])
    @include('admin.goods-receipts._form', [
        'route' => route('admin.goods-receipts.update', $receipt),
        'method' => 'PUT',
    ])
@endsection
