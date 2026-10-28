@extends('layouts.admin')

@section('title', 'Vai trò: '.$role->name)

@section('content')
    @include('admin.partials.page-header', ['title' => 'Vai trò: '.$role->name])
    @include('admin.roles._form')
@endsection
