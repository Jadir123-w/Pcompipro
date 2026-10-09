<?php

namespace App\Models\Tenant;

/**
 * Teléfono de un contacto de proveedor.
 */
class SupplierContactPhone extends ModelTenant
{
    protected $table = 'supplier_contact_phones';

    protected $fillable = [
        'supplier_contact_id',
        'telefono',
        'tipo',
        'es_principal',
        'estado',
    ];

    protected $casts = [
        'supplier_contact_id' => 'int',
        'es_principal' => 'int',
        'estado' => 'int',
    ];

    /**
     * Al modificar el teléfono, tocar al SupplierContact, que a su vez
     * (por su propio $touches) actualiza al Person (proveedor).
     */
    protected $touches = ['contact'];

    public function contact()
    {
        return $this->belongsTo(SupplierContact::class, 'supplier_contact_id');
    }
}
