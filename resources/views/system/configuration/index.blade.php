@extends('system.layouts.app')

@section('content')

    <div class="row">
        <!--<div class="col-lg-6 col-md-12 pt-2 pt-md-0">
            <system-companies-form></system-companies-form>
        </div> -->
        <div class="col-lg-6 col-md-12">
            <system-certificate-index></system-certificate-index>
            <system-login-settings :configuration='@json($configuration)'></system-login-settings>

            <system-login-other-configuration></system-login-other-configuration>            
            <system-email-configuration :configuration='@json($configuration)'></system-email-configuration>
        </div>
        <div class="col-lg-6 col-md-12">
            <system-configuration-culqi></system-configuration-culqi>
            <system-configuration-token></system-configuration-token>
            <system-configuration-apk-url></system-configuration-apk-url>
            <system-support-configuration></system-support-configuration>            
        </div>
    </div>

@endsection
