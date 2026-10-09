@php
use Illuminate\Support\Facades\Storage;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="googlebot" content="noindex">
    <meta name="robots" content="noindex">

    {{--    <title>{{ config('app.name', 'Facturación Electrónica') }}</title>--}}
    <title>{{ $vc_company->title_web }}</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800|Shadows+Into+Light" rel="stylesheet" type="text/css">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('porto-light/vendor/bootstrap/css/bootstrap.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/vendor/animate/animate.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/vendor/font-awesome/css/fontawesome-all.min.css') }}" />
    <link rel="stylesheet" href="{{ asset('porto-light/css/theme.css') }}" />
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/7.26.29/sweetalert2.min.css" />
    
    {{-- Incluir CSS del tema seleccionado --}}
    @if(isset($selectedSkin) && $selectedSkin)
        @if(Storage::disk('public')->exists('skins/' . $selectedSkin->filename))
            <link rel="stylesheet" href="{{ asset('storage/skins/' . $selectedSkin->filename) }}" />
        @endif
    @else
        {{-- CSS por defecto si no hay tema seleccionado --}}
        @if(Storage::disk('public')->exists('skins/default.css'))
            <link rel="stylesheet" href="{{ asset('storage/skins/default.css') }}" />
        @endif
    @endif
    
    @if (file_exists(public_path('theme/custom_styles.css')))
        <link rel="stylesheet" href="{{ asset('theme/custom_styles.css') }}" />
    @endif

    {{-- Aplicar colores de tema dinámicamente --}}
    @if(isset($themeColors) && $themeColors)
        <!-- Debug: Tema aplicado: {{ $selectedTheme ?? 'none' }} -->
        <style>
            :root {
                @foreach($themeColors as $property => $value)
                    {{ $property }}: {!! $value !!};
                @endforeach
            }
        </style>
    @endif

    {{-- @if (file_exists(public_path('theme/background/bkg_store.jpeg')))
        <style>
            .app{
                background: url('{{ asset('theme/background/bkg_store.jpeg') }}') center center / cover;
            }
        </style>
    @else
        <style>
            .app{
                background: url('{{ asset('porto-light/background/bkg_store.jpeg') }}') center center / cover;
            }
        </style>
    @endif --}}

    {{-- Google tag (gtag.js) --}}
    @if(config('services.google.analytics_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google.analytics_id') }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', '{{ config('services.google.analytics_id') }}');
    </script>
    @endif

</head>

<body>

    <div class="app">
        @yield('content')
    </div>

    @stack('scripts')
</body>

</html>
