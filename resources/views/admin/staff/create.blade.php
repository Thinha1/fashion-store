@extends('layouts.admin')

@section('title', 'Thêm nhân viên')

@section('content')
    @include('admin.partials.page-header', ['title' => 'Thêm nhân viên'])
    @include('admin.staff._form')
@endsection
