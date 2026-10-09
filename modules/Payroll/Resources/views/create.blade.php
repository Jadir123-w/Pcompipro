@extends('tenant.layouts.app')

@push('styles')

@endpush


@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Registrar Nueva Planilla</h3>
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

                    <form action="{{ route('tenant.payroll.store') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="employee_id">Empleado *</label>
                                        <select name="employee_id" id="employee_id" class="form-control" required>
                                            <option value="">Seleccionar empleado</option>
                                            @foreach($employees as $employee)
                                                <option value="{{ $employee->id }}">
                                                    {{ $employee->dni }} - {{ $employee->nombres }} {{ $employee->apellidos }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="periodo">Periodo *</label>
                                        <input type="month" name="periodo" id="periodo" class="form-control"
                                               value="{{ old('periodo', date('Y-m')) }}" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="dias_trabajados">Días Trabajados *</label>
                                        <input type="number" name="dias_trabajados" id="dias_trabajados"
                                               class="form-control" min="1" max="31" value="30" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <h5>Ingresos Adicionales</h5>
                                    <hr>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="horas_extras">Horas Extras</label>
                                        <input type="number" name="horas_extras" id="horas_extras"
                                               class="form-control" min="0" step="0.5" value="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="bonificaciones">Bonificaciones (S/)</label>
                                        <input type="number" name="bonificaciones" id="bonificaciones"
                                               class="form-control" min="0" step="0.01" value="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="otros_ingresos">Otros Ingresos (S/)</label>
                                        <input type="number" name="otros_ingresos" id="otros_ingresos"
                                               class="form-control" min="0" step="0.01" value="0">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <h5>Descuentos</h5>
                                    <hr>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="adelantos">Adelantos (S/)</label>
                                        <input type="number" name="adelantos" id="adelantos"
                                               class="form-control" min="0" step="0.01" value="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="otros_descuentos">Otros Descuentos (S/)</label>
                                        <input type="number" name="otros_descuentos" id="otros_descuentos"
                                               class="form-control" min="0" step="0.01" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-calculator"></i> Calcular Planilla
                            </button>
                            <a href="{{ route('tenant.payroll.index') }}" class="btn btn-default">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#employee_id').select2({
                placeholder: 'Seleccionar empleado...'
            });

          $("#periodo").datepicker( {
            format: "yyyy-mm-01",
            viewMode: "months",
            minViewMode: "months",
            language: "es",
            orientation: "bottom"
          });

        });
    </script>
@endpush
