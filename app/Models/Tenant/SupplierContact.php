<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

/**
 * Contacto de un Proveedor (tabla `supplier_contacts`).
 * Un Proveedor (persons.type='suppliers') puede tener N contactos.
 * Cada contacto tiene N teléfonos y N emails en tablas hijas
 * (supplier_contact_phones, supplier_contact_emails).
 */
class SupplierContact extends ModelTenant implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $table = 'supplier_contacts';

    protected $fillable = [
        'person_id',
        'nombres',
        'apellidos',
        'cargo',
        'cumpleanios',
        'origen',
        'estado',
        'user_id',
    ];

    protected $casts = [
        'person_id' => 'int',
        'estado' => 'int',
        'user_id' => 'int',
        'cumpleanios' => 'date',
    ];

    /**
     * Al crear/editar/eliminar un contacto, actualizar el `updated_at` del
     * Proveedor (persons) padre, así el proveedor sube al tope del listado.
     */
    protected $touches = ['supplier'];

    public function supplier()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function phones()
    {
        return $this->hasMany(SupplierContactPhone::class);
    }

    public function emails()
    {
        return $this->hasMany(SupplierContactEmail::class);
    }

    public function getNombreCompletoAttribute()
    {
        return trim(($this->nombres ?? '') . ' ' . ($this->apellidos ?? ''));
    }
}
