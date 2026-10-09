@extends('tenant.layouts.app')

@section('content')
    <tenant-payroll-detail :id="{{ $planilla->id }}"></tenant-payroll-detail>
@endsection
