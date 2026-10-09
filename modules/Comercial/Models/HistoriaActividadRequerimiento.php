<?php
/**
 * ============================================================================
 * ARCHIVO 2: HistoriaActividadRequerimiento.php (MODELO)
 * ============================================================================
 * Ubicación: Modules/Comercial/Models/HistoriaActividadRequerimiento.php
 * 
 * CAMBIOS REALIZADOS:
 * - Corregidos los campos fillable para coincidir con la tabla real
 * - La tabla tiene: tipo_actividad, detalle, etapa_anterior, etapa_nueva, usuario
 * - La tabla SÍ tiene deleted_at, agregado SoftDeletes
 * - NOTA: La columna 'usuario' es VARCHAR(150), NO es un ID de usuario
 * ============================================================================
 */

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;

class HistoriaActividadRequerimiento extends ModelTenant
{
    use SoftDeletes;

    protected $table = 'historia_actividad_requerimiento';

    /**
     * CAMPOS SEGÚN LA TABLA REAL (imagen historia_actividad_requerimiento.png):
     * - id
     * - requerimiento_id
     * - tipo_actividad (varchar 100)
     * - detalle (text)
     * - etapa_anterior (varchar 100)
     * - etapa_nueva (varchar 100)
     * - usuario (varchar 150) <-- OJO: Es el NOMBRE del usuario, no un ID
     * - created_at
     * - updated_at
     * - deleted_at
     */
    protected $fillable = [
        'requerimiento_id',
        'tipo_actividad',
        'detalle',
        'etapa_anterior',
        'etapa_nueva',
        'usuario',
    ];

    protected $casts = [
        'requerimiento_id' => 'integer',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Relación con el requerimiento padre
     */
    public function requerimiento()
    {
        return $this->belongsTo(Requerimiento::class, 'requerimiento_id');
    }

    /**
     * NOTA: La columna 'usuario' es VARCHAR, no es una FK.
     * Si en el futuro se necesita la relación, se debe agregar una columna usuario_id
     */
}
