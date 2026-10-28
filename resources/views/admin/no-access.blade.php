@extends('layouts.admin')

@section('title', 'Chưa có quyền')

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Xin chào, '.auth()->user()->name,
        'subtitle' => 'Vai trò "'.(auth()->user()->role?->name ?? '—').'" của bạn chưa được cấp quyền mở trang quản trị nào. Hãy liên hệ quản trị viên để được phân quyền.',
    ])
@endsection
