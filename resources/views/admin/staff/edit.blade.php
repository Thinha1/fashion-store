@extends('layouts.admin')

@section('title', 'Nhân viên: '.$staff->name)

@section('content')
    @include('admin.partials.page-header', ['title' => 'Nhân viên: '.$staff->name, 'subtitle' => $staff->email])
    @include('admin.staff._form')
@endsection
