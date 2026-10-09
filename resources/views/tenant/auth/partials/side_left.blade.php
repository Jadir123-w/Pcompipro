<article class="auth__image" style="padding: 5%; display: flex; justify-content: center; align-items: center; overflow: hidden; background-color: {{ $loginBgColor ?? '#ffffff' }};">
    <img 
        src="{{ $login->image }}" 
        alt="Background Image" 
        style="max-width: 100%; max-height: 100%; object-fit: contain;" 
    />
    @if ($useLoginGlobal)
        @if ($login->logo ?? false)
            @if ($login->position_logo != 'none')
                <img class="auth__logo {{ $login->position_logo }}" src="{{ $login->logo }}" alt="Logo" />
            @endif
        @endif
    @else
        @if($company->logo)
            <img class="auth__logo {{ $login->position_logo }}" src="{{ asset('storage/uploads/logos/' . $company->logo) }}" alt="Logo" />
        @else
            <img class="auth__logo {{ $login->position_logo }}" src="{{ asset('logo/tulogo.png') }}" alt="Logo" />
        @endif
    @endif
</article>
