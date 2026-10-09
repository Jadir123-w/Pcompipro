@extends('tenant.layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Reportes de Planillas</h3>
                    </div>
                    <div class="card-body" id="app-payroll-reports">
                        <tenant-payroll-report-form
                            :configuration="{{ json_encode([
                                'fecha_inicio' => request('fecha_inicio', date('Y-m-01')),
                                'fecha_fin' => request('fecha_fin', date('Y-m-t')),
                                'tipo_reporte' => request('tipo_reporte', 'detallado')
                            ]) }}"
                            url-generate="{{ route('tenant.payroll.reports.index') }}"
                            url-export="{{ route('tenant.payroll.reports.export') }}"
                            url-clean="{{ route('tenant.payroll.reports.index') }}"
                            :show-clean="{{ isset($reportView) ? 'true' : 'false' }}"
                        >
                            @csrf
                        </tenant-payroll-report-form>

                        <hr>

                        @if(isset($reportView))
                            @include($reportView)
                        @else
                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h5>Reportes por Empleado</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                            <tr>
                                                <th>Empleado</th>
                                                <th>DNI</th>
                                                <th>Total Planillas</th>
                                                <th>Último Periodo</th>
                                                <th>Acciones</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @isset($employees)
                                                @foreach($employees as $employee)
                                                    <tr>
                                                        <td>{{ $employee->user->name }} {{ $employee->user->paternal_lastname }}</td>
                                                        <td>{{ $employee->user->number }}</td>
                                                        <td>{{ $employee->payrolls_count }}</td>
                                                        <td>
                                                            @if($employee->latest_payroll)
                                                                {{ $employee->latest_payroll->periodo }}
                                                            @else
                                                                Sin planillas
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <a href="{{ route('tenant.payroll.reports.employee', $employee->id) }}"
                                                               class="btn btn-info btn-sm">
                                                                <i class="fas fa-eye"></i> Ver Reporte
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            @endisset
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function exportReport() {
            let form = document.getElementById('reportForm');
            let originalAction = form.action;
            
            form.action = "{{ route('tenant.payroll.reports.export') }}";
            form.submit();
            
            // Restore action after short delay
            setTimeout(() => {
                form.action = originalAction;
            }, 100);
        }
    </script>
@endpush
