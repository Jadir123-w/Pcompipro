<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Reporte General de ventas</title>
</head>
    <style>
    .pdf-table {
    width: 100%;
    border-collapse: collapse;
    font-family: DejaVu Sans, sans-serif; /* soporta UTF-8 */
    font-size: 11px;
}

    .pdf-table th, .pdf-table td {
        border: 1px solid #444;  /* borde visible en impresión */
        padding: 6px 10px;
        text-align: left;
    }

    .pdf-table thead th {
        background-color: #f2f2f2; /* gris claro */
        font-weight: bold;
    }

    .pdf-table tbody tr:nth-child(even) {
        background-color: #fafafa; /* alternado */
    }
    
    </style>
<body class="ticket">
    <table class="pdf-table">
        <thead>
            <tr>
                <th>Establecimiento: </th>
                <th>
                    {{ $establishment->description }}
                </th>
            </tr>
            <tr>
                @php
                    $description_period = '';
                    switch ($filter['period']) {
                        case 'month':
                            $description_period = "Por mes";
                            break;
                        case 'date':
                            $description_period = "Por fecha";
                            break;
                        case 'between_dates':
                            $description_period = "Entre fechas";
                            break;
                        
                    }
                @endphp
                <th>
                    Periodo:
                </th>
                <th>
                    {{ $description_period }}
                </th>

            </tr>
            <tr>
                @if ($filter['period'] == 'date' && isset($filter['date_start']))
                    <th>
                        Fecha de inicio:
                    </th>
                    <th>
                        {{ $filter['date_start']  }}
                    </th>
                @elseif ($filter['period'] == 'month' && isset($filter['month_start']))
                    <th>
                        Mes de inicio:
                    </th>
                    <th>
                        {{ $filter['month_start']  }}
                    </th>
            </tr>
                @elseif ($filter['period'] == 'between_dates' && isset($filter['date_start']) && isset($filter['date_end']))
                <tr>
                    <th>
                        Fecha de inicio:
                    </th>
                    <th>
                        {{ $filter['date_start']  }}
                    </th>
                </tr>
                <tr>
                    <th>
                        Fecha de fin:
                    </th>
                    <th>
                        {{ $filter['date_end']  }}
                    </th>

                </tr>
                @endif
        </thead>

    </table>
    <table class="pdf-table" style="margin-top: 120px">
       <thead>
        <tr>
            <th></th>
            <th>Totales</th>
        </tr>
        </thead> 
        <tbody>
            @php
                $data = $data['data'];
            @endphp
            <tr>
                <th>Total de nota de venta</th>
                <th>{{ $data['totals']['total_sale_notes']}}</th>
            </tr>
            <tr>
                <th>Total de comprobantes</th>
                <th>{{ $data['totals']['total_documents']}}</th>
            </tr>
            <tr>
                <th>Total de pedidos</th>
                <th>{{ $data['totals']['total_order_notes']}}</th>
            </tr>
            <tr>
                <th>Total</th>
                <th>{{ $data['totals']['total']}}</th>
            </tr>
        </tbody>

    </table>

</body>
</html>