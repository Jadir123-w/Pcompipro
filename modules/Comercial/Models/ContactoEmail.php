<?php

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use Modules\Comercial\Models\Traits\EstadoDatoContacto;

/**
 * Model ContactoEmail
 * 
 * @property int $id
 * @property int $contacto_id
 * @property string $email
 * @property bool $es_principal
 * @property int $estado
 */
class ContactoEmail extends ModelTenant
{
    use EstadoDatoContacto;

    protected $table = 'contacto_emails';

    protected $fillable = [
        'contacto_id',
        'email',
        'es_principal',
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
     * Al modificar un email, tocar al Contacto padre.
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
            'email' => $this->email,
            'es_principal' => (bool) $this->es_principal,
            'estado' => $this->estado,
        ];
    }
}
