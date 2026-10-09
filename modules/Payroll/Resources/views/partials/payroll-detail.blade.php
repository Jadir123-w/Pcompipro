<div class="row">
    <div class="col-md-6">
        <h5>Datos del Empleado</h5>
        <table class="table table-sm">
            <tr>
                <th width="120">DNI:</th>
                <td>{{ $payroll->employee->dni }}</td>
            </tr>
            <tr>
                <th>Nombre:</th>
                <td>{{ $payroll->employee->nombres }} {{ $payroll->employee->apellidos }}</td>
            </tr>
            <tr>
                <th>Fecha Ingreso:</th>
                <td>{{ $payroll->employee->fecha_ingreso->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <th>Tipo Contrato:</th>
                <td>{{ $payroll->employee->tipo_contrato }}</td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h5>Información de Pago</h5>
        <table class="table table-sm">
            <tr>
                <th width="120">Periodo:</th>
                <td>{{ $payroll->periodo->format('F Y') }}</td>
            </tr>
            <tr>
                <th>Días Trabajados:</th>
                <td>{{ $payroll->dias_trabajados }} días</td>
            </tr>
            <tr>
                <th>Sueldo Básico:</th>
                <td>S/ {{ number_format($payroll->employee->sueldo_basico, 2) }}</td>
            </tr>
        </table>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <h5>Ingresos</h5>
        <table class="table table-bordered">
            <tr>
                <td>Sueldo Proporcional</td>
                <td class="text-right">S/ {{ number_format($payroll->total_ingresos - $payroll->bonificaciones - $payroll->otros_ingresos, 2) }}</td>
            </tr>
            <tr>
                <td>Horas Extras ({{ $payroll->horas_extras }} hrs)</td>
                <td class="text-right">S/ {{ number_format($payroll->horas_extras * (($payroll->employee->sueldo_basico / 30) / 8) * 1.25, 2) }}</td>
            </tr>
            <tr>
                <td>Bonificaciones</td>
                <td class="text-right">S/ {{ number_format($payroll->bonificaciones, 2) }}</td>
            </tr>
            <tr>
                <td>Otros Ingresos</td>
                <td class="text-right">S/ {{ number_format($payroll->otros_ingresos, 2) }}</td>
            </tr>
            <tr class="table-success">
                <th>TOTAL INGRESOS</th>
                <th class="text-right">S/ {{ number_format($payroll->total_ingresos, 2) }}</th>
            </tr>
        </table>
    </div>

    <div class="col-md-6">
        <h5>Descuentos</h5>
        <table class="table table-bordered">
            <tr>
                <td>ESSALUD (9%)</td>
                <td class="text-right">S/ {{ number_format($payroll->essalud, 2) }}</td>
            </tr>
            <tr>
                <td>AFP Fondo (10%)</td>
                <td class="text-right">S/ {{ number_format($payroll->afp_fondo, 2) }}</td>
            </tr>
            <tr>
                <td>AFP Seguro (1.368%)</td>
                <td class="text-right">S/ {{ number_format($payroll->afp_seguro, 2) }}</td>
            </tr>
            <tr>
                <td>AFP Comisión (0.77%)</td>
                <td class="text-right">S/ {{ number_format($payroll->afp_comision, 2) }}</td>
            </tr>
            <tr>
                <td>5ta Categoría</td>
                <td class="text-right">S/ {{ number_format($payroll->quinta_categoria, 2) }}</td>
            </tr>
            <tr>
                <td>Adelantos</td>
                <td class="text-right">S/ {{ number_format($payroll->adelantos, 2) }}</td>
            </tr>
            <tr>
                <td>Otros Descuentos</td>
                <td class="text-right">S/ {{ number_format($payroll->otros_descuentos, 2) }}</td>
            </tr>
            <tr class="table-danger">
                <th>TOTAL DESCUENTOS</th>
                <th class="text-right">S/ {{ number_format($payroll->total_descuentos, 2) }}</th>
            </tr>
        </table>
    </div>
</div>
