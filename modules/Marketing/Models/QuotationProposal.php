<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * QuotationProposal — Salida Comercial Derivada (COM-02 §26)
 *
 * Relación 1:1 con PreQuote. Representa el documento formal que se envía al
 * cliente (equivalente al PDF MAP...-..._... que hoy se arma manualmente
 * desde el Excel interno).
 *
 * Principio de diseño: "el sistema genera, la ejecutiva valida" — al crear
 * una proposal desde una prequote, la IA pre-llena todos los campos. La
 * ejecutiva revisa y ajusta lo que haga falta antes de emitir.
 *
 * Una vez emitida (estado = 'emitida'), el registro se vuelve inmutable para
 * preservar la integridad del documento entregado al cliente.
 */
class QuotationProposal extends ModelTenant
{
    use SoftDeletes;

    protected $table = 'quotation_proposals';

    protected $fillable = [
        'prequote_id',
        'numero_cotizacion',
        'prefijo_numeracion',
        'fecha_emision',
        'secuencial_dia',
        'version_externa',
        'destinatario_nombre',
        'destinatario_cargo',
        'destinatario_sede',
        'destinatario_empresa',
        'titulo_descriptivo',
        'notas_encabezado',
        'precios_incluyen_igv',
        'aplica_igv',
        'moneda',
        'condiciones',
        'incluye_datos_empresa',
        'incluye_datos_bancarios',
        'incluye_sostenibilidad',
        // Agosto 2026 (punto 13): mostrar u ocultar el bloque de totales del PDF.
        // Sirve para cotizaciones por escala, donde un total consolidado que suma
        // las tres alternativas no significa nada.
        'incluye_totales',
        'bank_account_id',
        'ejecutiva_id',
        'pdf_path',
        'estado',
        'emitida_at',
        'ai_provider',
        'ai_model',
    ];

    protected $casts = [
        'fecha_emision'           => 'date',
        'secuencial_dia'          => 'integer',
        'version_externa'         => 'integer',
        'precios_incluyen_igv'    => 'boolean',
        'aplica_igv'              => 'boolean',
        'incluye_datos_empresa'   => 'boolean',
        'incluye_datos_bancarios' => 'boolean',
        'incluye_sostenibilidad'  => 'boolean',
        'incluye_totales'         => 'boolean',
        'emitida_at'              => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes de estado
    |--------------------------------------------------------------------------
    */
    const ESTADO_BORRADOR          = 'borrador';
    const ESTADO_EMITIDA           = 'emitida';
    const ESTADO_ENVIADA_CLIENTE   = 'enviada_cliente';
    const ESTADO_APROBADA_CLIENTE  = 'aprobada_cliente';
    const ESTADO_RECHAZADA_CLIENTE = 'rechazada_cliente';

    const ESTADOS = [
        self::ESTADO_BORRADOR,
        self::ESTADO_EMITIDA,
        self::ESTADO_ENVIADA_CLIENTE,
        self::ESTADO_APROBADA_CLIENTE,
        self::ESTADO_RECHAZADA_CLIENTE,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function prequote()
    {
        return $this->belongsTo(PreQuote::class, 'prequote_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(\App\Models\Tenant\BankAccount::class, 'bank_account_id');
    }

    public function ejecutiva()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'ejecutiva_id');
    }

    /**
     * Kits/paquetes comerciales de la propuesta (mockup db).
     */
    public function kits()
    {
        return $this->hasMany(ProposalKit::class, 'proposal_id')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Accesores / mutadores para campos JSON
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener notas_encabezado como array (decodifica JSON).
     * @return array
     */
    public function getNotasEncabezadoArrayAttribute()
    {
        if (empty($this->notas_encabezado)) {
            return [];
        }
        $decoded = json_decode($this->notas_encabezado, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Setear notas_encabezado desde un array (codifica a JSON).
     * @param array $value
     */
    public function setNotasEncabezadoFromArray($value)
    {
        $this->notas_encabezado = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Obtener condiciones como array.
     * @return array
     */
    public function getCondicionesArrayAttribute()
    {
        if (empty($this->condiciones)) {
            return [];
        }
        $decoded = json_decode($this->condiciones, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Setear condiciones desde un array.
     * @param array $value
     */
    public function setCondicionesFromArray($value)
    {
        $this->condiciones = is_array($value) ? json_encode($value) : $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeBorradores($query)
    {
        return $query->where('estado', self::ESTADO_BORRADOR);
    }

    public function scopeEmitidas($query)
    {
        return $query->whereIn('estado', [
            self::ESTADO_EMITIDA,
            self::ESTADO_ENVIADA_CLIENTE,
            self::ESTADO_APROBADA_CLIENTE,
            self::ESTADO_RECHAZADA_CLIENTE,
        ]);
    }

    public function scopeByEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * ¿La proposal todavía es editable? (solo en borrador).
     * @return bool
     */
    public function isEditable()
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    /**
     * ¿Ya fue emitida al cliente?
     * @return bool
     */
    public function isEmitida()
    {
        return in_array($this->estado, [
            self::ESTADO_EMITIDA,
            self::ESTADO_ENVIADA_CLIENTE,
            self::ESTADO_APROBADA_CLIENTE,
            self::ESTADO_RECHAZADA_CLIENTE,
        ]);
    }

    /**
     * ¿El cliente aprobó la cotización? (disparador para convertir a
     * Quotation formal).
     * @return bool
     */
    public function isAprobadaPorCliente()
    {
        return $this->estado === self::ESTADO_APROBADA_CLIENTE;
    }

    /**
     * Construye el número de cotización a partir de los 4 componentes.
     * Formato: {prefijo}{YYMMDD}-{secuencial}_{version}
     * Ej: MAP240917-07_03
     *
     * @return string
     */
    public function buildNumeroCotizacion()
    {
        $fecha = $this->fecha_emision ? $this->fecha_emision : now();
        $yymmdd = date('ymd', strtotime($fecha));
        $seq = str_pad($this->secuencial_dia, 2, '0', STR_PAD_LEFT);
        $ver = str_pad($this->version_externa, 2, '0', STR_PAD_LEFT);

        return $this->prefijo_numeracion . $yymmdd . '-' . $seq . '_' . $ver;
    }
}
