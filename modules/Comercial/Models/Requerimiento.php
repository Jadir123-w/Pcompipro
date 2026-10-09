<?php
/**
 * ============================================================================
 * ARCHIVO 4: Requerimiento.php (MODELO)
 * ============================================================================
 * Ubicación: Modules/Comercial/Models/Requerimiento.php
 * 
 * CAMBIOS REALIZADOS:
 * - Agregado método actualizarUltimaInteraccion() que faltaba
 * - Agregado método cambiarEtapa() que faltaba
 * - Agregado método aprobarCotizacion() que faltaba
 * - Agregado método escalarAProyecto() que faltaba
 * - Agregado accessor getDiasHastaSeguimientoAttribute() que faltaba
 * ============================================================================
 */

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Auth;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Requerimiento extends ModelTenant implements Auditable
{
    use AuditableTrait;
    // ⚠️ NO usar SoftDeletes - la tabla NO tiene deleted_at

    protected $table = 'requerimiento';

    protected $fillable = [
        // Campos originales
        'empresa_id',
        'lead_id',
        'titulo',
        'descripcion',
        'monto_estimado',
        'fecha_requerida',
        'etapa',
        'estado',
        
        // Campos nuevos agregados
        'contacto_id',
        'origen',
        'nivel_prioridad',
        'fecha_ingreso_etapa_actual',
        'tipo_condicion_proyecto',
        'motivo_compra',
        'forma_pago',
        'nivel_tentativo',
        'categoria_productos',
        'usuario_apoyo_cotizacion',
        'cotizacion_aprobada',
        'fecha_aprobacion',
        'escalado_a_proyecto',
        'fecha_escalado_proyecto',
        'activo_en_crm',
        'archivos_cliente',
        'usuario_ultima_interaccion',
        'fecha_ultima_interaccion',
        'fecha_seguimiento',
    ];

    protected $casts = [
        'empresa_id'                 => 'integer',
        'lead_id'                    => 'integer',
        'contacto_id'                => 'integer',
        'monto_estimado'             => 'decimal:2',
        'fecha_requerida'            => 'date',
        'usuario_apoyo_cotizacion'   => 'integer',
        'usuario_ultima_interaccion' => 'integer',
        'fecha_ingreso_etapa_actual' => 'datetime',
        'cotizacion_aprobada'        => 'boolean',
        'fecha_aprobacion'           => 'datetime',
        'escalado_a_proyecto'        => 'boolean',
        'fecha_escalado_proyecto'    => 'datetime',
        'activo_en_crm'              => 'boolean',
        'archivos_cliente'           => 'array',
        'fecha_ultima_interaccion'   => 'datetime',
        'fecha_seguimiento'          => 'datetime',
    ];

    protected $attributes = [
        'origen'              => 'manual',
        'nivel_prioridad'     => 'Medio',
        'activo_en_crm'       => true,
        'cotizacion_aprobada' => false,
        'escalado_a_proyecto' => false,
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'fecha_requerida',
        'fecha_ingreso_etapa_actual',
        'fecha_aprobacion',
        'fecha_escalado_proyecto',
        'fecha_ultima_interaccion',
        'fecha_seguimiento',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($requerimiento) {
            if (!$requerimiento->fecha_ingreso_etapa_actual) {
                $requerimiento->fecha_ingreso_etapa_actual = now();
            }
            if (!$requerimiento->etapa) {
                $requerimiento->etapa = 'Pendiente atender';
            }
            if (!$requerimiento->estado) {
                $requerimiento->estado = 'abierto';
            }
        });
    }

    /* ========== RELACIONES ========== */

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function contacto()
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function usuarioApoyo()
    {
        return $this->belongsTo(User::class, 'usuario_apoyo_cotizacion');
    }

    public function usuarioUltimaInteraccion()
    {
        return $this->belongsTo(User::class, 'usuario_ultima_interaccion');
    }

    public function proyectos()
    {
        return $this->hasMany(Proyecto::class, 'requerimiento_id');
    }

    public function interacciones()
    {
        return $this->hasMany(RequerimientoInteraccion::class, 'requerimiento_id')
               ->orderBy('created_at', 'desc');
    }

    public function cotizaciones()
    {
        return $this->hasMany(CotizacionRequerimiento::class, 'requerimiento_id')
               ->orderBy('version', 'desc');
    }

    public function historial()
    {
        return $this->hasMany(HistoriaActividadRequerimiento::class, 'requerimiento_id')
               ->orderBy('created_at', 'desc');
    }

    /* ========== SCOPES ========== */

    public function scopeActivos($query)
    {
        // Nota: La tabla no tiene soft deletes, retorna todos los registros
        return $query;
    }

    public function scopeActivosEnCrm($query)
    {
        return $query->where('activo_en_crm', true);
    }

    public function scopePorEtapa($query, $etapa)
    {
        return $query->where('etapa', $etapa);
    }

    public function scopePorEmpresa($query, $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePorPrioridad($query, $prioridad)
    {
        return $query->where('nivel_prioridad', $prioridad);
    }

    public function scopePorOrigen($query, $origen)
    {
        return $query->where('origen', $origen);
    }

    public function scopeRequierenSeguimiento($query)
    {
        return $query->whereNotNull('fecha_seguimiento')
                     ->whereDate('fecha_seguimiento', '<=', now());
    }

    public function scopeConCotizacionAprobada($query)
    {
        return $query->where('cotizacion_aprobada', true);
    }

    public function scopeOrdenadosPorPrioridad($query)
    {
        return $query->orderByRaw("FIELD(nivel_prioridad, 'Muy alto', 'Alto', 'Medio', 'Bajo')");
    }

    public function scopeOrdenadosPorAntiguedad($query)
    {
        return $query->orderBy('fecha_ingreso_etapa_actual', 'asc');
    }

    public function scopePendientesAtencion($query)
    {
        return $query->activosEnCrm()
                     ->where('etapa', 'Pendiente atender');
    }

    public function scopeSinRespuestaReciente($query, $dias = 3)
    {
        return $query->activosEnCrm()
                     ->where(function($q) use ($dias) {
                         $q->whereNull('fecha_ultima_interaccion')
                           ->orWhere('fecha_ultima_interaccion', '<=', now()->subDays($dias));
                     });
    }

    /* ========== ACCESSORS ========== */

    public function getNombreEmpresaAttribute()
    {
        return $this->empresa ? $this->empresa->razon_social : null;
    }

    public function getNombreContactoAttribute()
    {
        return $this->contacto ? $this->contacto->nombre : null;
    }

    public function getDiasEnEtapaAttribute()
    {
        if (!$this->fecha_ingreso_etapa_actual) {
            return 0;
        }
        return now()->diffInDays($this->fecha_ingreso_etapa_actual);
    }

    /**
     * Días hasta el próximo seguimiento
     * Retorna número negativo si ya pasó la fecha
     */
    public function getDiasHastaSeguimientoAttribute()
    {
        if (!$this->fecha_seguimiento) {
            return null;
        }
        return now()->diffInDays($this->fecha_seguimiento, false);
    }

    public function getSeguimientoVencidoAttribute()
    {
        if (!$this->fecha_seguimiento) {
            return false;
        }
        return now()->isAfter($this->fecha_seguimiento);
    }

    public function getUltimaCotizacionAttribute()
    {
        return $this->cotizaciones()->first();
    }

    /* ========== MÉTODOS AUXILIARES ========== */

    /**
     * Verificar si el requerimiento está atrasado (más de 5 días en la misma etapa)
     */
    public function estaAtrasado()
    {
        if (!$this->fecha_ingreso_etapa_actual) {
            return false;
        }
        
        $diasEnEtapa = now()->diffInDays($this->fecha_ingreso_etapa_actual);
        return $diasEnEtapa > 5;
    }

    /**
     * Actualizar la última interacción del requerimiento
     * 
     * @param int|null $usuarioId ID del usuario que realizó la interacción
     * @return void
     */
    public function actualizarUltimaInteraccion($usuarioId = null)
    {
        $this->usuario_ultima_interaccion = $usuarioId ?: Auth::id();
        $this->fecha_ultima_interaccion = now();
        $this->save();
    }

    /**
     * Cambiar la etapa del requerimiento y registrar en historial
     * 
     * @param string $nuevaEtapa
     * @param int|null $usuarioId
     * @return void
     * @throws \Exception
     */
    public function cambiarEtapa($nuevaEtapa, $usuarioId = null)
    {
        $etapaAnterior = $this->etapa;
        
        // Validar que la etapa sea diferente
        if ($etapaAnterior === $nuevaEtapa) {
            return;
        }

        // Actualizar etapa y fecha de ingreso
        $this->etapa = $nuevaEtapa;
        $this->fecha_ingreso_etapa_actual = now();
        $this->save();

        // Registrar en historial
        $this->historial()->create([
            'tipo_actividad' => 'Cambio de etapa',
            'detalle'        => "Cambio de etapa: {$etapaAnterior} → {$nuevaEtapa}",
            'etapa_anterior' => $etapaAnterior,
            'etapa_nueva'    => $nuevaEtapa,
            'usuario'        => $usuarioId ? User::find($usuarioId)->name : (Auth::user()->name ?? 'Sistema'),
        ]);

        // Actualizar última interacción
        $this->actualizarUltimaInteraccion($usuarioId);
    }

    /**
     * Aprobar una cotización
     * 
     * @param int $cotizacionId
     * @return void
     */
    public function aprobarCotizacion($cotizacionId)
    {
        $this->cotizacion_aprobada = true;
        $this->fecha_aprobacion = now();
        $this->save();

        // Cambiar etapa a "Cotización aprobada"
        $this->cambiarEtapa('Cotización aprobada', Auth::id());

        // Registrar en historial
        $this->historial()->create([
            'tipo_actividad' => 'Cotización aprobada',
            'detalle'        => "Se aprobó la cotización #{$cotizacionId}",
            'etapa_anterior' => $this->etapa,
            'etapa_nueva'    => 'Cotización aprobada',
            'usuario'        => Auth::user()->name ?? 'Sistema',
        ]);
    }

    /**
     * Escalar requerimiento a proyecto
     * 
     * @param array $datosProyecto
     * @return Proyecto
     * @throws \Exception
     */
    public function escalarAProyecto(array $datosProyecto)
    {
        if (!$this->cotizacion_aprobada) {
            throw new \Exception('Solo se pueden escalar requerimientos con cotización aprobada.');
        }

        if ($this->escalado_a_proyecto) {
            throw new \Exception('Este requerimiento ya fue escalado a proyecto.');
        }

        // Crear proyecto
        $proyecto = Proyecto::create($datosProyecto);

        // Marcar como escalado
        $this->escalado_a_proyecto = true;
        $this->fecha_escalado_proyecto = now();
        $this->activo_en_crm = false;
        $this->save();

        // Registrar en historial
        $this->historial()->create([
            'tipo_actividad' => 'Escalado a proyecto',
            'detalle'        => "Requerimiento escalado a proyecto #{$proyecto->id}",
            'etapa_anterior' => $this->etapa,
            'etapa_nueva'    => 'Escalado a proyecto',
            'usuario'        => Auth::user()->name ?? 'Sistema',
        ]);

        return $proyecto;
    }
}
