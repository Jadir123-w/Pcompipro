<?php
// Modules/Comercial/Models/Lead.php

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Lead extends ModelTenant implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $table = 'lead';

    protected $fillable = [
        'empresa_id','origen','etapa','descripcion','contacto','telefono','email',
        'prioridad','monto_estimado','fecha_estimada','estado',
        // nuevos:
        'ruc','razon_social','asunto_email','adjunto_path',
        'empresa_detectada_id','contacto_detectado_id',
        'conflicto_detectado','estado_revision','procesado',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'monto_estimado' => 'decimal:2',
        'fecha_estimada' => 'date',
        'empresa_detectada_id' => 'integer',
        'contacto_detectado_id' => 'integer',
        'conflicto_detectado' => 'boolean',
        'procesado' => 'boolean',
    ];

    public const ESTADOS_REVISION_LEAD =[
      'pendiente' => 'Pendiente',
      'en_revision' => 'En Revision',
      'convertido' => 'Convertido',
      'coincidencia_total' => 'Coincidencia Total',
      'conflicto' => 'Conflicto',
    ];

    protected static function boot()
    {
      parent::boot();
    }

    public function empresaDetectada()  {
      return $this->belongsTo(\Modules\Comercial\Models\Empresa::class, 'empresa_detectada_id');
    }
      // (si tienes modelo Contacto)
    public function contactoDetectado() {
      return $this->belongsTo(\Modules\Comercial\Models\Contacto::class, 'contacto_detectado_id');
    }

    /* Relaciones */
    public function empresa()
    {
        // Ajusta el FQN si tu modelo Empresa vive en otro namespace
        return $this->belongsTo(\Modules\Comercial\Models\Empresa::class, 'empresa_id');
    }
}
