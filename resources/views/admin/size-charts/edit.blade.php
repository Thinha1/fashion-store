@extends('layouts.admin')

@section('title', 'Sửa bảng size')

@section('content')
    @include('admin.partials.page-header', ['title' => 'Sửa bảng size: '.$chart->name])
    @include('admin.size-charts._form', ['route' => route('admin.size-charts.update', $chart), 'method' => 'PUT'])
@endsection
