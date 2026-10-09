<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rendición de Caja Chica</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 9px;
        }
        table {
            border-collapse: collapse;
        }
        .titulo {
            text-align: center;
            font-weight: bold;
            font-size: 15px;
        }
        .identificacion {
            text-align: center;
            font-size: 11px;
            padding-top: 3px;
            padding-bottom: 12px;
        }

        /* Disposición general: la tabla de movimientos ocupa la izquierda y el
           resumen de saldos queda fijo al margen derecho, a la misma altura.
           Así la tabla crece hacia abajo sin desplazar los bloques de arriba.
           Las columnas llevan clase propia: un selector con ">" no sirve porque
           el motor inserta un <tbody> implícito. */
        .hoja { width: 100%; }
        .col-izq, .col-der { vertical-align: top; }
        .col-izq { width: 80%; padding-right: 12px; }
        .col-der { width: 20%; }

        .movimientos { width: 100%; }
        .movimientos th,
        .movimientos td {
            border: 1px solid #000000;
            padding: 3px 4px;
        }
        .movimientos th {
            background-color: #FFFF00;
            font-weight: bold;
            text-align: center;
        }

        .resumen { width: 100%; }
        .resumen td {
            border: 1px solid #000000;
            padding: 3px 5px;
            height: 14px;
        }
        .resumen .cab {
            background-color: #FFFF00;
            font-weight: bold;
            text-align: center;
        }

        .amarillo { background-color: #FFFF00; font-weight: bold; }
        .der  { text-align: right; }
        .cen  { text-align: center; }
        .nowrap { white-space: nowrap; }
        .vacio { color: #000000; }
        @page { margin: 1cm; }
    </style>
</head>
<body>

@php
    $egresos   = $transactions->where('type', 'expense')->values();
    $ingresos  = $transactions->where('type', 'income')->values();
    $totalCaja = $smallCash->beginning_balance + $smallCash->income;

    // El modelo adjunto por el cliente muestra la grilla con renglones libres al
    // final; se completa hasta un mínimo para conservar esa presentación.
    $filasMinimas = 12;
    $filasVacias  = max(0, $filasMinimas - $egresos->count());

    // En el modelo, los datos que no aplican se representan con guiones.
    $guion = '----------';
@endphp

<div class="titulo">{{ $smallCash->titulo_rendicion }}</div>
<div class="identificacion">{{ $smallCash->identificacion_rendicion }}</div>

<table class="hoja">
    <tr>
        <!-- ═══════════ Movimientos ═══════════ -->
        <td class="col-izq">
            <table class="movimientos">
                <thead>
                <tr>
                    <th style="width: 8%;">PERIODO</th>
                    <th colspan="9">{{ $smallCash->periodo }}</th>
                </tr>
                <tr>
                    <th style="width: 8%;">Fecha</th>
                    <th style="width: 22%;">Descripción</th>
                    <th style="width: 8%;">Método<br>Pago</th>
                    <th style="width: 9%;">N°<br>Documento</th>
                    <th style="width: 10%;">Proveedor<br>/ Cliente</th>
                    <th style="width: 9%;">Categoría/<br>Tipo-Gasto</th>
                    <th style="width: 9%;">Ruta</th>
                    <th style="width: 13%;">Proyecto</th>
                    <th style="width: 8%;">Responsable</th>
                    <th style="width: 8%;">Monto</th>
                </tr>
                </thead>
                <tbody>
                @foreach($egresos as $egreso)
                    <tr>
                        <td class="cen nowrap">{{ $egreso->date ? $egreso->date->format('d-m-Y') : $guion }}</td>
                        <td>{{ $egreso->description }}</td>
                        <td class="cen">{{ $egreso->paymentMethodType ? $egreso->paymentMethodType->description : $guion }}</td>
                        <td class="cen">{{ $egreso->document_number ?: $guion }}</td>
                        <td class="cen">{{ $egreso->supplier_customer ?: $guion }}</td>
                        <td class="cen">{{ $egreso->category ?: $guion }}</td>
                        <td>{{ $egreso->ruta ?: $guion }}</td>
                        <td>{{ $egreso->proyecto ?: $guion }}</td>
                        <td class="cen">{{ $egreso->responsable ?: $guion }}</td>
                        <td class="der nowrap">S/ {{ number_format($egreso->amount, 2) }}</td>
                    </tr>
                @endforeach

                @for($i = 0; $i < $filasVacias; $i++)
                    <tr>
                        <td>&nbsp;</td><td></td><td></td><td></td><td></td>
                        <td></td><td></td><td></td><td></td><td></td>
                    </tr>
                @endfor

                <tr>
                    <td class="amarillo cen">Totales</td>
                    <td class="amarillo"></td>
                    <td class="amarillo"></td>
                    <td class="amarillo"></td>
                    <td class="amarillo"></td>
                    <td class="amarillo"></td>
                    <td class="amarillo"></td>
                    <td class="amarillo"></td>
                    <td class="amarillo"></td>
                    <td class="amarillo der nowrap">S/ {{ number_format($smallCash->expense, 2) }}</td>
                </tr>
                </tbody>
            </table>
        </td>

        <!-- ═══════════ Resumen de saldos (margen derecho) ═══════════ -->
        <td class="col-der">
            <table class="resumen">
                <tr>
                    <td class="cab">Concepto</td>
                    <td class="cab">Monto</td>
                </tr>
                <tr>
                    <td>Monto Inicial</td>
                    <td class="der nowrap">S/ {{ number_format($smallCash->beginning_balance, 2) }}</td>
                </tr>
                <tr>
                    <td>Ingreso De Caja</td>
                    <td class="der nowrap">S/ {{ number_format($smallCash->income, 2) }}</td>
                </tr>
                <tr>
                    <td>Total De Caja</td>
                    <td class="der nowrap">S/ {{ number_format($totalCaja, 2) }}</td>
                </tr>
                <tr>
                    <td>Gastos De Caja</td>
                    <td class="der nowrap">S/ {{ number_format($smallCash->expense, 2) }}</td>
                </tr>
                <tr>
                    <td class="amarillo">Saldo De Caja</td>
                    <td class="amarillo der nowrap">S/ {{ number_format($smallCash->final_balance, 2) }}</td>
                </tr>
            </table>

            <table class="resumen" style="margin-top: 14px;">
                <tr>
                    <td class="cab" colspan="2">Ingreso Total Caja</td>
                </tr>
                <tr>
                    <td class="amarillo cen nowrap" colspan="2">S/ {{ number_format($smallCash->income, 2) }}</td>
                </tr>
                <tr>
                    <td class="cab">Fecha</td>
                    <td class="cab">Monto</td>
                </tr>
                @foreach($ingresos as $ingreso)
                    <tr>
                        <td class="cen nowrap">{{ $ingreso->date ? $ingreso->date->format('d/m/Y') : '' }}</td>
                        <td class="der nowrap">S/ {{ number_format($ingreso->amount, 2) }}</td>
                    </tr>
                @endforeach
                @for($i = $ingresos->count(); $i < 6; $i++)
                    <tr>
                        <td>&nbsp;</td>
                        <td></td>
                    </tr>
                @endfor
            </table>
        </td>
    </tr>
</table>

</body>
</html>
