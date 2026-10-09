<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * Com07Oportunidad — Oportunidad de recuperación comercial (COM-07).
 *
 * Un contacto que el proceso automático diario marcó como pendiente de
 * atención: su Requerimiento quedó en "Cotización caída" o "No cotizado", o su
 * cuenta lleva 8 semanas o más sin actividad comercial.
 *
 * La antigüedad y la condición del seguimiento NO se guardan: se derivan de
 * `ultima_actividad_at` y de `proxima_atencion_at` respectivamente, para que no
 * puedan quedar desactualizadas entre corridas del proceso.
 */
class Com07Oportunidad extends ModelTenant
{
    protected $table = 'com07_oportunidades';

    protected $fillable = [
        'contacto_id',
        'empresa_id',
        'origen',
        'requerimiento_id',
        'responsable_comercial_id',
        'ultima_actividad_at',
        'monto_historico',
        'potencial_puntaje',
        'urgencia_puntaje',
        'prioridad_puntaje',
        'prioridad_sustento',
        'proxima_atencion_at',
        'estado',
        'detectada_at',
    ];

    protected $casts = [
        'monto_historico' => 'decimal:2',
    ];

    protected $dates = [
        'ultima_actividad_at',
        'proxima_atencion_at',
        'detectada_at',
    ];

    /**
     * Campos derivados que el panel consume directamente.
     */
    protected $appends = [
        'antiguedad_dias',
        'rango_antiguedad',
        'condicion_seguimiento',
    ];

    /*
    |--------------------------------------------------------------------------
    | Origen de la detección
    |--------------------------------------------------------------------------
    */
    const ORIGEN_COTIZACION_CAIDA = 'cotizacion_caida';
    const ORIGEN_NO_COTIZADO      = 'no_cotizado';
    const ORIGEN_SIN_MOVIMIENTO   = 'sin_movimiento';

    const ORIGENES = [
        self::ORIGEN_COTIZACION_CAIDA,
        self::ORIGEN_NO_COTIZADO,
        self::ORIGEN_SIN_MOVIMIENTO,
    ];

    /*
    |--------------------------------------------------------------------------
    | Estado de la oportunidad
    |--------------------------------------------------------------------------
    */
    const ESTADO_PENDIENTE  = 'pendiente';   // detectada, sin gestionar
    const ESTADO_EN_GESTION = 'en_gestion';  // con gestiones y continuidad
    const ESTADO_CONCLUIDA  = 'concluida';   // atendida, sin acción pendiente

    const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_EN_GESTION,
        self::ESTADO_CONCLUIDA,
    ];

    /** Estados en los que la oportunidad sigue viva (bloquean una nueva detección). */
    const ESTADOS_VIGENTES = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_EN_GESTION,
    ];

    /*
    |--------------------------------------------------------------------------
    | Rangos de antigüedad (Especificación Puntual, punto 3.3)
    |--------------------------------------------------------------------------
    */
    const RANGO_RECIENTE = '8_semanas_4_meses';  // >= 8 semanas y < 4 meses
    const RANGO_MEDIO    = '4_6_meses';          // >= 4 meses y < 6 meses
    const RANGO_ANTIGUO  = 'mas_6_meses';        // >= 6 meses

    /** Umbral de inactividad a partir del cual un contacto entra al proceso. */
    const DIAS_INACTIVIDAD = 56;  // 8 semanas

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function gestiones()
    {
        return $this->hasMany(Com07Gestion::class, 'oportunidad_id')->orderByDesc('id');
    }

    /** Última gestión registrada, para mostrarla en la vista de supervisión. */
    public function ultimaGestion()
    {
        return $this->hasOne(Com07Gestion::class, 'oportunidad_id')->latest('id');
    }

    public function contacto()
    {
        return $this->belongsTo(\Modules\Comercial\Models\Contacto::class, 'contacto_id');
    }

    public function empresa()
    {
        return $this->belongsTo(\Modules\Comercial\Models\Empresa::class, 'empresa_id');
    }

    public function requerimiento()
    {
        return $this->belongsTo(\Modules\Comercial\Models\Requerimiento::class, 'requerimiento_id');
    }

    public function responsable()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'responsable_comercial_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Campos derivados
    |--------------------------------------------------------------------------
    */

    /**
     * Días transcurridos desde la última actividad comercial válida.
     *
     * @return int|null  null si no se pudo determinar la última actividad.
     */
    public function getAntiguedadDiasAttribute()
    {
        if (empty($this->ultima_actividad_at)) {
            return null;
        }

        return $this->ultima_actividad_at->startOfDay()->diffInDays(now()->startOfDay());
    }

    /**
     * Rango de antigüedad al que pertenece la oportunidad.
     *
     * @return string|null
     */
    public function getRangoAntiguedadAttribute()
    {
        if (empty($this->ultima_actividad_at)) {
            return null;
        }

        // Meses de calendario, no bloques de 30 días: la Especificación habla de
        // "4 meses" y "6 meses", y una cuenta inactiva desde el 15 de enero debe
        // cumplir 6 meses el 15 de julio, no unos días antes.
        $meses = $this->ultima_actividad_at->startOfDay()->diffInMonths(now()->startOfDay());

        if ($meses < 4) {
            return self::RANGO_RECIENTE;
        }
        if ($meses < 6) {
            return self::RANGO_MEDIO;
        }

        return self::RANGO_ANTIGUO;
    }

    /**
     * Niveles del Modelo de Priorización (Especificación 6.2): derivados de
     * los puntajes con los umbrales acordados, nunca almacenados.
     *
     * Los tres indicadores usan los mismos cortes: >= 8, >= 5, >= 3.
     */
    const NIVEL_MUY_ALTO = 'muy_alta';
    const NIVEL_ALTO     = 'alta';
    const NIVEL_MEDIO    = 'media';
    const NIVEL_BAJO     = 'baja';
    const NIVEL_NO_APLICA = 'no_aplica';

    /** @return string|null nivel del Potencial, o null si aún no se calculó */
    public function getNivelPotencialAttribute()
    {
        return $this->potencial_puntaje === null ? null : self::nivelPorPuntaje($this->potencial_puntaje);
    }

    /**
     * Nivel de la Urgencia. NULL de puntaje significa "No aplica" (sin
     * necesidad actual), que la Especificación exige mantener distinto de 0.
     *
     * @return string|null
     */
    public function getNivelUrgenciaAttribute()
    {
        if ($this->prioridad_puntaje === null && $this->potencial_puntaje === null) {
            return null;   // nunca calculada
        }

        return $this->urgencia_puntaje === null
            ? self::NIVEL_NO_APLICA
            : self::nivelPorPuntaje($this->urgencia_puntaje);
    }

    /** @return string|null */
    public function getNivelPrioridadAttribute()
    {
        return $this->prioridad_puntaje === null ? null : self::nivelPorPuntaje($this->prioridad_puntaje);
    }

    public static function nivelPorPuntaje($puntaje)
    {
        if ($puntaje >= 8) {
            return self::NIVEL_MUY_ALTO;
        }
        if ($puntaje >= 5) {
            return self::NIVEL_ALTO;
        }
        if ($puntaje >= 3) {
            return self::NIVEL_MEDIO;
        }

        return self::NIVEL_BAJO;
    }

    /**
     * Condición del seguimiento tal como la muestra el panel.
     *
     * Se deriva de la próxima fecha de atención: no hay un estado "vencido"
     * almacenado que pudiera quedar viejo entre corridas del proceso.
     *
     * @return string  'vencido' | 'pendiente' | 'sin_seguimiento'
     */
    public function getCondicionSeguimientoAttribute()
    {
        if (empty($this->proxima_atencion_at)) {
            return 'sin_seguimiento';
        }

        return $this->proxima_atencion_at->startOfDay()->isBefore(now()->startOfDay())
            ? 'vencido'
            : 'pendiente';
    }

    /**
     * Días de atraso respecto de la próxima fecha de atención.
     *
     * @return int  0 cuando no está vencida.
     */
    public function getDiasAtraso()
    {
        if ($this->condicion_seguimiento !== 'vencido') {
            return 0;
        }

        return $this->proxima_atencion_at->startOfDay()->diffInDays(now()->startOfDay());
    }

    /** ¿Sigue viva? (bloquea que el proceso genere otra sobre el mismo contacto) */
    public function estaVigente()
    {
        return in_array($this->estado, self::ESTADOS_VIGENTES, true);
    }

    /** ¿Puede mostrarse en el panel de una ejecutiva? */
    public function tieneResponsable()
    {
        return !empty($this->responsable_comercial_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeVigentes($query)
    {
        return $query->whereIn('estado', self::ESTADOS_VIGENTES);
    }

    public function scopeDeResponsable($query, $userId)
    {
        return $query->where('responsable_comercial_id', $userId);
    }

    public function scopeSinAsignar($query)
    {
        return $query->whereNull('responsable_comercial_id');
    }

    /** Seguimientos cuya fecha ya pasó sin registrarse la atención. */
    public function scopeVencidas($query)
    {
        return $query->whereNotNull('proxima_atencion_at')
                     ->whereDate('proxima_atencion_at', '<', now()->toDateString());
    }
}
