@extends('tenant.layouts.app')

@section('content')
  <tenant-comercial-empresas-index
    :type-user="{{ $type_user }}"
    :type="{{ $type }}"
    :configuration="{{ $config }}"
  ></tenant-comercial-empresas-index>
@endsection
