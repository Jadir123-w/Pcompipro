@extends('tenant.layouts.app')

@section('content')

    <tenant-pending-account-commissions-index :type-user="{{json_encode(Auth::user()->type)}}"></tenant-pending-account-commissions-index>

@endsection