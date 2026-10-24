@extends('layouts.admin')

@section('title', 'Thêm vai trò')

@section('content')
    @include('admin.partials.page-header', ['title' => 'Thêm vai trò'])
    @include('admin.roles._form')
@endsection
