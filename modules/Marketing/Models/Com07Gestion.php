<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * Com07Gestion — Atención registrada sobre una oportunidad (COM-07).
 *
 * Qué resultado obtuvo la ejecutiva, qué observó y, cuando corresponde, qué
 * acción sigue y en qué fecha.
 *
 * La tabla es de sólo agregar: una gestión no se edita ni se borra, cada
 * atención suma una fila. El historial del contacto se reconstruye leyendo la
 * secuencia.
 *
 * El seguimiento vive en las columnas `seguimiento_*` porque siempre nace de
 * una gestión: es la respuesta a "¿y ahora qué?". Su condición VENCIDO no se
 * guarda, se deriva comparando la fecha con hoy.
 */
class Com07Gestion extends ModelTenant
{
    protected $table = 'com07_gestiones';

    protected $fillable = [
        'oportunidad_id',
        'user_id',
        'resultado',
        'observacion',
        'proxima_accion',
        'seguimiento_at',
        'seguimiento_at_anterior',
        'seguimiento_estado',
        'seguimiento_motivo',
    ];

    protected $dates = [
        'seguimiento_at',
        'seguimiento_at_anterior',
    ];

    protected $appends = [
        'condicion_seguimiento',
    ];

    /*
    |--------------------------------------------------------------------------
    | Resultados de la gestión
    |--------------------------------------------------------------------------
    | Los seis de la Especificación Puntual, punto 14.
    */
    const RESULTADO_SIN_RESPONDER        = 'sin_respuesta';
    const RESULTADO_NUEVO_SEGUIMIENTO    = 'requiere_nuevo_seguimiento';
    const RESULTADO_EXISTE_INTERES       = 'existe_interes';
    const RESULTADO_NECESIDAD_FUTURA     = 'necesidad_futura';
    const RESULTADO_REQUIERE_CONTINUIDAD = 'requiere_continuidad';
    const RESULTADO_CONCLUIDA            = 'concluida';

    const RESULTADOS = [
        self::RESULTADO_SIN_RESPONDER,
        self::RESULTADO_NUEVO_SEGUIMIENTO,
        self::RESULTADO_EXISTE_INTERES,
        self::RESULTADO_NECESIDAD_FUTURA,
        self::RESULTADO_REQUIERE_CONTINUIDAD,
        self::RESULTADO_CONCLUIDA,
    ];

    /** Resultados que cierran la oportunidad si no se programa un seguimiento. */
    const RESULTADOS_DE_CIERRE = [
        self::RESULTADO_CONCLUIDA,
    ];

    /*
    |--------------------------------------------------------------------------
    | Estado del seguimiento
    |--------------------------------------------------------------------------
    | VENCIDO no aparece: es una condición derivada de la fecha, no un estado
    | que alguien decida. Ver getCondicionSeguimientoAttribute().
    */
    const SEG_PENDIENTE = 'pendiente';
    const SEG_ATENDIDO  = 'atendido';
    const SEG_CANCELADO = 'cancelado';

    const SEG_ESTADOS = [
        self::SEG_PENDIENTE,
        self::SEG_ATENDIDO,
        self::SEG_CANCELADO,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function oportunidad()
    {
        return $this->belongsTo(Com07Oportunidad::class, 'oportunidad_id');
    }

    public function usuario()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Campos derivados
    |--------------------------------------------------------------------------
    */

    /**
     * Condición vigente del seguimiento que programó esta gestión.
     *
     * @return string|null  'pendiente' | 'vencido' | 'atendido' | 'cancelado'
     *                      null cuando la gestión no programó seguimiento.
     */
    public function getCondicionSeguimientoAttribute()
    {
        if (empty($this->seguimiento_estado)) {
            return null;
        }

        // Atendido y cancelado son definitivos: la fecha ya no los cambia.
        if ($this->seguimiento_estado !== self::SEG_PENDIENTE) {
            return $this->seguimiento_estado;
        }

        if (empty($this->seguimiento_at)) {
            return self::SEG_PENDIENTE;
        }

        return $this->seguimiento_at->startOfDay()->isBefore(now()->startOfDay())
            ? 'vencido'
            : self::SEG_PENDIENTE;
    }

    /** ¿Esta gestión dejó una acción pendiente? */
    public function dejaContinuidad()
    {
        return $this->seguimiento_estado === self::SEG_PENDIENTE
            && !empty($this->seguimiento_at);
    }

    /**
     * Reprogramar el seguimiento conservando la fecha anterior.
     *
     * @param  \DateTimeInterface|string  $nuevaFecha
     * @return void
     */
    public function reprogramar($nuevaFecha)
    {
        $this->seguimiento_at_anterior = $this->seguimiento_at;
        $this->seguimiento_at          = $nuevaFecha;
        $this->seguimiento_estado      = self::SEG_PENDIENTE;
        $this->save();
    }

    /**
     * Cancelar el seguimiento porque dejó de aplicar. No elimina el registro.
     *
     * @param  string|null  $motivo
     * @return void
     */
    public function cancelarSeguimiento($motivo = null)
    {
        $this->seguimiento_estado = self::SEG_CANCELADO;
        $this->seguimiento_motivo = $motivo;
        $this->save();
    }

    /** Marcar el seguimiento como atendido (lo hace la gestión siguiente). */
    public function marcarAtendido()
    {
        $this->seguimiento_estado = self::SEG_ATENDIDO;
        $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePendientes($query)
    {
        return $query->where('seguimiento_estado', self::SEG_PENDIENTE);
    }

    public function scopeVencidos($query)
    {
        return $query->where('seguimiento_estado', self::SEG_PENDIENTE)
                     ->whereNotNull('seguimiento_at')
                     ->whereDate('seguimiento_at', '<', now()->toDateString());
    }
}
