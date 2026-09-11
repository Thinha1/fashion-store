@extends('layouts.admin')

@section('title', 'Thêm nhà cung cấp')

@section('content')
    @include('admin.partials.page-header', ['title' => 'Thêm nhà cung cấp'])
    @include('admin.suppliers._form', ['route' => route('admin.suppliers.store')])
@endsection
