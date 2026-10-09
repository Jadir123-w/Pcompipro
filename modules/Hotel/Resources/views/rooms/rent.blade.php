@extends('tenant.layouts.app')

@section('content')
    <tenant-hotel-rent 
        :room='@json($room)' 
        :affectation-igv-types='@json($affectation_igv_types)'
        :all-series='{{ $series }}'
    ></tenant-hotel-rent>
@endsection
