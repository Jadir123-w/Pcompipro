<table>
    <thead>
    <tr>
        <th colspan="6" style="font-weight: bold; text-align: center;">Reporte Detallado de Planillas</th>
    </tr>
    <tr>
        <th colspan="6" style="text-align: center;">Periodo: {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}</th>
    </tr>
    <tr>
        <th style="font-weight: bold;">Periodo</th>
        <th style="font-weight: bold;">Empleado</th>
        <th style="font-weight: bold;">DNI</th>
        <th style="font-weight: bold;">Ingresos</th>
        <th style="font-weight: bold;">Descuentos</th>
        <th style="font-weight: bold;">Neto</th>
    </tr>
    </thead>
    <tbody>
    @forelse($payrolls as $payroll)
        @foreach($payroll->detalles as $detalle)
        <tr>
            <td>{{ $payroll->periodo }}</td>
            <td>{{ optional($detalle->empleado)->user->name ?? '' }} {{ optional($detalle->empleado)->user->paternal_lastname ?? '' }}</td>
            <td>{{ optional($detalle->empleado)->user->number ?? '' }}</td>
            <td>{{ $detalle->total_ingresos }}</td>
            <td>{{ $detalle->total_descuentos }}</td>
            <td>{{ $detalle->neto_pagar }}</td>
        </tr>
        @endforeach
    @empty
        <tr>
            <td colspan="6">No hay datos disponibles para el periodo seleccionado.</td>
        </tr>
    @endforelse
    </tbody>
</table>
