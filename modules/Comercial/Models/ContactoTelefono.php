<?php

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use Modules\Comercial\Models\Traits\EstadoDatoContacto;

/**
 * Model ContactoTelefono
 *
 * @property int $id
 * @property int $contacto_id
 * @property string $telefono
 * @property string $tipo
 * @property bool $es_principal
 * @property string|null $slot
 * @property int $estado
 */
class ContactoTelefono extends ModelTenant
{
    use EstadoDatoContacto;

    protected $table = 'contacto_telefonos';

    /**
     * Valores válidos de la columna `slot`. Identifica el rol del teléfono
     * dentro del contacto y se usa para mapear directamente a los campos del
     * form (celular principal / fijo / adicional) sin inferir por posición.
     */
    const SLOT_CELULAR_PRINCIPAL = 'celular_principal';
    const SLOT_FIJO              = 'fijo';
    const SLOT_ADICIONAL         = 'adicional';

    protected $fillable = [
        'contacto_id',
        'telefono',
        'tipo',
        'es_principal',
        'slot',
        'estado',
        'deleted_at',
    ];

    protected $casts = [
        'contacto_id' => 'integer',
        'es_principal' => 'boolean',
        'estado' => 'integer',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Al modificar un teléfono, tocar al Contacto padre.
     */
    protected $touches = ['contacto'];

    /* Relaciones */
    public function contacto()
    {
        return $this->belongsTo(Contacto::class, 'contacto_id');
    }

    /* Scopes */
    public function scopeActivo($query)
    {
        return $query->where('estado', 1);
    }

    public function scopeInactivo($query)
    {
        return $query->where('estado', 0);
    }

    public function scopePrincipal($query)
    {
        return $query->where('es_principal', 1);
    }

    /* Helpers */
    public function getCollectionData()
    {
        return [
            'id' => $this->id,
            'telefono' => $this->telefono,
            'tipo' => $this->tipo,
            'es_principal' => (bool) $this->es_principal,
            'slot' => $this->slot,
            'estado' => $this->estado,
        ];
    }
}
