<?php
/**
 * ============================================================================
 * ARCHIVO 3: CotizacionRequerimiento.php (MODELO)
 * ============================================================================
 * Ubicación: Modules/Comercial/Models/CotizacionRequerimiento.php
 * 
 * CAMBIOS REALIZADOS:
 * - Removido SoftDeletes porque la tabla NO tiene columna deleted_at
 * - Los campos fillable ya estaban correctos
 * ============================================================================
 */

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\User;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class CotizacionRequerimiento extends ModelTenant implements Auditable
{
    use AuditableTrait;

    // ⚠️ NO usar SoftDeletes - la tabla NO tiene deleted_at

    protected $table = 'cotizaciones_requerimiento';

    /**
     * CAMPOS SEGÚN LA TABLA REAL (imagen cotizaciones_requerimiento.png):
     * - id
     * - requerimiento_id
     * - version (varchar 10)
     * - archivo_url (text)
     * - monto (decimal 12,2)
     * - estado (enum: Enviada, Recotizada, Aprobada, Caida, No cotizado)
     * - motivo_caida (text)
     * - observaciones (text)
     * - usuario_id (bigint unsigned)
     * - created_at
     * - updated_at
     * - ⚠️ NO tiene deleted_at
     */
    protected $fillable = [
        'requerimiento_id',
        'version',
        'archivo_url',
        'monto',
        'estado',
        'motivo_caida',
        'observaciones',
        'usuario_id',
    ];

    protected $casts = [
        'requerimiento_id' => 'integer',
        'version'          => 'integer',
        'monto'            => 'decimal:2',
        'usuario_id'       => 'integer',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * Valor por defecto del estado
     */
    protected $attributes = [
        'estado' => 'Enviada',
        'monto'  => 0.00,
    ];

    /**
     * Relación con el requerimiento padre
     */
    public function requerimiento()
    {
        return $this->belongsTo(Requerimiento::class, 'requerimiento_id');
    }

    /**
     * Relación con el usuario que subió la cotización
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Alias para compatibilidad
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Scope para cotizaciones aprobadas
     */
    public function scopeAprobadas($query)
    {
        return $query->where('estado', 'Aprobada');
    }

    /**
     * Scope para cotizaciones pendientes
     */
    public function scopePendientes($query)
    {
        return $query->whereIn('estado', ['Enviada', 'Recotizada']);
    }
}
