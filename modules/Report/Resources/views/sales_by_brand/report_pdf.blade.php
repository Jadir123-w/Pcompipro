<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/pdf; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Ventas por marca</title>
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
            <p align="center" class="title"><strong>Reporte de ventas por marca</strong></p>
        </div>
        <div style="margin-top:20px; margin-bottom:20px;">
            <table>
                <tr>
                    <td>
                        <p><strong>Empresa: </strong>{{$company->name}}</p>
                    </td>
                    <td>
                        <p>
                        <strong>Fecha: </strong>
                        @if($date_start == $date_end)
                            {{ \Carbon\Carbon::parse($date_start)->format('d/m/Y') }}
                        @else
                            {{ \Carbon\Carbon::parse($date_start)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($date_end)->format('d/m/Y') }}
                        @endif
                        </p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p><strong>Ruc: </strong>{{$company->number}}</p>
                    </td>
                </tr>
            </table>
        </div>
        @if(!empty($records) && count($records) > 0)
            <div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Marca</th>
                            <th class="text-center">Cantidad vendida</th>
                            <th class="text-center">Valor de venta (sin IGV)</th>
                            <th class="text-center">Utilidad generada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $total_quantity = 0;
                            $total_sale_value = 0;
                            $total_profit = 0;
                        @endphp
                        @foreach($records as $row)
                            <tr>
                                <td class="celda">{{$loop->iteration}}</td>
                                <td class="celda">{{$row['brand_name']}}</td>
                                <td class="celda">{{number_format($row['quantity_sold'], 2)}}</td>
                                <td class="celda">{{number_format($row['total_sale_value'], 2)}}</td>
                                <td class="celda">{{number_format($row['total_profit'], 2)}}</td>
                            </tr>
                            @php
                                $total_quantity += $row['quantity_sold'];
                                $total_sale_value += $row['total_sale_value'];
                                $total_profit += $row['total_profit'];
                            @endphp
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th class="celda" colspan="2">Totales</th>
                            <th class="celda">{{number_format($total_quantity, 2)}}</th>
                            <th class="celda">{{number_format($total_sale_value, 2)}}</th>
                            <th class="celda">{{number_format($total_profit, 2)}}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="callout callout-info">
                <p>No se encontraron registros.</p>
            </div>
        @endif
    </body>
</html>