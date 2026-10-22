@extends('layouts.admin')

@section('title', 'Thêm bảng size')

@section('content')
    @include('admin.partials.page-header', ['title' => 'Thêm bảng size'])
    @include('admin.size-charts._form')
@endsection
