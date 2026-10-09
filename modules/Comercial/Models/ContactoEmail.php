<?php

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;

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
    protected $table = 'contacto_emails';

    protected $fillable = [
        'contacto_id',
        'email',
        'es_principal',
        'estado',
    ];

    protected $casts = [
        'contacto_id' => 'integer',
        'es_principal' => 'boolean',
        'estado' => 'integer',
    ];

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

    /**
     * Borrado lógico
     */
    public function inactivar()
    {
        $this->estado = 0;
        $this->updated_at = now();
        return $this->save();
    }

    /**
     * Reactivar
     */
    public function activar()
    {
        $this->estado = 1;
        $this->updated_at = now();
        return $this->save();
    }
}
