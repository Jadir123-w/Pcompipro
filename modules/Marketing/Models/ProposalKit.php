<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * ProposalKit — Kit/paquete comercial de la propuesta (mockup db).
 *
 * Agrupa ítems de la pre-cotización en un paquete con precio consolidado.
 * Vive en el borrador 1:1 de la propuesta; se guarda con su propio botón y
 * se borra de BD al disolver.
 */
class ProposalKit extends ModelTenant
{
    protected $table = 'quotation_proposal_kits';

    protected $fillable = [
        'proposal_id',
        'nombre',
        'descripcion',
        'presentacion',
        'imagen',
        'cantidad',
        'precio_kit',
    ];

    protected $casts = [
        'proposal_id' => 'integer',
        'cantidad'    => 'integer',
        'precio_kit'  => 'decimal:2',
    ];

    public function proposal()
    {
        return $this->belongsTo(QuotationProposal::class, 'proposal_id');
    }

    public function members()
    {
        return $this->hasMany(ProposalKitItem::class, 'kit_id');
    }
}
