@php
    $path_style = app_path('CoreFacturalo'.DIRECTORY_SEPARATOR.'Templates'.DIRECTORY_SEPARATOR.'pdf'.DIRECTORY_SEPARATOR.'style.css');
@endphp
<head>
    <link href="{{ $path_style }}" rel="stylesheet" />
</head>
<body>
<table height="300px" class="full-width">
    <tr>
        <td class="text-center" style="font-size: 10px; padding-bottom:5px">GRACIAS POR SU PREFERENCIA</td>
    </tr>
    <tr>
        <td class="text-center" style="font-size: 13px;">
            <strong class="text-center">TuÉxito!</strong>
        </td>
    </tr>
    <tr>
        <td class="text-center" style=" font-size:11px">
           Comprobante emitido a través de <span class="font-bold" style="font-size:15px; text-decoration:underline; color:blue">www.tuexito.pe</span>
        </td>
    </tr>
    <tr>
        <td class="text-center desc" style="font-size:08px">
            {{-- Condición para omitir la línea de enlace si es Guía de Remisión --}}
            @if(!in_array($document->document_type_id, ['09']))
                Para consultar el comprobante ingresar a {!! url('/buscar') !!}
                <br>
            @endif
        </td>
    </tr>
</table>
</body>
