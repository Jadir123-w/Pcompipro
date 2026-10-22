<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * ProposalKitItem — Enlace kit ↔ ítem de pre-cotización (mockup db).
 *
 * Tabla sin id propio (PK compuesta): solo lectura vía modelo, las
 * escrituras se hacen con query builder desde el controlador.
 */
class ProposalKitItem extends ModelTenant
{
    public $incrementing = false;

    protected $primaryKey = null;

    protected $table = 'quotation_proposal_kit_items';

    protected $fillable = [
        'kit_id',
        'prequote_item_id',
        'cantidad',
    ];

    protected $casts = [
        'kit_id'           => 'integer',
        'prequote_item_id' => 'integer',
        'cantidad'         => 'decimal:2',
    ];

    public function kit()
    {
        return $this->belongsTo(ProposalKit::class, 'kit_id');
    }

    public function prequoteItem()
    {
        return $this->belongsTo(PreQuoteItem::class, 'prequote_item_id');
    }
}
