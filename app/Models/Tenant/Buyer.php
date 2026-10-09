<?php
namespace App\Models\Tenant;

use App\Models\Tenant\Catalogs\IdentityDocumentType;

class Buyer extends ModelTenant
{
    protected $fillable = [
        'name',
        'identity_document_type_id',
        'number',
        'address',
        'location_id'
    ];    

        /**
         * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
         */
        public function identity_document_type()
        {
            return $this->belongsTo(IdentityDocumentType::class, 'identity_document_type_id');
        }
}
