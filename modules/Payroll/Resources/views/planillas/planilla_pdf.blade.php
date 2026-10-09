<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planilla de Remuneraciones</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; }
        .header { margin-bottom: 20px; }
        .title { font-weight: bold; font-size: 14px; text-align: center; margin-bottom: 5px; }
        .subtitle { text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: right; }
        th { background: #eee; text-align: center; font-weight: bold; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .totals-row td { font-weight: bold; background: #ddd; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">PLANILLA DE REMUNERACIONES - {{ $planilla->periodo }}</div>
        <div class="subtitle">
            Del {{ $planilla->fecha_inicio }} al {{ $planilla->fecha_fin }} <br>
           Estado: {{ $planilla->estado }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">N°</th>
                <th rowspan="2">DNI</th>
                <th rowspan="2">Apellidos y Nombres</th>
                <th rowspan="2">Cargo</th>
                <th rowspan="2">Días</th>
                <th colspan="7">INGRESOS</th>
                <th colspan="5">DESCUENTOS</th>
                <th>APORTE</th>
                <th rowspan="2">NETO</th>
            </tr>
            <tr>
                <th>Básico</th>
                <th>Asig.Fam</th>
                <th>H.Ext</th>
                <th>Bonif.</th>
                <th>Mov.</th>
                <th>Otros</th>
                <th>Total</th>
                
                <th>Reg.</th>
                <th>Aporte</th>
                <th>Com.</th>
                <th>Seg.</th>
                <th>Total</th>

                <th>Essalud</th>
            </tr>
        </thead>
        <tbody>
            @foreach($planilla->detalles as $index => $det)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $det->dni }}</td>
                <td class="text-left">{{ $det->nombres_completos }}</td>
                <td class="text-left">{{ $det->cargo }}</td>
                <td class="text-center">{{ $det->dias_laborados }}</td>
                
                <td>{{ number_format($det->sueldo_basico, 2) }}</td>
                <td>{{ number_format($det->asignacion_familiar, 2) }}</td>
                <td>{{ number_format($det->horas_extras, 2) }}</td>
                <td>{{ number_format($det->bonificaciones, 2) }}</td>
                <td>{{ number_format($det->movilidad, 2) }}</td>
                <td>{{ number_format($det->otros_ingresos, 2) }}</td>
                <td style="background:#f9f9f9"><strong>{{ number_format($det->total_ingresos, 2) }}</strong></td>
                
                <td class="text-center" style="font-size:8px">{{ $det->tipo_regimen }}</td>
                <td>{{ number_format($det->aporte_afp_onp, 2) }}</td>
                <td>{{ number_format($det->comision_afp, 2) }}</td>
                <td>{{ number_format($det->seguro_afp, 2) }}</td>
                <td style="background:#f9f9f9"><strong>{{ number_format($det->total_descuentos, 2) }}</strong></td>

                <td>{{ number_format($det->essalud, 2) }}</td>
                
                <td style="background:#ddd"><strong>{{ number_format($det->neto_pagar, 2) }}</strong></td>
            </tr>
            @endforeach
            
            <tr class="totals-row">
                <td colspan="5" class="text-center">TOTALES GENERALES</td>
                <td>{{ number_format($planilla->detalles->sum('sueldo_basico'), 2) }}</td>
                <td>{{ number_format($planilla->detalles->sum('asignacion_familiar'), 2) }}</td>
                <td>{{ number_format($planilla->detalles->sum('horas_extras'), 2) }}</td>
                <td>{{ number_format($planilla->detalles->sum('bonificaciones'), 2) }}</td>
                <td>{{ number_format($planilla->detalles->sum('movilidad'), 2) }}</td>
                <td>{{ number_format($planilla->detalles->sum('otros_ingresos'), 2) }}</td>
                <td>{{ number_format($planilla->total_ingresos, 2) }}</td>
                
                <td></td>
                <td>{{ number_format($planilla->detalles->sum('aporte_afp_onp'), 2) }}</td>
                <td>{{ number_format($planilla->detalles->sum('comision_afp'), 2) }}</td>
                <td>{{ number_format($planilla->detalles->sum('seguro_afp'), 2) }}</td>
                <td>{{ number_format($planilla->total_descuentos, 2) }}</td>

                <td>{{ number_format($planilla->total_aportaciones, 2) }}</td>
                
                <td>{{ number_format($planilla->total_neto, 2) }}</td>
            </tr>
        </tbody>
    </table>
    
    <div style="margin-top: 20px; font-size: 10px;">
        Generado por: {{ auth()->user()->name }} - Fecha: {{ date('d/m/Y H:i') }}
    </div>

</body>
</html>
