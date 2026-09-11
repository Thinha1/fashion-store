@extends('layouts.admin')

@section('title', $title ?? 'Thêm danh mục')

@section('content')
    @include('admin.partials.page-header', ['title' => $title ?? 'Thêm danh mục'])
    @include('admin.categories._form', ['route' => route('admin.categories.store')])
@endsection
