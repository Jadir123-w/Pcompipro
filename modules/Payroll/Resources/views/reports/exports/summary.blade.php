<table>
    <thead>
    <tr>
        <th colspan="2" style="font-weight: bold; text-align: center;">Resumen de Planillas</th>
    </tr>
    <tr>
        <th colspan="2" style="text-align: center;">Periodo: {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td style="font-weight: bold;">Total Planillas</td>
        <td>{{ $summary->total_planillas }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Total Ingresos</td>
        <td>{{ $summary->total_ingresos }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Total Descuentos</td>
        <td>{{ $summary->total_descuentos }}</td>
    </tr>
    <tr>
        <td style="font-weight: bold;">Total Neto</td>
        <td>{{ $summary->total_neto }}</td>
    </tr>
    </tbody>
</table>
