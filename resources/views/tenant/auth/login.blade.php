@extends('tenant.layouts.auth')

@section('content')
<section class="auth auth__form-{{ $login->position_form }}">
    @include('tenant.auth.partials.side_left')
    <article class="auth__form">
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="d-flex justify-content-center">
                <div class="row">
                    @include('tenant.auth.partials.form_logo')
                </div>
            </div>
            <div class="text-center title-login-container">
                <h1 class="auth__title"><span class="text-xs">Bienvenido a</span><br><b>{{ $company->trade_name }}</b></h1>
                <p class="auth__subtitle">Ingresa a tu cuenta</p>
                <p class="auth__subtitle__black d-none">
                    Ingrese su correo electrónico y contraseña a continuación para iniciar sesión en su cuenta.
                </p>
            </div>
            <div class="form-group form-group-email">
                <label for="email" class="label-email">Correo electrónico</label>
                <input type="email" name="email" id="email" placeholder="correo@ejemplo.com" class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}" value="{{ old('email') }}" autofocus>
                @if ($errors->has('email'))
                <div class="invalid-feedback">{{ $errors->first('email') }}</div>
                @endif
            </div>
            <div class="form-group">
                <label for="password" class="label-password">
                    Contraseña
                    <a class="forgot-password d-none" href="{{ url('password/reset') }}" tabindex="5">¿Olvidaste tu contraseña?</a>
                </label>
                <div class="position-relative">
                    <input type="password" name="password" id="password" placeholder="********" class="form-control hide-password {{ $errors->has('password') ? 'is-invalid' : '' }}">
                    <button type="button" class="btn btn-eye" id="btnEye" tabindex="4">
                        <i class="fa fa-eye"></i>
                    </button>                    
                </div>
                @if ($errors->has('password'))
                <div class="invalid-feedback">{{ $errors->first('password') }}</div>
                @endif
            </div>

            <div class="auth__forgot-password d-flex justify-content-between align-items-center">
                <div class="checkbox-custom checkbox-default d-none">
                    <input name="remember" id="RememberMe" type="checkbox" {{ old('remember') ? 'checked' : '' }}>
                    <label class="m-0" for="RememberMe">Recordarme</label>
                </div>
                <a class="forgot-password-modern d-none" href="{{ url('password/reset') }}" tabindex="5">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="btn btn-signin btn-block">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-login-2 mr-1 icon-login d-none"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M9 8v-2a2 2 0 0 1 2 -2h7a2 2 0 0 1 2 2v12a2 2 0 0 1 -2 2h-7a2 2 0 0 1 -2 -2v-2"></path><path d="M3 12h13l-3 -3"></path><path d="M13 15l3 -3"></path></svg>
                iniciar sesión
            </button>
            <div class="text-center p-4 password-down">
                <a href="{{ url('password/reset') }}" tabindex="5">¿Has olvidado tu contraseña?</a>
            </div>
            @include('tenant.auth.partials.socials')
        </form>
    </article>
</section>
    {{-- <section class="body-sign">
                                <div class="checkbox-custom checkbox-default">
                                    <input name="remember" id="RememberMe" type="checkbox" {{ old('remember') ? 'checked' : '' }}>
                                    <label for="RememberMe">Recordarme</label>
                                </div>
    </section> --}}
@endsection
@push('scripts')
    <script>
        var inputPassword = document.getElementById('password');
        var btnEye = document.getElementById('btnEye');
        btnEye.addEventListener('click', function () {
            if (inputPassword.classList.contains('hide-password')) {
                inputPassword.type = 'text';
                inputPassword.classList.remove('hide-password');
                btnEye.innerHTML = '<i class="fa fa-eye-slash"></i>'
            } else {
                inputPassword.type = 'password';
                inputPassword.classList.add('hide-password');
                btnEye.innerHTML = '<i class="fa fa-eye"></i>'
            }
        });
    </script>
@endpush
