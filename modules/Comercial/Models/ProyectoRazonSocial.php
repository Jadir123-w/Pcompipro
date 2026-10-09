<?php
/**
 * ============================================================================
 * ARCHIVO: ProyectoRazonSocial.php (MODELO)
 * ============================================================================
 * Ubicación: Modules/Comercial/Models/ProyectoRazonSocial.php
 * 
 * Modelo para las razones sociales de facturación de un proyecto.
 * Útil cuando se factura a múltiples razones sociales.
 * ============================================================================
 */

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class ProyectoRazonSocial extends ModelTenant implements Auditable
{
    use AuditableTrait;

    protected $table = 'proyecto_razones_sociales';

    protected $fillable = [
        'proyecto_id',
        'razon_social',
        'ruc',
        'monto_facturar',
        'tiene_oc',
        'oc_numero',
        'oc_archivo',
        'oc_archivo_original',
        'observaciones',
    ];

    protected $casts = [
        'proyecto_id'    => 'integer',
        'monto_facturar' => 'decimal:2',
        // tiene_oc ahora es string: 'Pendiente' | 'No Aplica' | 'Se tiene'
    ];

    /* ========== RELACIONES ========== */

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    /* ========== ACCESSORS ========== */

    public function getMontoFacturarFormatAttribute()
    {
        return $this->monto_facturar ? 'S/ ' . number_format($this->monto_facturar, 2) : null;
    }

    public function getTieneOcTextAttribute()
    {
        return $this->tiene_oc ?: 'Pendiente';
    }
}
