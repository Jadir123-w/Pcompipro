<?php

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\Person;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

/**
 * Evaluación de un proveedor en el contexto de un proyecto (post-venta).
 *
 * Un proyecto puede tener N evaluaciones (una por proveedor relevante).
 * Cada evaluación lleva estrellas (1..5), una etiqueta textual opcional
 * (Bueno/Regular/Malo) y un comentario libre.
 */
class ProyectoEvaluacionProveedor extends ModelTenant implements Auditable
{
    use AuditableTrait;

    protected $table = 'proyecto_evaluacion_proveedor';

    protected $fillable = [
        'proyecto_id',
        'proveedor_id',
        'proveedor_nombre',
        'estrellas',
        'calificacion_label',
        'comentario',
        'user_id',
    ];

    protected $casts = [
        'proyecto_id' => 'integer',
        'proveedor_id' => 'integer',
        'estrellas' => 'integer',
        'user_id' => 'integer',
    ];

    /**
     * Al crear/editar/eliminar una evaluación, actualizar el `updated_at`
     * del Proyecto padre. Así el proyecto sube al tope del listado.
     */
    protected $touches = ['proyecto'];

    /* ========== RELACIONES ========== */

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Person::class, 'proveedor_id');
    }

    /* ========== ACCESSORS ========== */

    /**
     * Devuelve la etiqueta textual (Bueno/Regular/Malo) basada en las estrellas
     * cuando no se haya guardado una etiqueta explícita.
     */
    public function getCalificacionTextoAttribute()
    {
        if (!empty($this->calificacion_label)) {
            return $this->calificacion_label;
        }
        if ($this->estrellas === null) return null;
        if ($this->estrellas >= 4) return 'Bueno';
        if ($this->estrellas >= 2) return 'Regular';
        return 'Malo';
    }
}
