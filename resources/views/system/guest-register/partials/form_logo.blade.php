@if ($use_login_global && ($login->image ?? false))
    @if ($login->show_logo_in_form)
        <img class="auth__logo-form" src="{{ $login->image }}" alt="Logo formulario" />
    @endif
@else
    @if ($login->show_logo_in_form)
        <img class="auth__logo-form" src="{{asset('logo/tulogo.png')}}" alt="Logo formulario" width="250" />
    @endif
@endif
<br>
