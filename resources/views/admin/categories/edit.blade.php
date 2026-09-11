@extends('layouts.admin')

@section('title', 'Sửa danh mục: '.$category->name)

@section('content')
    @include('admin.partials.page-header', ['title' => "Sửa danh mục: {$category->name}"])
    @include('admin.categories._form', [
        'route' => route('admin.categories.update', $category),
        'method' => 'PUT',
    ])
@endsection
