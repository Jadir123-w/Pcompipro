<?php
namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\Model;

class LogConversionLead extends ModelTenant
{
    protected $table = 'log_conversion_lead';
    public $timestamps = false;

    protected $fillable = [
        'lead_id','usuario_id','fecha_conversion',
        'empresa_final_id','contacto_final_id',
        'tipo_conflicto','accion_tomada','comentario_usuario'
    ];

    protected static function boot()
    {
      parent::boot();
    }
}
