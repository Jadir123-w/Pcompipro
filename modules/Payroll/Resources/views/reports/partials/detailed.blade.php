
<div class="row mt-4">
    <div class="col-md-12">
        <h3>Reporte Detallado de Planillas</h3>
        <p>Periodo: {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}</p>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                <tr>
                    <th>Periodo</th>
                    <th>Empleado</th>
                    <th>DNI</th>
                    <th>Ingresos</th>
                    <th>Descuentos</th>
                    <th>Neto</th>
                </tr>
                </thead>
                <tbody>
                @foreach($payrolls as $payroll)
                    @foreach($payroll->detalles as $detalle)
                    <tr>
                        <td>{{ $payroll->periodo }}</td>
                        <td>{{ $detalle->empleado->user->name ?? '' }} {{ $detalle->empleado->user->paternal_lastname ?? '' }}</td>
                        <td>{{ $detalle->empleado->user->number ?? '' }}</td>
                        <td>S/ {{ number_format($detalle->total_ingresos, 2) }}</td>
                        <td>S/ {{ number_format($detalle->total_descuentos, 2) }}</td>
                        <td>S/ {{ number_format($detalle->neto_pagar, 2) }}</td>
                    </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
