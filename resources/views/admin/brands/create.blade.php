@extends('layouts.admin')

@section('title', 'Thêm thương hiệu')

@section('content')
    @include('admin.partials.page-header', ['title' => 'Thêm thương hiệu'])
    @include('admin.brands._form', ['route' => route('admin.brands.store')])
@endsection
