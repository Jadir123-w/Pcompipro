
<div class="row mt-4">
    <div class="col-md-12">
        <h3>Reporte AFP</h3>
        <p>Periodo: {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}</p>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                <tr>
                    <th>Periodo</th>
                    <th>Empleado</th>
                    <th>DNI</th>
                    <th>Régimen Pensionario</th>
                    <th>Fondo</th>
                    <th>Seguro</th>
                    <th>Comisión</th>
                    <th>Total Aporte</th>
                </tr>
                </thead>
                <tbody>
                @foreach($payrolls as $payroll)
                    @foreach($payroll->detalles as $detalle)
                        @if($detalle->aporte_afp_onp > 0 || $detalle->seguro_afp > 0 || $detalle->comision_afp > 0)
                        <tr>
                            <td>{{ $payroll->periodo }}</td>
                            <td>{{ $detalle->empleado->user->name ?? '' }} {{ $detalle->empleado->user->paternal_lastname ?? '' }}</td>
                            <td>{{ $detalle->empleado->user->number ?? '' }}</td>
                            <td>{{ $detalle->regimen_pensionario ?? '-' }}</td>
                            <td>S/ {{ number_format($detalle->aporte_afp_onp, 2) }}</td>
                            <td>S/ {{ number_format($detalle->seguro_afp, 2) }}</td>
                            <td>S/ {{ number_format($detalle->comision_afp, 2) }}</td>
                            <td>S/ {{ number_format($detalle->aporte_afp_onp + $detalle->seguro_afp + $detalle->comision_afp, 2) }}</td>
                        </tr>
                        @endif
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
