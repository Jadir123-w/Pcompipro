<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="ie=edge">
        <title>Prices</title>
    </head>
    <body>  
        @if(!empty($records))
            <div class="">
                <div class=" ">
                    <table class="">
                        <thead>
                            <tr>
                                <th>Código Interno</th>
                                @foreach($records as $key => $value)
                                <th>Precio Unitario Venta ({{$value->id}})</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            
                            <tr>
                                <td class="celda">BI001</td>
                                @foreach($records as $key => $value)
                                <td class="celda">100</td>
                                @endforeach 
                            </tr> 
                            <tr>
                                <td class="celda">BI002</td>
                                @foreach($records as $key => $value)
                                <td class="celda"></td>
                                @endforeach 
                            </tr> 
                            
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div>
                <p>No se encontraron registros.</p>
            </div>
        @endif
    </body>
</html>
