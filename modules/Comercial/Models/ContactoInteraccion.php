<?php
/**
 * ============================================================================
 * MODELO: ContactoInteraccion.php
 * ============================================================================
 * Ubicación: Modules/Comercial/Models/ContactoInteraccion.php
 * 
 * Modelo para las interacciones/comunicaciones con contactos.
 * Similar a RequerimientoInteraccion pero para Contactos.
 * ============================================================================
 */

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class ContactoInteraccion extends ModelTenant implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $table = 'contacto_interaccion';

    /**
     * Campos según la tabla:
     * - id
     * - contacto_id
     * - canal (varchar 100)
     * - resumen (text)
     * - requiere_seguimiento (tinyint)
     * - proximo_seguimiento (timestamp)
     * - archivo_adjunto (text)
     * - user_id (bigint unsigned)
     * - created_at
     * - updated_at
     * - deleted_at
     */
    protected $fillable = [
        'contacto_id',
        'canal',
        'resumen',
        'requiere_seguimiento',
        'proximo_seguimiento',
        'archivo_adjunto',
        'user_id',
    ];

    protected $casts = [
        'contacto_id'          => 'integer',
        'user_id'              => 'integer',
        'requiere_seguimiento' => 'boolean',
        'proximo_seguimiento'  => 'datetime',
    ];

    /**
     * Cada vez que se crea/edita/elimina una interacción, se actualiza el
     * `updated_at` del Contacto padre. Esto hace que el contacto suba al tope
     * del listado ordenado por última actualización.
     */
    protected $touches = ['contacto'];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'proximo_seguimiento',
    ];

    /**
     * Relación con el contacto padre
     */
    public function contacto()
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }

    /**
     * Relación con el usuario que registró la interacción
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias para compatibilidad
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
