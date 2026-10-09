<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Model;

class MassiveInvoice extends Model
{
    protected $fillable = [
        'fecha_emision',
        'fecha_vencimiento',
        'ruc_emisor',
        'tipo_comprobante',
        'serie_comprobante',
        'ruc',
        'correo',
        'moneda',
        'forma_pago',
        'observacion',
        'orden_compra',
        'incluye_igv',
        'incluye_detraccion',
        'porcentaje_detraccion',
        'servicio_detraccion',
        'item',
        'descripcion_producto',
        'cantidad',
        'unidad_medida',
        'tipo_afectacion',
        'precio',
        'status',
        'nota',
        'external_id',
        'pdf_link',
        'xml_link',
        'cdr_link',
        'estado_sunat',
        'mensaje_sunat',
        'total_gravado',
        'total_igv', 
        'total_venta'
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'incluye_igv' => 'boolean',
        'incluye_detraccion' => 'boolean',
        'cantidad' => 'decimal:2',
        'precio' => 'decimal:2',
        'porcentaje_detraccion' => 'decimal:2',
        'total_gravado' => 'decimal:2',
        'total_igv' => 'decimal:2',
        'total_venta' => 'decimal:2'
    ];
}
