@extends('tenant.layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Detalle del Empleado</h3>
                        <div class="card-tools">
                            <a href="{{ route('tenant.payroll.employees.edit', $employee->id) }}" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <a href="{{ route('tenant.payroll.employees.index') }}" class="btn btn-default btn-sm">
                                <i class="fas fa-arrow-left"></i> Volver
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5>Datos Personales</h5>
                                <table class="table table-bordered">
                                    <tr><th>DNI:</th><td>{{ $employee->dni }}</td></tr>
                                    <tr><th>Nombres:</th><td>{{ $employee->nombres }}</td></tr>
                                    <tr><th>Apellidos:</th><td>{{ $employee->apellidos }}</td></tr>
                                    <tr><th>Fecha Nacimiento:</th><td>{{ $employee->fecha_nacimiento->format('d/m/Y') }}</td></tr>
                                    <tr><th>Email:</th><td>{{ $employee->email }}</td></tr>
                                    <tr><th>Teléfono:</th><td>{{ $employee->telefono ?? 'N/A' }}</td></tr>
                                    <tr><th>Dirección:</th><td>{{ $employee->direccion ?? 'N/A' }}</td></tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Datos Laborales</h5>
                                <table class="table table-bordered">
                                    <tr><th>Fecha Ingreso:</th><td>{{ $employee->fecha_ingreso->format('d/m/Y') }}</td></tr>
                                    <tr><th>Tipo Contrato:</th><td>{{ $employee->tipo_contrato }}</td></tr>
                                    <tr><th>Sueldo Básico:</th><td>S/ {{ number_format($employee->sueldo_basico, 2) }}</td></tr>
                                    <tr><th>Banco:</th><td>{{ $employee->banco ?? 'N/A' }}</td></tr>
                                    <tr><th>Número Cuenta:</th><td>{{ $employee->numero_cuenta ?? 'N/A' }}</td></tr>
                                    <tr><th>Estado:</th>
                                        <td>
                                        <span class="badge badge-{{ $employee->activo ? 'success' : 'danger' }}">
                                            {{ $employee->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
