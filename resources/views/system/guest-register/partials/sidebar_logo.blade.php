
<article class="auth__image" style="background-image: url({{ $use_login_global ? $login->image : $default_background_image_login }});background-size: 100%">

    @if ($use_login_global && ($login->image ?? false))
        @if ($login->position_logo != 'none')
            <img class="auth__logo {{ $login->position_logo }}" src="{{ $login->image }}" alt="Logo" />
        @endif
    @else
        <img class="auth__logo {{ $login->position_logo }}" src="{{ asset('logo/tulogo.png') }}" alt="Logo" />
    @endif

</article>

