<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boleta de Pago - {{ $payroll->employee->dni }}</title>
    <style>
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .container { width: 100% !important; }
        }
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .company-info { margin-bottom: 20px; }
        .payroll-info { margin: 20px 0; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f8f9fa; }
        .text-right { text-align: right; }
        .total-row { font-weight: bold; background-color: #e9ecef; }
        .signature-area { margin-top: 50px; }
        .footer { margin-top: 30px; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <!-- Encabezado -->
    <div class="header">
        <h2>BOLETA DE PAGO</h2>
        <h3>{{ $company->razon_social ?? 'EMPRESA' }}</h3>
        <p>RUC: {{ $company->ruc ?? '00000000000' }}</p>
    </div>

    <!-- Información del Empleado -->
    <div class="row">
        <div class="col-md-6">
            <table class="table">
                <tr><th colspan="2">DATOS DEL EMPLEADO</th></tr>
                <tr><td>DNI:</td><td>{{ $payroll->employee->dni }}</td></tr>
                <tr><td>Nombre:</td><td>{{ $payroll->employee->nombres }} {{ $payroll->employee->apellidos }}</td></tr>
                <tr><td>Cargo:</td><td>{{ $payroll->employee->tipo_contrato }}</td></tr>
                <tr><td>F. Ingreso:</td><td>{{ $payroll->employee->fecha_ingreso->format('d/m/Y') }}</td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table">
                <tr><th colspan="2">INFORMACIÓN DE PAGO</th></tr>
                <tr><td>Periodo:</td><td>{{ $payroll->periodo->format('F Y') }}</td></tr>
                <tr><td>Días Trabajados:</td><td>{{ $payroll->dias_trabajados }} días</td></tr>
                <tr><td>Fecha Pago:</td><td>{{ now()->format('d/m/Y') }}</td></tr>
            </table>
        </div>
    </div>

    <!-- Detalle de Ingresos y Descuentos -->
    <div class="row">
        <div class="col-md-6">
            <table class="table">
                <tr><th colspan="2">INGRESOS</th></tr>
                <tr><td>Sueldo Básico</td><td class="text-right">S/ {{ number_format($payroll->employee->sueldo_basico, 2) }}</td></tr>
                <tr><td>Horas Extras</td><td class="text-right">S/ {{ number_format($payroll->horas_extras * (($payroll->employee->sueldo_basico / 30) / 8) * 1.25, 2) }}</td></tr>
                <tr><td>Bonificaciones</td><td class="text-right">S/ {{ number_format($payroll->bonificaciones, 2) }}</td></tr>
                <tr><td>Otros Ingresos</td><td class="text-right">S/ {{ number_format($payroll->otros_ingresos, 2) }}</td></tr>
                <tr class="total-row"><td>TOTAL INGRESOS</td><td class="text-right">S/ {{ number_format($payroll->total_ingresos, 2) }}</td></tr>
            </table>
        </div>
        <div class="col-md-6">
            <table class="table">
                <tr><th colspan="2">DESCUENTOS</th></tr>
                <tr><td>ESSALUD (9%)</td><td class="text-right">S/ {{ number_format($payroll->essalud, 2) }}</td></tr>
                <tr><td>AFP Fondo (10%)</td><td class="text-right">S/ {{ number_format($payroll->afp_fondo, 2) }}</td></tr>
                <tr><td>AFP Seguro (1.368%)</td><td class="text-right">S/ {{ number_format($payroll->afp_seguro, 2) }}</td></tr>
                <tr><td>5ta Categoría</td><td class="text-right">S/ {{ number_format($payroll->quinta_categoria, 2) }}</td></tr>
                <tr><td>Adelantos</td><td class="text-right">S/ {{ number_format($payroll->adelantos, 2) }}</td></tr>
                <tr class="total-row"><td>TOTAL DESCUENTOS</td><td class="text-right">S/ {{ number_format($payroll->total_descuentos, 2) }}</td></tr>
            </table>
        </div>
    </div>

    <!-- Total a Pagar -->
    <div class="row">
        <div class="col-md-12">
            <table class="table">
                <tr class="total-row" style="background-color: #d4edda;">
                    <td><h4>NETO A PAGAR</h4></td>
                    <td class="text-right"><h4>S/ {{ number_format($payroll->neto_pagable, 2) }}</h4></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Firmas -->
    <div class="signature-area">
        <div class="row">
            <div class="col-md-6 text-center">
                <p>_________________________</p>
                <p><strong>Empleado</strong></p>
                <p>{{ $payroll->employee->nombres }} {{ $payroll->employee->apellidos }}</p>
            </div>
            <div class="col-md-6 text-center">
                <p>_________________________</p>
                <p><strong>Representante Legal</strong></p>
                <p>{{ $company->representante_legal ?? 'Nombre del Representante' }}</p>
            </div>
        </div>
    </div>

    <!-- Pie de página -->
    <div class="footer">
        <p>Documento generado el: {{ now()->format('d/m/Y H:i') }} | Sistema FacturadorPro4</p>
        <p>Este documento es una copia para el empleado</p>
    </div>
</div>

<script>
    window.onload = function() {
        window.print();
        setTimeout(function() {
            window.close();
        }, 1000);
    }
</script>
</body>
</html>
