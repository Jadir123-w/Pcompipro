<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Reporte cuentas pendientes</title>
    </head>
    <body>
        <div>
            <h3 align="center" class="title"><strong>Reporte de cuentas pendientes</strong></h3>
        </div>
        <br>
        <div style="margin-top:20px; margin-bottom:15px;">
            <table>
                <tr>
                    <td>
                        <p><b>Empresa: </b></p>
                    </td>
                    <td align="center">
                        <p><strong>{{$company->name}}</strong></p>
                    </td>
                    <td>
                        <p><strong>Fecha: </strong></p>
                    </td>
                    <td align="center">
                        <p><strong>{{date('Y-m-d')}}</strong></p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p><strong>Ruc: </strong></p>
                    </td>
                    <td align="center">{{$company->number}}</td>
                </tr>
            </table>
        </div>
        <br>
        @if(!empty($records))
            <div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Usuario</th>
                            <th class="text-center">Monto cobro fuera de fecha</th>
                            <th class="text-center">Monto de cobro comisionado</th>
                            <th class="text-center">Monto total de cobro efectuado</th>
                            <th class="text-center">Comisión</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($records as $row)
                        <tr>
                            <td class="celda">{{$loop->iteration}}</td>
                            <td class="celda">{{$row['user_name']}}</td>
                            <td class="celda text-center">{{ $row['total_out_of_range'] }}</td>
                            <td class="celda text-center">{{ $row['total_within_range'] }}</td>
                            <td class="celda text-center">{{ $row['total_completed'] }}</td>
                            <td class="celda text-center">{{ $row['commission'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div>
                <p>No se encontraron registros.</p>
            </div>
        @endif
    </body>
</html>