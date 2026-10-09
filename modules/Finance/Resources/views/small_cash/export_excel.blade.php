<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
</head>
<body>
<table>
    <tr></tr> <!-- Row 1 -->
    <!-- TITLE -->
    <tr>
        <td></td> <!-- A -->
        <td></td> <!-- B -->
        <td colspan="9" style="text-align: center; font-weight: bold; font-size: 16px;">
            {{ $smallCash->titulo_rendicion }}
        </td>
    </tr>
    <tr>
        <td></td> <!-- A -->
        <td></td> <!-- B -->
        <td colspan="9" style="text-align: center; font-size: 11px;">
            {{ $smallCash->identificacion_rendicion }}
        </td>
    </tr>
    <tr></tr> <!-- Row 3 -->
    
    <!-- Row 4 -->
    <tr>
        <td></td> <!-- A -->
        <td></td> <!-- B -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">CONCEPTO</td> <!-- C -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">MONTO</td> <!-- D -->
        <td></td> <!-- E -->
        <td></td> <!-- F -->
        <td></td> <!-- G -->
        <td></td> <!-- H -->
        <td></td> <!-- I -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">Ingreso total caja</td> <!-- J -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: right;">S/ {{ number_format($smallCash->income, 2) }}</td> <!-- K -->
    </tr>
    <!-- Row 5 -->
    <tr>
        <td></td> <!-- A -->
        <td></td> <!-- B -->
        <td style="border: 1px solid #000000;">MONTO INICIAL</td> <!-- C -->
        <td style="border: 1px solid #000000; text-align: right;">S/ {{ number_format($smallCash->beginning_balance, 2) }}</td> <!-- D -->
        <td></td> <!-- E -->
        <td></td> <!-- F -->
        <td></td> <!-- G -->
        <td></td> <!-- H -->
        <td></td> <!-- I -->
        <td style="border: 1px solid #000000; text-align: center;">Fecha</td> <!-- J -->
        <td style="border: 1px solid #000000; text-align: center;">Monto</td> <!-- K -->
    </tr>
    @php
        $incomes = $transactions->where('type', 'income')->values();
        $incomeCount = $incomes->count();
        $meses = ['01' => 'ENERO', '02' => 'FEBRERO', '03' => 'MARZO', '04' => 'ABRIL', '05' => 'MAYO', '06' => 'JUNIO', '07' => 'JULIO', '08' => 'AGOSTO', '09' => 'SEPTIEMBRE', '10' => 'OCTUBRE', '11' => 'NOVIEMBRE', '12' => 'DICIEMBRE'];
        $mesApertura = \Carbon\Carbon::parse($smallCash->date_opening)->format('m');
        $nombreMes = $meses[$mesApertura] ?? '';
    @endphp
    <!-- Row 6 -->
    <tr>
        <td></td> <!-- A -->
        <td></td> <!-- B -->
        <td style="border: 1px solid #000000;">INGRESO DE CAJA</td>
        <td style="border: 1px solid #000000; text-align: right;">S/ {{ number_format($smallCash->income, 2) }}</td>
        <td></td><td></td><td></td><td></td><td></td>
        @if($incomeCount > 0)
            <td style="border: 1px solid #000000; text-align: center;">{{ $incomes[0]->date->format('d/m/y') }}</td>
            <td style="border: 1px solid #000000; text-align: right;">S/ {{ number_format($incomes[0]->amount, 2) }}</td>
        @else
            <td style="border: 1px solid #000000;"></td><td style="border: 1px solid #000000;"></td>
        @endif
    </tr>
    <!-- Row 7 -->
    <tr>
        <td></td>
        <td></td>
        <td style="border: 1px solid #000000; font-weight: bold;">TOTAL DE CAJA</td>
        <td style="border: 1px solid #000000; font-weight: bold; text-align: right;">S/ {{ number_format($smallCash->beginning_balance + $smallCash->income, 2) }}</td>
        <td></td><td></td><td></td><td></td><td></td>
        @if($incomeCount > 1)
            <td style="border: 1px solid #000000; text-align: center;">{{ $incomes[1]->date->format('d/m/y') }}</td>
            <td style="border: 1px solid #000000; text-align: right;">S/ {{ number_format($incomes[1]->amount, 2) }}</td>
        @else
            <td style="border: 1px solid #000000;"></td><td style="border: 1px solid #000000;"></td>
        @endif
    </tr>
    <!-- Row 8 -->
    <tr>
        <td></td>
        <td></td>
        <td style="border: 1px solid #000000;">GASTOS DE CAJA</td>
        <td style="border: 1px solid #000000; text-align: right;">S/ {{ number_format($smallCash->expense, 2) }}</td>
        <td></td><td></td><td></td><td></td><td></td>
        @if($incomeCount > 2)
            <td style="border: 1px solid #000000; text-align: center;">{{ $incomes[2]->date->format('d/m/y') }}</td>
            <td style="border: 1px solid #000000; text-align: right;">S/ {{ number_format($incomes[2]->amount, 2) }}</td>
        @else
            <td style="border: 1px solid #000000;"></td><td style="border: 1px solid #000000;"></td>
        @endif
    </tr>
    <!-- Row 9 -->
    <tr>
        <td></td>
        <td></td>
        <td style="border: 1px solid #000000; font-weight: bold;">SALDO DE CAJA</td>
        <td style="background-color: #FFFF00; border: 1px solid #000000; font-weight: bold; text-align: right;">S/ {{ number_format($smallCash->final_balance, 2) }}</td>
        <td></td><td></td><td></td><td></td><td></td>
        @if($incomeCount > 3)
            <td style="border: 1px solid #000000; text-align: center;">{{ $incomes[3]->date->format('d/m/y') }}</td>
            <td style="border: 1px solid #000000; text-align: right;">S/ {{ number_format($incomes[3]->amount, 2) }}</td>
        @else
            <td style="border: 1px solid #000000;"></td><td style="border: 1px solid #000000;"></td>
        @endif
    </tr>

    <!-- Remaining Incomes if > 4 -->
    @if($incomeCount > 4)
        @for($i = 4; $i < $incomeCount; $i++)
        <tr>
            <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
            <td style="border: 1px solid #000000; text-align: center;">{{ $incomes[$i]->date->format('d/m/y') }}</td>
            <td style="border: 1px solid #000000; text-align: right;">S/ {{ number_format($incomes[$i]->amount, 2) }}</td>
        </tr>
        @endfor
    @endif

    <tr></tr> <!-- Space before tables -->
    <tr></tr> <!-- Space before tables -->
    <tr></tr> <!-- Space before tables -->

    <!-- Header Block -->
    <tr>
        <td></td> <!-- A -->
        <td style="background-color: #FFFF00; font-weight: bold; text-align: center; border: 1px solid #000000;">PERIODO</td> <!-- B -->
        <td colspan="9" style="background-color: #FFFF00; font-weight: bold; text-align: left; border: 1px solid #000000;">{{ $nombreMes }}</td>
    </tr>
    <tr>
        <td></td> <!-- A -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">FECHA</td> <!-- B -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">PROVEEDOR / CLIENTE</td> <!-- C -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">MÉTODO PAGO</td> <!-- D -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">N° DOCUMENTO</td> <!-- E -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">DESCRIPCIÓN</td> <!-- F -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">CATEGORÍA / TIPO-GASTO</td> <!-- G -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">RUTA</td> <!-- H -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">PROYECTO</td> <!-- I -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">RESPONSABLE</td> <!-- J -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">MONTO</td> <!-- K -->
    </tr>

    <!-- EXPENSES -->
    @foreach($transactions->where('type', 'expense') as $expense)
    <tr>
        <td></td> <!-- A -->
        <td style="border: 1px solid #000000; text-align: center;">{{ $expense->date->format('d-m-y') }}</td> <!-- B -->
        <td style="border: 1px solid #000000; text-align: center;">{{ $expense->supplier_customer }}</td> <!-- C -->
        <td style="border: 1px solid #000000; text-align: center;">{{ $expense->paymentMethodType ? $expense->paymentMethodType->description : '' }}</td> <!-- D -->
        <td style="border: 1px solid #000000; text-align: center;">{{ $expense->document_number }}</td> <!-- E -->
        <td style="border: 1px solid #000000;">{{ $expense->description }}</td> <!-- F -->
        <td style="border: 1px solid #000000; text-align: center;">{{ $expense->category }}</td> <!-- G -->
        <td style="border: 1px solid #000000;">{{ $expense->ruta }}</td> <!-- H: RUTA -->
        <td style="border: 1px solid #000000;">{{ $expense->proyecto }}</td> <!-- I: PROYECTO -->
        <td style="border: 1px solid #000000; text-align: center;">{{ $expense->responsable }}</td> <!-- J: RESPONSABLE (quien ejecutó el gasto) -->
        <td style="border: 1px solid #000000; text-align: right;">S/ {{ number_format($expense->amount, 2) }}</td> <!-- K -->
    </tr>
    @endforeach

    <!-- TOTAL -->
    <tr>
        <td></td> <!-- A -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: center;">Totales</td> <!-- B -->
        <td style="background-color: #FFFF00; border: 1px solid #000000;"></td> <!-- C -->
        <td style="background-color: #FFFF00; border: 1px solid #000000;"></td> <!-- D -->
        <td style="background-color: #FFFF00; border: 1px solid #000000;"></td> <!-- E -->
        <td style="background-color: #FFFF00; border: 1px solid #000000;"></td> <!-- F -->
        <td style="background-color: #FFFF00; border: 1px solid #000000;"></td> <!-- G -->
        <td style="background-color: #FFFF00; border: 1px solid #000000;"></td> <!-- H -->
        <td style="background-color: #FFFF00; border: 1px solid #000000;"></td> <!-- I -->
        <td style="background-color: #FFFF00; border: 1px solid #000000;"></td> <!-- J -->
        <td style="background-color: #FFFF00; font-weight: bold; border: 1px solid #000000; text-align: right;">S/ {{ number_format($smallCash->expense, 2) }}</td> <!-- K -->
    </tr>
</table>
</body>
</html>
