<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Ventas por marca</title>
    </head>
    <body>
        <div>
            <h3 align="center" class="title"><strong>Reporte de Ventas por Marca</strong></h3>
        </div>
        <br>
        <div style="margin-top:20px; margin-bottom:15px;">
            <table>
                <tr>
                    <td>
                        <p><b>Empresa: </b></p>
                    </td>
                    <td align="center" colspan="3">
                        <p><strong>{{$company->name}}</strong></p>
                    </td>
                </tr>
                <tr>
                    <td>
                        <p><strong>Ruc: </strong></p>
                    </td>
                    <td align="center" colspan="3">{{$company->number}}</td>
                </tr>
                <tr>
                    <td>
                        <p><strong>Fecha: </strong></p>
                    </td>
                    <td align="center" colspan="3">
                        <p>
                            @if($date_start == $date_end)
                                {{ \Carbon\Carbon::parse($date_start)->format('d/m/Y') }}
                            @else
                                {{ \Carbon\Carbon::parse($date_start)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($date_end)->format('d/m/Y') }}
                            @endif
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <br>
        @if(!empty($records) && count($records) > 0)
            <div>
                <table border="1">
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
                                <td>{{$loop->iteration}}</td>
                                <td>{{$row['brand_name']}}</td>
                                <td>{{number_format($row['quantity_sold'], 2)}}</td>
                                <td>{{number_format($row['total_sale_value'], 2)}}</td>
                                <td>{{number_format($row['total_profit'], 2)}}</td>
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
                            <th colspan="2">Totales</th>
                            <th>{{number_format($total_quantity, 2)}}</th>
                            <th>{{number_format($total_sale_value, 2)}}</th>
                            <th>{{number_format($total_profit, 2)}}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div>
                <p>No se encontraron registros.</p>
            </div>
        @endif