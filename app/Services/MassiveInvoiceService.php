<?php

namespace App\Services;

use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Carbon\Carbon;
use App\Models\System\Client;
use App\Http\Controllers\System\ServiceController;

class MassiveInvoiceService
{
    protected $serviceController;

    public function __construct(ServiceController $serviceController)
    {
        $this->serviceController = $serviceController;
    }

    public function processExcel($file)
    {
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        array_shift($rows); // Eliminar encabezados
        
        return $this->transformToJsonRequests($rows);
    }

    private function transformToJsonRequests(array $rows)
    {
        return collect($rows)->map(function($row) {
            if (empty($row[0])) return null;

            // Validaciones básicas
            if (!$this->validateRow($row)) {
                throw new \Exception("Fila con datos incompletos o inválidos");
            }

            $rucEmisor = $row[2];
            $client = Client::where('number', $rucEmisor)->first();
            
            if (!$client || empty($client->token)) {
                throw new \Exception("Cliente no encontrado o sin token: {$rucEmisor}");
            }

            // Procesamiento de datos
            $tipoComprobante = strtolower($row[3]) === 'boleta' ? '03' : '01';
            $tipoAfectacion = $this->getTipoAfectacion($row[19] ?? '10');
            $cantidad = floatval($row[17] ?? 1);
            $precio = floatval($row[20] ?? 0);
            $incluyeIgv = strtolower(trim($row[11] ?? '')) === 'si';
            
            // Cálculos de montos según tipo de afectación e IGV incluido
            $montos = $this->calcularMontos($precio, $cantidad, $tipoAfectacion, $incluyeIgv);

            // Validar documento del receptor
            $receptorDocNum = $row[5] ?? '';
            $this->validateReceptorDocument($receptorDocNum, $tipoComprobante);

            // Consultar datos del receptor
            $receptorData = $this->getReceptorData($receptorDocNum, $tipoComprobante);

            return [
                'tenant_id' => $rucEmisor,
                'token' => $client->token,
                'ruc_emisor' => $rucEmisor,
                'data' => [
                    'serie_documento' => $row[4] ?? '',
                    'numero_documento' => '#',
                    'fecha_de_emision' => Carbon::parse($row[0])->format('Y-m-d'),
                    'hora_de_emision' => Carbon::now()->format('H:i:s'),
                    'codigo_tipo_operacion' => '0101',
                    'codigo_tipo_documento' => $tipoComprobante,
                    'codigo_tipo_moneda' => $row[7] ?? 'PEN',
                    'fecha_de_vencimiento' => Carbon::parse($row[1])->format('Y-m-d'),
                    'numero_orden_de_compra' => $row[10] ?? '',
                    
                    'datos_del_cliente_o_receptor' => array_merge([
                        'codigo_tipo_documento_identidad' => $tipoComprobante === '03' ? '1' : '6',
                        'numero_documento' => $receptorDocNum,
                        'codigo_pais' => 'PE',
                        'correo_electronico' => $row[6] ?? '',
                        'telefono' => ''
                    ], $receptorData),
                    
                    'totales' => [
                        'total_exportacion' => 0,
                        'total_operaciones_gravadas' => $tipoAfectacion == '10' ? $montos['baseImponible'] : 0,
                        'total_operaciones_inafectas' => $tipoAfectacion == '30' ? $montos['baseImponible'] : 0,
                        'total_operaciones_exoneradas' => $tipoAfectacion == '20' ? $montos['baseImponible'] : 0,
                        'total_operaciones_gratuitas' => 0,
                        'total_igv' => $tipoAfectacion == '10' ? $montos['igv'] : 0,
                        'total_impuestos' => $tipoAfectacion == '10' ? $montos['igv'] : 0,
                        'total_valor' => $montos['baseImponible'],
                        'total_venta' => $montos['total']
                    ],
                    
                    'items' => [[
                        'codigo_interno' => $row[15] ?? '',
                        'descripcion' => $row[16] ?? '',
                        'codigo_producto_sunat' => '51121703',
                        'unidad_de_medida' => $this->normalizeUnidadMedida($row[18] ?? 'NIU'),
                        'cantidad' => $cantidad,
                        'valor_unitario' => $montos['valorUnitario'],
                        'codigo_tipo_precio' => '01',
                        'precio_unitario' => ($tipoAfectacion == '20' || $tipoAfectacion == '30')
                            ? $precio
                            : ($incluyeIgv ? $precio : round($precio * 1.18, 2)),
                        'codigo_tipo_afectacion_igv' => $tipoAfectacion,
                        'total_base_igv' => $tipoAfectacion == '10' ? $montos['baseImponible'] : (
                            $tipoAfectacion == '20' ? $montos['baseImponible'] : (
                                $tipoAfectacion == '30' ? $montos['baseImponible'] : 0
                            )
                        ),
                        'porcentaje_igv' => 18,
                        'total_igv' => $tipoAfectacion == '10' ? $montos['igv'] : 0,
                        'total_impuestos' => $tipoAfectacion == '10' ? $montos['igv'] : 0,
                        'total_valor_item' => $montos['baseImponible'],
                        'total_item' => $montos['total']
                    ]],
                    
                    'informacion_adicional' => "Forma de pago:{$row[8]}|{$row[9]}",
                ]
            ];
        })->filter()->values()->all();
    }

    private function calcularMontos($precio, $cantidad, $tipoAfectacion, $incluyeIgv)
    {
        $igvPercentage = ($tipoAfectacion == '20' || $tipoAfectacion == '30') ? 0 : 18;

        if ($igvPercentage == 0) {
            // Exonerado o inafecto, no hay IGV
            $valorUnitario = $precio;
            $baseImponible = $valorUnitario * $cantidad;
            $igv = 0;
            $total = $baseImponible;
        } else {
            if ($incluyeIgv) {
                // El precio incluye IGV
                $valorUnitario = round($precio / (1 + ($igvPercentage/100)), 2);
                $baseImponible = round($valorUnitario * $cantidad, 2);
                $igv = round($baseImponible * ($igvPercentage/100), 2);
                $total = round($precio * $cantidad, 2);
            } else {
                // El precio es base, hay que sumarle IGV
                $valorUnitario = $precio;
                $baseImponible = round($valorUnitario * $cantidad, 2);
                $igv = round($baseImponible * ($igvPercentage/100), 2);
                $total = round($baseImponible + $igv, 2);
            }
        }

        return [
            'valorUnitario' => $valorUnitario,
            'baseImponible' => $baseImponible,
            'igv' => $igv,
            'total' => $total,
            'igvPercentage' => $igvPercentage
        ];
    }

    private function validateRow($row)
    {
        return !empty($row[0]) && // Fecha emisión
               !empty($row[2]) && // RUC emisor
               !empty($row[3]) && // Tipo comprobante
               !empty($row[4]) && // Serie
               !empty($row[5]);   // RUC/DNI receptor
    }

    private function normalizeUnidadMedida($unidad)
    {
        $unidades = [
            'UNIDAD SERVICIOS' => 'ZZ',
            'UNIDAD' => 'NIU',
            'SERVICIO' => 'ZZ'
        ];
        
        return $unidades[strtoupper($unidad)] ?? 'NIU';
    }

    private function getReceptorData($numero, $tipoComprobante) 
    {
        $data = [
            'apellidos_y_nombres_o_razon_social' => 'CLIENTE GENERAL',
            'direccion' => 'DIRECCION GENERAL',
            'ubigeo' => '150101'
        ];
        
        try {
            $serviceData = new \Modules\ApiPeruDev\Data\ServiceData();
            $response = $serviceData->service($tipoComprobante === '03' ? 'dni' : 'ruc', $numero);

            if (isset($response['success']) && $response['success']) {
                $data['apellidos_y_nombres_o_razon_social'] = $response['data']['name'];
                $data['direccion'] = $response['data']['address'] ?? 'DIRECCION GENERAL';

                // Solo para RUC y si location_id existe y es array
                if ($tipoComprobante === '01' && 
                    isset($response['data']['location_id']) && 
                    is_array($response['data']['location_id']) && 
                    count($response['data']['location_id']) === 3 &&
                    !empty($response['data']['location_id'][2])) {
                    
                    $data['ubigeo'] = $response['data']['location_id'][2];

                    \Log::debug("Ubigeo construido:", [
                        'location_id' => $response['data']['location_id'],
                        'ubigeo_final' => $data['ubigeo']
                    ]);
                }

                \Log::debug("Datos del receptor procesados:", [
                    'response' => $response,
                    'data_final' => $data
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error al consultar datos del receptor: ' . $e->getMessage());
        }

        return $data;
    }

    private function getTipoAfectacion($tipo) 
    {
        $tipos = [
            'GRAVADO_OPERACION_ONEROSA' => '10',
            'INAFECTO_OPERACION_ONEROSA' => '30', 
            'EXONERADO_OPERACION_ONEROSA' => '20'
        ];

        if (in_array($tipo, ['10', '20', '30'])) {
            return $tipo;
        }

        return $tipos[$tipo] ?? '10';
    }

    private function validateReceptorDocument($numero, $tipoComprobante)
    {
        if ($tipoComprobante === '01' && strlen($numero) !== 11) {
            throw new \Exception("Para facturas el receptor debe tener RUC válido de 11 dígitos");
        }

        return true;
    }
}
