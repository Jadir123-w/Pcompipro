<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FeriaReceived — Feria recibida (MKT-03)
 *
 * Evento/feria recibido desde el agente de ferias (o manual).
 * Dispara la generación de 3 momentos editoriales: 2m, 1m, 15d antes.
 */
class FeriaReceived extends ModelTenant
{
    use SoftDeletes;

    protected $table = 'mkt_ferias_received';

    protected $fillable = [
        'nombre',
        'fecha',
        'sector',
        'ciudad',
        'tipo_participacion',
        'objetivo_comunicacional',
        'enfoque_sugerido',
        'contexto_interno',
        'fase_editorial_actual',
        'piezas_2m_generadas',
        'piezas_1m_generadas',
        'piezas_15d_generadas',
        'source',
        'external_feria_id',
        'redes_sociales_target',
        'estado',
    ];

    protected $casts = [
        'fecha'                 => 'date',
        'piezas_2m_generadas'   => 'boolean',
        'piezas_1m_generadas'   => 'boolean',
        'piezas_15d_generadas'  => 'boolean',
        'redes_sociales_target' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Constantes
    |--------------------------------------------------------------------------
    */
    const FASE_2M    = '2m';
    const FASE_1M    = '1m';
    const FASE_15D   = '15d';
    const FASE_COMPLETADA = 'completada';

    const FASES = [self::FASE_2M, self::FASE_1M, self::FASE_15D, self::FASE_COMPLETADA];

    const ESTADO_ACTIVA       = 'activa';
    const ESTADO_EN_COBERTURA = 'en_cobertura';
    const ESTADO_CONCLUIDA    = 'concluida';
    const ESTADO_CANCELADA    = 'cancelada';

    const SOURCE_MANUAL        = 'manual';
    const SOURCE_AGENTE_FERIAS = 'agente_ferias';

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function gridItems()
    {
        return $this->hasMany(GridItem::class, 'feria_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Calcular la fase editorial activa según la fecha actual.
     *
     * @return string|null
     */
    public function calcularFaseActual()
    {
        if (!$this->fecha) {
            return null;
        }

        $hoy = now()->startOfDay();
        $fechaFeria = $this->fecha->startOfDay();
        $diasRestantes = $hoy->diffInDays($fechaFeria, false);

        if ($diasRestantes < 0) {
            return self::FASE_COMPLETADA;
        }

        if ($diasRestantes <= 15) {
            return self::FASE_15D;
        }

        if ($diasRestantes <= 30) {
            return self::FASE_1M;
        }

        if ($diasRestantes <= 60) {
            return self::FASE_2M;
        }

        return null; // Aún muy lejos
    }
}
