@extends('tenant.layouts.app')

@section('content')

    <cash-index :type-user="{{json_encode(Auth::user()->type)}}"
                :user-id="{{ json_encode(Auth::user()->id) }}"
        :configuration="{{\App\Models\Tenant\Configuration::AvailableReportSeller()}}"></cash-index>

@endsection
