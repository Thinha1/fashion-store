@extends('layouts.admin')

@section('title', 'Sửa thương hiệu: '.$brand->name)

@section('content')
    @include('admin.partials.page-header', ['title' => "Sửa thương hiệu: {$brand->name}", 'subtitle' => 'ID '.$brand->id])
    @include('admin.brands._form', [
        'route' => route('admin.brands.update', $brand),
        'method' => 'PUT',
    ])
@endsection
