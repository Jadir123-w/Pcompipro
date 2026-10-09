{{--
    Términos y condiciones al pie. Se incluye para que el diseño quede completo
    y no falte ningún archivo que el sistema pueda solicitar.
--}}
<body>
@if(!is_null($document) && $document->terms_condition)
    <table style="width: 100%;">
        <tr>
            <td style="font-size: 7.5pt;">{!! $document->terms_condition !!}</td>
        </tr>
    </table>
@endif
</body>
