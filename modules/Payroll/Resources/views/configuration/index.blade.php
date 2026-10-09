@extends('tenant.layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Configuración de Planillas</h3>
                    </div>
                    <form action="{{ route('tenant.payroll.configuration.update') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <h5>Parámetros Generales</h5>
                                    <hr>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="uit">UIT (S/)</label>
                                        <input type="number" name="config[uit]" id="uit"
                                               class="form-control" step="0.01" min="0"
                                               value="{{ $settings->uit ?? 4950 }}" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="dias_mes_laboral">Días Mes Laboral</label>
                                        <input type="number" name="config[dias_mes_laboral]" id="dias_mes_laboral"
                                               class="form-control" min="1" max="31"
                                               value="{{ $settings->dias_mes_laboral ?? 30 }}" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="horas_dia_laboral">Horas Día Laboral</label>
                                        <input type="number" name="config[horas_dia_laboral]" id="horas_dia_laboral"
                                               class="form-control" min="1" max="24"
                                               value="{{ $settings->horas_dia_laboral ?? 8 }}" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="tasa_hora_extra">Tasa Hora Extra</label>
                                        <input type="number" name="config[tasa_hora_extra]" id="tasa_hora_extra"
                                               class="form-control" step="0.01" min="1" max="2"
                                               value="{{ $settings->tasa_hora_extra ?? 1.25 }}" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h5>Tasas de Aportes y Descuentos Generales</h5>
                                    <hr>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="porcentaje_essalud">ESSALUD (%)</label>
                                        <input type="number" name="config[porcentaje_essalud]" id="porcentaje_essalud"
                                               class="form-control" step="0.01" min="0" max="100"
                                               value="{{ $settings->porcentaje_essalud ?? 9.00 }}" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="porcentaje_onp">ONP (%)</label>
                                        <input type="number" name="config[porcentaje_onp]" id="porcentaje_onp"
                                               class="form-control" step="0.01" min="0" max="100"
                                               value="{{ $settings->porcentaje_onp ?? 13.00 }}" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <h5>Configuración de AFPs</h5>
                                    <hr>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th>AFP</th>
                                                    <th>Comisión Flujo (%)</th>
                                                    <th>Comisión Mixta (%)</th>
                                                    <th>Aporte Obligatorio (%)</th>
                                                    <th>Seguro Invalidez (%)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($afps as $afp)
                                                <tr>
                                                    <td>{{ $afp->nombre }}</td>
                                                    <td>
                                                        <input type="number" name="afps[{{ $afp->id }}][comision_flujo]" 
                                                               class="form-control form-control-sm" step="0.01" min="0" 
                                                               value="{{ $afp->comision_flujo }}">
                                                    </td>
                                                    <td>
                                                        <input type="number" name="afps[{{ $afp->id }}][comision_mixta]" 
                                                               class="form-control form-control-sm" step="0.01" min="0" 
                                                               value="{{ $afp->comision_mixta }}">
                                                    </td>
                                                    <td>
                                                        <input type="number" name="afps[{{ $afp->id }}][aporte_obligatorio]" 
                                                               class="form-control form-control-sm" step="0.01" min="0" 
                                                               value="{{ $afp->aporte_obligatorio }}">
                                                    </td>
                                                    <td>
                                                        <input type="number" name="afps[{{ $afp->id }}][seguro_invalidez]" 
                                                               class="form-control form-control-sm" step="0.01" min="0" 
                                                               value="{{ $afp->seguro_invalidez }}">
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Configuración
                            </button>
                            <a href="{{ route('tenant.payroll.index') }}" class="btn btn-default">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
