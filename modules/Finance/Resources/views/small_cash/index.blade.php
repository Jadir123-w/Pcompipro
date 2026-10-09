@extends('tenant.layouts.app')

@section('content')

  <tenant-finance-small-cash-index
    type-user="{{ $type_user }}"
    :user-id="{{ auth()->id() }}">
  </tenant-finance-small-cash-index>

@endsection
