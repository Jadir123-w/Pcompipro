<?php
/**
 * ============================================================================
 * ARCHIVO: ProyectoBitacora.php (MODELO)
 * ============================================================================
 * Ubicación: Modules/Comercial/Models/ProyectoBitacora.php
 *
 * Apartado "Cambios e indicaciones" del proyecto (M3): comentarios editables con
 * autor y fecha/hora de registro, y autor/fecha de última edición.
 * ============================================================================
 */

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;

class ProyectoBitacora extends ModelTenant
{
    protected $table = 'proyecto_bitacora';

    protected $fillable = [
        'proyecto_id',
        'contenido',
        'user_id',
        'edited_by',
        'edited_at',
    ];

    protected $casts = [
        'proyecto_id' => 'integer',
        'user_id'     => 'integer',
        'edited_by'   => 'integer',
        'edited_at'   => 'datetime',
    ];

    /* ========== RELACIONES ========== */

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function autor()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'user_id');
    }

    public function editor()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'edited_by');
    }
}
