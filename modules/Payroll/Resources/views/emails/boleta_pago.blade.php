<!DOCTYPE html>
<html>
<head>
    <title>Boleta de Pago</title>
</head>
<body>
    <p>Estimado(a) {{ $detalle->nombres_completos }},</p>
    <p>Adjunto encontrará su boleta de pago correspondiente al periodo {{ $detalle->planilla->periodo }}.</p>
    <br>
    <p>Atentamente,</p>
    <p>El Equipo de Recursos Humanos</p>
</body>
</html>
