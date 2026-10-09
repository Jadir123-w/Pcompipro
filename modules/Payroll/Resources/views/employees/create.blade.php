@extends('tenant.layouts.app')

@push('styles')

@endpush

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">{{ isset($employee) ? 'Editar' : 'Crear' }} Empleado</h3>
                    </div>

                  @if ($errors->any())
                    <div class="alert alert-danger">
                      <ul>
                        @foreach ($errors->all() as $error)
                          <li>{{ $error }}</li>
                        @endforeach
                      </ul>
                    </div>
                  @endif

                    <form action="{{ isset($employee) ? route('tenant.payroll.employees.update', $employee->id) : route('tenant.payroll.employees.store') }}" method="POST">
                        @csrf
                        @if(isset($employee)) @method('PUT') @endif

                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Datos Personales</h5>
                                    <hr>

                                    <div class="form-group">
                                        <label for="dni">DNI *</label>
                                        <input type="text" name="dni" id="dni"
                                               class="form-control" value="{{ old('dni', $employee->dni ?? '') }}"
                                               required maxlength="8">
                                    </div>

                                    <div class="form-group">
                                        <label for="nombres">Nombres *</label>
                                        <input type="text" name="nombres" id="nombres"
                                               class="form-control" value="{{ old('nombres', $employee->nombres ?? '') }}"
                                               required>
                                    </div>

                                    <div class="form-group">
                                        <label for="apellidos">Apellidos *</label>
                                        <input type="text" name="apellidos" id="apellidos"
                                               class="form-control" value="{{ old('apellidos', $employee->apellidos ?? '') }}"
                                               required>
                                    </div>

                                    <div class="form-group">
                                        <label for="fecha_nacimiento">Fecha Nacimiento *</label>
                                        <input type="date" name="fecha_nacimiento" id="fecha_nacimiento"
                                               class="form-control" value="{{ old('fecha_nacimiento', $employee->fecha_nacimiento ?? '') }}"
                                               required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h5>Datos Laborales</h5>
                                    <hr>

                                    <div class="form-group">
                                        <label for="email">Email *</label>
                                        <input type="email" name="email" id="email"
                                               class="form-control" value="{{ old('email', $employee->email ?? '') }}"
                                               required>
                                    </div>

                                    <div class="form-group">
                                        <label for="fecha_ingreso">Fecha Ingreso *</label>
                                        <input type="date" name="fecha_ingreso" id="fecha_ingreso"
                                               class="form-control" value="{{ old('fecha_ingreso', $employee->fecha_ingreso ?? '') }}"
                                               required>
                                    </div>

                                    <div class="form-group">
                                        <label for="tipo_contrato">Tipo Contrato *</label>
                                        <select name="tipo_contrato" id="tipo_contrato" class="form-control" required>
                                            <option value="CAS" {{ old('tipo_contrato', $employee->tipo_contrato ?? '') == 'CAS' ? 'selected' : '' }}>CAS</option>
                                            <option value="Practicante" {{ old('tipo_contrato', $employee->tipo_contrato ?? '') == 'Practicante' ? 'selected' : '' }}>Practicante</option>
                                            <option value="Aprendiz" {{ old('tipo_contrato', $employee->tipo_contrato ?? '') == 'Aprendiz' ? 'selected' : '' }}>Aprendiz</option>
                                            <option value="Otros" {{ old('tipo_contrato', $employee->tipo_contrato ?? '') == 'Otros' ? 'selected' : '' }}>Otros</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="sueldo_basico">Sueldo Básico (S/) *</label>
                                        <input type="number" name="sueldo_basico" id="sueldo_basico"
                                               class="form-control" step="0.01" min="0"
                                               value="{{ old('sueldo_basico', $employee->sueldo_basico ?? '') }}"
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <h5>Información de Contacto</h5>
                                    <hr>

                                    <div class="form-group">
                                        <label for="telefono">Teléfono</label>
                                        <input type="text" name="telefono" id="telefono"
                                               class="form-control" value="{{ old('telefono', $employee->telefono ?? '') }}"
                                               maxlength="15">
                                    </div>

                                    <div class="form-group">
                                        <label for="direccion">Dirección</label>
                                        <textarea name="direccion" id="direccion"
                                                  class="form-control" rows="3">{{ old('direccion', $employee->direccion ?? '') }}</textarea>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <h5>Información Bancaria</h5>
                                    <hr>

                                    <div class="form-group">
                                        <label for="banco">Banco</label>
                                        <input type="text" name="banco" id="banco"
                                               class="form-control" value="{{ old('banco', $employee->banco ?? '') }}">
                                    </div>

                                    <div class="form-group">
                                        <label for="numero_cuenta">Número de Cuenta</label>
                                        <input type="text" name="numero_cuenta" id="numero_cuenta"
                                               class="form-control" value="{{ old('numero_cuenta', $employee->numero_cuenta ?? '') }}">
                                    </div>

                                    <div class="form-group">
                                        <div class="form-check">
                                            <input type="checkbox" name="activo" id="activo"
                                                   class="form-check-input" value="1"
                                                {{ old('activo', $employee->activo ?? true) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="activo">Empleado Activo</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> {{ isset($employee) ? 'Actualizar' : 'Guardar' }}
                            </button>
                            <a href="{{ route('tenant.payroll.employees.index') }}" class="btn btn-default">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
