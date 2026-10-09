<table>
    <thead>
    <tr>
        <th colspan="8" style="font-weight: bold; text-align: center;">Reporte AFP</th>
    </tr>
    <tr>
        <th colspan="8" style="text-align: center;">Periodo: {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}</th>
    </tr>
    <tr>
        <th style="font-weight: bold;">Periodo</th>
        <th style="font-weight: bold;">Empleado</th>
        <th style="font-weight: bold;">DNI</th>
        <th style="font-weight: bold;">Régimen Pensionario</th>
        <th style="font-weight: bold;">Fondo</th>
        <th style="font-weight: bold;">Seguro</th>
        <th style="font-weight: bold;">Comisión</th>
        <th style="font-weight: bold;">Total Aporte</th>
    </tr>
    </thead>
    <tbody>
    @forelse($payrolls as $payroll)
        @foreach($payroll->detalles as $detalle)
            @if($detalle->aporte_afp_onp > 0 || $detalle->seguro_afp > 0 || $detalle->comision_afp > 0)
            <tr>
                <td>{{ $payroll->periodo }}</td>
                <td>{{ optional($detalle->empleado)->user->name ?? '' }} {{ optional($detalle->empleado)->user->paternal_lastname ?? '' }}</td>
                <td>{{ optional($detalle->empleado)->user->number ?? '' }}</td>
                <td>{{ $detalle->regimen_pensionario ?? '-' }}</td>
                <td>{{ $detalle->aporte_afp_onp }}</td>
                <td>{{ $detalle->seguro_afp }}</td>
                <td>{{ $detalle->comision_afp }}</td>
                <td>{{ $detalle->aporte_afp_onp + $detalle->seguro_afp + $detalle->comision_afp }}</td>
            </tr>
            @endif
        @endforeach
    @empty
        <tr>
            <td colspan="8">No hay datos disponibles.</td>
        </tr>
    @endforelse
    </tbody>
</table>
