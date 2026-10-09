<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización de prueba — Compipro</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:Arial, Helvetica, sans-serif; color:#303133;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08);">

                    <!-- Banda de aviso de PRUEBA -->
                    <tr>
                        <td style="background-color:#e6a23c; color:#ffffff; padding:10px 24px; font-size:13px; font-weight:bold; letter-spacing:0.3px;">
                            VISTA PREVIA DE PRUEBA — Este correo es una validación interna, no el envío definitivo al cliente.
                        </td>
                    </tr>

                    <!-- Cabecera -->
                    <tr>
                        <td style="padding:24px 24px 8px 24px;">
                            <h1 style="margin:0; font-size:20px; color:#1f2d3d;">Cotización {{ $numeroCotizacion }}</h1>
                        </td>
                    </tr>

                    <!-- Cuerpo -->
                    <tr>
                        <td style="padding:8px 24px 0 24px; font-size:14px; line-height:1.6; color:#5a5e66;">
                            <p style="margin:0 0 12px 0;">Hola,</p>
                            <p style="margin:0 0 12px 0;">
                                Adjuntamos una <strong>vista previa de prueba</strong> de la cotización
                                @if(!empty($empresaNombre))
                                    para <strong>{{ $empresaNombre }}</strong>@if(!empty($destinatarioNombre)) (atención {{ $destinatarioNombre }})@endif.
                                @elseif(!empty($destinatarioNombre))
                                    dirigida a <strong>{{ $destinatarioNombre }}</strong>.
                                @else
                                    .
                                @endif
                            </p>
                            <p style="margin:0 0 12px 0;">
                                Revisa el PDF adjunto para verificar cómo se verá la propuesta antes de emitirla al cliente.
                                Si algo necesita ajustarse, puedes editarlo en la Cotización Final y volver a enviar esta prueba.
                            </p>
                            <p style="margin:0 0 4px 0; color:#909399; font-size:13px;">
                                Este mensaje se generó automáticamente desde el cotizador de Compipro.
                            </p>
                        </td>
                    </tr>

                    <!-- Adjunto destacado -->
                    <tr>
                        <td style="padding:16px 24px 24px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" style="background-color:#f0f9eb; border-left:3px solid #67c23a; border-radius:0 6px 6px 0; width:100%;">
                                <tr>
                                    <td style="padding:12px 16px; font-size:13px; color:#529b2e;">
                                        📎 Cotización {{ $numeroCotizacion }} adjunta en formato PDF
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Pie -->
                    <tr>
                        <td style="padding:16px 24px; background-color:#fafafa; border-top:1px solid #ebeef5; font-size:12px; color:#909399;">
                            COMPIPRO — Comunicación &amp; Marketing<br>
                            Este es un correo de prueba interno. No reenviar al cliente.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
