<?php
/**
 * ============================================================================
 * Proyecto.php (MODELO)
 * ============================================================================
 * Ubicación: Modules/Comercial/Models/Proyecto.php
 *
 * Modelo completo del proyecto con los 85 campos de la tabla,
 * relaciones, casts y generación automática de código.
 * ============================================================================
 */

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Proyecto extends ModelTenant implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $table = 'proyecto';

    protected $fillable = [
        // Datos generales
        'empresa_id', 'contacto_id', 'requerimiento_id', 'codigo', 'nombre',
        // Agosto 2026 (punto 17): nombre del coordinador a cargo. Texto libre,
        // porque puede ser alguien sin usuario en el sistema.
        'coordinador',
        'descripcion', 'tipo', 'prioridad', 'ejecutivo_id',
        'monto_total', 'monto_costo', 'costo_total_proyecto', 'margen_pct',
        'oc_numero', 'oc_fecha', 'arte_aprobado_at',
        'adelanto_monto', 'adelanto_fecha', 'notas',
        // Criterios
        'tiempo_proyecto', 'politica_tiempos_cortos', 'es_recompra',
        'tipo_cliente_1', 'tipo_cliente_2', 'tiene_oc',
        // Facturación
        'servicio_facturado', 'cantidad_razones_sociales', 'cada_rs_con_oc',
        'aplica_detraccion', 'aplica_retencion', 'forma_pago', 'dias_credito', 'pago_directo',
        'contacto_facturacion', 'contactos_facturacion', 'correo_factura',
        'requiere_formato_especial', 'entrega_factura_fisica',
        'direccion_entrega_factura', 'documento_adicional_factura', 'comentario_facturacion',
        // Muestras
        'muestra_virtual', 'muestra_fisica', 'prueba_color',
        // Logística
        'tipo_embalaje', 'embalaje_grupal', 'rotulo_especial',
        'sticker_compipro', 'cantidad_sticker',
        // Despacho
        'contacto_despacho', 'tipo_entrega', 'direccion_entrega',
        'horario_atencion_despacho', 'documentacion_adicional_despacho',
        'tamano_movilidad', 'requiere_programacion_despacho', 'confirmacion_ingreso',
        // Seguridad
        'sctr_personal_logistica', 'epps_logistica',
        'sctr_conductor', 'epps_conductor', 'documentacion_transportista',
        // Post Venta
        'carta_conformidad', 'solicitud_promocion', 'acepta_solicitud_promocion',
        'tiene_datos_cumpleanos', 'estatus_proveedores', 'resumen_proyecto',
        // Cobranza
        'fecha_envio_factura', 'fecha_ingreso_factura', 'fecha_tentativa_pago',
        'metodo_politica_pago', 'pago_conforme', 'observaciones_cobranza',
        // Progreso y estado
        'fecha_inicio', 'fecha_fin', 'etapa', 'semaforo',
        'porcentaje_avance', 'porcentaje_facturado', 'porcentaje_cobrado', 'estado',
    ];

    protected $casts = [
        'empresa_id'          => 'integer',
        'contacto_id'         => 'integer',
        'requerimiento_id'    => 'integer',
        'ejecutivo_id'        => 'integer',
        'monto_total'         => 'decimal:2',
        'monto_costo'         => 'decimal:2',
        'costo_total_proyecto' => 'decimal:2',
        'margen_pct'          => 'decimal:3',
        'adelanto_monto'      => 'decimal:2',
        'porcentaje_avance'   => 'decimal:2',
        'porcentaje_facturado' => 'decimal:2',
        'porcentaje_cobrado'  => 'decimal:2',
        'cantidad_razones_sociales' => 'integer',
        'fecha_inicio'        => 'date',
        'fecha_fin'           => 'date',
        'oc_fecha'            => 'date',
        'adelanto_fecha'      => 'date',
        'fecha_envio_factura'    => 'date',
        'fecha_ingreso_factura'  => 'date',
        'fecha_tentativa_pago'   => 'date',
        // Booleans (tinyint)
        'cada_rs_con_oc'               => 'boolean',
        'aplica_detraccion'            => 'boolean',
        'aplica_retencion'             => 'boolean',
        'pago_directo'                 => 'boolean',
        'requiere_formato_especial'    => 'boolean',
        'muestra_virtual'              => 'boolean',
        'muestra_fisica'               => 'boolean',
        'prueba_color'                 => 'boolean',
        'rotulo_especial'              => 'boolean',
        'sticker_compipro'             => 'boolean',
        'requiere_programacion_despacho' => 'boolean',
        'confirmacion_ingreso'         => 'boolean',
        'sctr_personal_logistica'      => 'boolean',
        'epps_logistica'               => 'boolean',
        'sctr_conductor'               => 'boolean',
        'epps_conductor'               => 'boolean',
        'documentacion_transportista'  => 'boolean',
        'carta_conformidad'            => 'boolean',
        'solicitud_promocion'          => 'boolean',
        'acepta_solicitud_promocion'   => 'boolean',
        'tiene_datos_cumpleanos'       => 'boolean',
    ];

    // =========================================================================
    // BOOT — Generación automática de código al crear
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($proyecto) {
            if (empty($proyecto->codigo)) {
                $proyecto->codigo = static::generarCodigo($proyecto->empresa_id);
            }
        });
    }

    /**
     * Generar código: PRY-{AÑO}-{MES}-{DIA}-{CORRELATIVO_GLOBAL}
     * Ejemplo: PRY-2026-MAR-28-0001
     * El correlativo es global y nunca reinicia.
     */
    public static function generarCodigo($empresaId = null)
    {
        $meses = [
            1 => 'ENE', 2 => 'FEB', 3 => 'MAR', 4 => 'ABR',
            5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AGO',
            9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DIC',
        ];

        $anio = date('Y');
        $mes = $meses[(int)date('m')];
        $dia = date('d');

        // Correlativo global: buscar el último proyecto con formato PRY-
        $ultimo = static::where('codigo', 'like', 'PRY-%')
            ->orderBy('id', 'desc')
            ->first();

        if ($ultimo) {
            // Extraer los últimos 4 dígitos del código
            $correlativo = intval(substr($ultimo->codigo, -4)) + 1;
        } else {
            // Si no hay proyectos con formato nuevo, contar todos los existentes
            $totalExistentes = static::count();
            $correlativo = $totalExistentes + 1;
        }

        return "PRY-{$anio}-{$mes}-{$dia}-" . str_pad($correlativo, 4, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // RELACIONES
    // =========================================================================

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function requerimiento()
    {
        return $this->belongsTo(Requerimiento::class, 'requerimiento_id');
    }

    public function productos()
    {
        return $this->hasMany(ProyectoProducto::class, 'proyecto_id');
    }

    public function razonesSociales()
    {
        return $this->hasMany(ProyectoRazonSocial::class, 'proyecto_id');
    }

    public function evaluacionesProveedor()
    {
        return $this->hasMany(ProyectoEvaluacionProveedor::class, 'proyecto_id');
    }
}
