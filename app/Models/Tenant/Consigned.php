<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Catalogs\IdentityDocumentType;
use App\Models\Tenant\ModelTenant;

class Consigned extends ModelTenant
{
    protected $fillable = [
        'identity_document_type_id',
        'number',
        'name',
        'telephone',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function identity_document_type()
    {
        return $this->belongsTo(IdentityDocumentType::class, 'identity_document_type_id');
    }

}
