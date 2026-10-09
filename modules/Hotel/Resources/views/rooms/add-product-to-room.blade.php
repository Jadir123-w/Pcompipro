@extends('tenant.layouts.app')

@section('content')
    <tenant-hotel-rent-add-product 
        :products='@json($products)'
        :rent='@json($rent)'
        :configuration='@json($configuration)'
        :establishment='@json($establishment)'
        :all-series='{{ $series }}'
    ></tenant-hotel-rent-add-product>
@endsection
