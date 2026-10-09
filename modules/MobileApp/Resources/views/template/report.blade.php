<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0">
    <title>Envio de Reporte General de ventas</title>
    <style>
        body {
            color: #000;
        }
        ul {
            list-style: none;
        }
    </style>
</head>
<body>
<ul>
    <li>RUC/DNI: {{ $company->number}}</li>
    <li>Razon social: {{ $company->name }}</li>
    <li>Teléfono: {{ $establishment->telephone }}</li>
    <li>Fecha : {{ Carbon\Carbon::now()->format('d/m/Y') }}</li>
</ul>
</body>
</html>