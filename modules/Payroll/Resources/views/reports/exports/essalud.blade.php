<table>
    <thead>
    <tr>
        <th colspan="5" style="font-weight: bold; text-align: center;">Reporte ESSALUD</th>
    </tr>
    <tr>
        <th colspan="5" style="text-align: center;">Periodo: {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}</th>
    </tr>
    <tr>
        <th style="font-weight: bold;">Periodo</th>
        <th style="font-weight: bold;">Empleado</th>
        <th style="font-weight: bold;">DNI</th>
        <th style="font-weight: bold;">Sueldo Base</th>
        <th style="font-weight: bold;">Aporte ESSALUD (9%)</th>
    </tr>
    </thead>
    <tbody>
    @forelse($payrolls as $payroll)
        @foreach($payroll->detalles as $detalle)
            @if($detalle->essalud > 0)
            <tr>
                <td>{{ $payroll->periodo }}</td>
                <td>{{ optional($detalle->empleado)->user->name ?? '' }} {{ optional($detalle->empleado)->user->paternal_lastname ?? '' }}</td>
                <td>{{ optional($detalle->empleado)->user->number ?? '' }}</td>
                <td>{{ $detalle->sueldo_basico }}</td>
                <td>{{ $detalle->essalud }}</td>
            </tr>
            @endif
        @endforeach
    @empty
        <tr>
            <td colspan="5">No hay datos disponibles.</td>
        </tr>
    @endforelse
    </tbody>
</table>
