@extends('system.guest-register.layouts.maindisabled')

@section('content')

    <section class="auth auth__form-{{ $login->position_form }}">
        
        @include('system.guest-register.partials.sidebar_logo')

        <article class="auth__form">
            <form>
                <div class="d-flex justify-content-center">
                    <div class="row">
                        @include('system.guest-register.partials.form_logo')
                    </div>
                </div>
                <system-guest-register-disabled
                    :base-url="{{json_encode($base_url)}}"
                >
                </system-guest-register-disabled>
            </form>
        </article>
    </section>
@endsection


