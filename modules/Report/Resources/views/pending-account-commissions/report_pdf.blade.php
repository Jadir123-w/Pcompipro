<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/pdf; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Reporte cuentas pendientes</title>
        <style>
            html {
                font-family: sans-serif;
                font-size: 12px;
            }
            table {
                width: 100%;
                border-spacing: 0;
                border: 1px solid black;
            }
            .celda {
                text-align: center;
                padding: 5px;
                border: 0.1px solid black;
            }
            th {
                padding: 5px;
                text-align: center;
                border-color: #0088cc;
                border: 0.1px solid black;
            }
            .title {
                font-weight: bold;
                padding: 5px;
                font-size: 20px !important;
                text-decoration: underline;
            }
            p>strong {
                margin-left: 5px;
                font-size: 13px;
            }
            thead {
                font-weight: bold;
                background: #0088cc;
                color: white;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div>
            <p align="center" class="title"><strong>Reporte de cuentas pendientes</strong></p>
        </div>
        <div style="margin-top:20px; margin-bottom:20px;">
            <table>
                <tr>
                    <td>
                        <p><strong>Empresa: </strong>{{$company->name}}</p>
                    </td>
                    <td>
                        <p><strong>Fecha: </strong>{{date('Y-m-d')}}</p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p><strong>Ruc: </strong>{{$company->number}}</p>
                    </td>
                </tr>
            </table>
        </div>
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
                                <td class="celda">{{ $row['total_out_of_range'] }}</td>
                                <td class="celda">{{ $row['total_within_range'] }}</td>
                                <td class="celda">{{ $row['total_completed'] }}</td>
                                <td class="celda">{{ $row['commission'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="callout callout-info">
                <p>No se encontraron registros.</p>
            </div>
        @endif
    </body>
</html>