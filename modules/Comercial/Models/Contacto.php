<?php

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Collection;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

/**
 * @property-read Collection|ContactoEmail[]      $emails
 * @property-read Collection|ContactoTelefono[]   $telefonos
 */
class Contacto extends ModelTenant implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $table = 'contacto';

    protected $fillable = [
        'empresa_id',
        // COM-07 — ejecutiva dueña de la cuenta. NULL = pendiente de asignación.
        'responsable_comercial_id',
        'nombres',
        'apellidos',
        'dni',
        'cargo',
        'celular',
        'telefono',
        'email',
        'estado',
        'origen',
        'tipo_contacto',
        'fecha_cumpleanos',
        'direccion',
        'referencia',
        'adicionales',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'estado'     => 'integer',
    ];

    /* Relaciones */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Ejecutiva responsable de la cuenta (COM-07).
     *
     * Puede estar vacía: en ese caso la oportunidad queda pendiente de
     * asignación y se aplica el criterio de respaldo del resolutor.
     */
    public function responsableComercial()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'responsable_comercial_id');
    }

    /**
     * Emails del contacto (solo activos por defecto)
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function emails()
    {
        return $this->hasMany(ContactoEmail::class, 'contacto_id')
            ->where('estado', 1);
    }

    /**
     * Todos los emails (incluyendo inactivos)
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function emailsTodos()
    {
        return $this->hasMany(ContactoEmail::class, 'contacto_id');
    }

    /**
     * Teléfonos del contacto (solo activos por defecto)
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function telefonos()
    {
        return $this->hasMany(ContactoTelefono::class, 'contacto_id')
            ->where('estado', 1);
    }

    /**
     * Todos los teléfonos (incluyendo inactivos)
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function telefonosTodos()
    {
        return $this->hasMany(ContactoTelefono::class, 'contacto_id');
    }

    /**
     * Email principal (activo)
     */
    public function emailPrincipal()
    {
        return $this->hasOne(ContactoEmail::class, 'contacto_id')
            ->where('es_principal', 1)
            ->where('estado', 1);
    }

    /**
     * Teléfono principal (activo)
     */
    public function telefonoPrincipal()
    {
        return $this->hasOne(ContactoTelefono::class, 'contacto_id')
            ->where('es_principal', 1)
            ->where('estado', 1);
    }

    /* Helpers */
    public function getCollectionData($withDetails = false)
    {
        $data = [
            'id' => $this->id,
            'empresa_id' => $this->empresa_id,
            'razon_social' => $this->empresa->razon_social ?? null,
            'nombre_comercial' => $this->empresa->nombre_comercial ?? null,
            'empresa_nombre' => $this->empresa ? $this->empresa->nombreVisible() : null,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'dni' => $this->dni,
            'cargo' => $this->cargo,
            'celular' => $this->celular,
            'origen' => $this->origen,
            'tipo_contacto' => $this->tipo_contacto,
            'fecha_cumpleanos' => $this->fecha_cumpleanos,
            'direccion' => $this->direccion,
            'referencia' => $this->referencia,
            'adicionales' => $this->adicionales,
            'estado_name' => $this->estado ? 'Activo' : 'Inactivo',
            'estado' => $this->estado,
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
            'ultimo_usuario' => $this->ultimo_usuario ?? null,
            // Solo lectura en la tabla; se informa cuando la relación viene cargada (listado).
            'responsable_comercial' => $this->relationLoaded('responsableComercial')
                ? optional($this->responsableComercial)->name
                : null,
            
            // Backward compatibility (deprecated)
            'telefono' => $this->telefono,
            'email' => $this->email,
        ];

        if ($withDetails) {
            // Un contacto inactivo muestra sus datos suspendidos (estado 0 sin fecha de baja)
            // para que al editarlo no se pierdan.
            $activo = (int) $this->estado === 1;
            $emails = $activo ? $this->emails : $this->emailsTodos()->whereNull('deleted_at')->get();
            $telefonos = $activo ? $this->telefonos : $this->telefonosTodos()->whereNull('deleted_at')->get();

            $data['emails'] = $emails->map(function ($email) {
                return $email->getCollectionData();
            });

            $data['telefonos'] = $telefonos->map(function ($telefono) {
                return $telefono->getCollectionData();
            });
        } else {
            $data['emails_count'] = $this->emails->count();
            $data['telefonos_count'] = $this->telefonos->count();
        }

        return $data;
    }
}
