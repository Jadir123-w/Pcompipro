
<div class="row mt-4">
    <div class="col-md-12">
        <h3>Reporte ESSALUD</h3>
        <p>Periodo: {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}</p>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                <tr>
                    <th>Periodo</th>
                    <th>Empleado</th>
                    <th>DNI</th>
                    <th>Sueldo Base</th>
                    <th>Aporte ESSALUD (9%)</th>
                </tr>
                </thead>
                <tbody>
                @foreach($payrolls as $payroll)
                    @foreach($payroll->detalles as $detalle)
                        @if($detalle->essalud > 0)
                        <tr>
                            <td>{{ $payroll->periodo }}</td>
                            <td>{{ $detalle->empleado->user->name ?? '' }} {{ $detalle->empleado->user->paternal_lastname ?? '' }}</td>
                            <td>{{ $detalle->empleado->user->number ?? '' }}</td>
                            <td>S/ {{ number_format($detalle->sueldo_basico, 2) }}</td>
                            <td>S/ {{ number_format($detalle->essalud, 2) }}</td>
                        </tr>
                        @endif
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
