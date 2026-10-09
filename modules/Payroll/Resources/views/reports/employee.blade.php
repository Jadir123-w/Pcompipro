@extends('tenant.layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h3>Reporte de Pagos por Empleado</h3>
                <p><strong>Empleado:</strong> {{ $employee->user->name }} {{ $employee->user->paternal_lastname }}</p>
                <p><strong>DNI:</strong> {{ $employee->user->number }}</p>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th>Periodo</th>
                        <th>Fecha de Pago</th>
                        <th>Días Laborados</th>
                        <th>Ingresos Totales</th>
                        <th>Descuentos Totales</th>
                        <th>Neto Recibido</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($payrolls as $payroll)
                        @php
                            // Since we loaded details filtered by employee, the first detail should be the one for this employee
                            $detalle = $payroll->detalles->first();
                        @endphp
                        @if($detalle)
                        <tr>
                            <td>{{ $payroll->periodo }}</td>
                            <td>{{ $payroll->fecha_pago ? $payroll->fecha_pago->format('d/m/Y') : '-' }}</td>
                            <td>{{ $detalle->dias_laborados }}</td>
                            <td>S/ {{ number_format($detalle->total_ingresos, 2) }}</td>
                            <td>S/ {{ number_format($detalle->total_descuentos, 2) }}</td>
                            <td>S/ {{ number_format($detalle->neto_pagar, 2) }}</td>
                            <td>
                                <a href="{{ url('payroll-main/imprimir-boleta/'.$payroll->id.'/'.$detalle->id) }}" class="btn btn-danger btn-sm" target="_blank">
                                    <i class="fas fa-file-pdf"></i> Boleta
                                </a>
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No se encontraron registros de pago.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                <div class="mt-4">
                     <a href="{{ route('tenant.payroll.reports.index') }}" class="btn btn-secondary">Volver</a>
                </div>
            </div>
        </div>
    </div>
@endsection
