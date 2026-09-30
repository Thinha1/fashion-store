@extends('layouts.admin')

@section('title', 'Sửa nhà cung cấp: '.$supplier->name)

@section('content')
    @include('admin.partials.page-header', ['title' => "Sửa nhà cung cấp: {$supplier->name}", 'subtitle' => "Mã nhà cung cấp: {$supplier->code}"])
    @include('admin.suppliers._form', [
        'route' => route('admin.suppliers.update', $supplier),
        'method' => 'PUT',
    ])
@endsection
