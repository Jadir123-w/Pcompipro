<?php
/**
 * ============================================================================
 * ARCHIVO 1: RequerimientoInteraccion.php (MODELO)
 * ============================================================================
 * Ubicación: Modules/Comercial/Models/RequerimientoInteraccion.php
 * 
 * CAMBIOS REALIZADOS:
 * - Corregidos los campos fillable para coincidir con la tabla real
 * - La tabla tiene: canal, resumen, requiere_seguimiento, proximo_seguimiento, 
 *   archivo_adjunto, user_id (NO usuario_id)
 * - Agregado SoftDeletes porque la tabla SÍ tiene deleted_at
 * - Corregida la relación usuario() para usar 'user_id'
 * ============================================================================
 */

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class RequerimientoInteraccion extends ModelTenant implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $table = 'requerimiento_interaccion';

    /**
     * CAMPOS SEGÚN LA TABLA REAL (imagen requerimiento_interaccion.png):
     * - id
     * - requerimiento_id
     * - canal (varchar 100)
     * - resumen (text)
     * - requiere_seguimiento (tinyint)
     * - proximo_seguimiento (timestamp)
     * - archivo_adjunto (text)
     * - user_id (bigint unsigned) <-- OJO: es user_id, NO usuario_id
     * - created_at
     * - updated_at
     * - deleted_at
     */
    protected $fillable = [
        'requerimiento_id',
        'canal',
        'resumen',
        'requiere_seguimiento',
        'proximo_seguimiento',
        'archivo_adjunto',
        'user_id',
    ];

    protected $casts = [
        'requerimiento_id'     => 'integer',
        'user_id'              => 'integer',
        'requiere_seguimiento' => 'boolean',
        'proximo_seguimiento'  => 'datetime',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'proximo_seguimiento',
    ];

    /**
     * Cada vez que se crea/edita/elimina una interacción, se actualiza el
     * `updated_at` del Requerimiento padre. Esto hace que el requerimiento
     * suba al tope del listado ordenado por última actualización.
     */
    protected $touches = ['requerimiento'];

    /**
     * Relación con el requerimiento padre
     */
    public function requerimiento()
    {
        return $this->belongsTo(Requerimiento::class, 'requerimiento_id');
    }

    /**
     * Relación con el usuario que registró la interacción
     * IMPORTANTE: La columna en la tabla es 'user_id', NO 'usuario_id'
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias para compatibilidad con código que use 'user'
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
