<?php
// Modules/Comercial/Models/Empresa.php

namespace Modules\Comercial\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Auditable as AuditableTrait;

class Empresa extends ModelTenant implements Auditable
{
    use SoftDeletes, AuditableTrait;

    protected $table = 'empresa';

    protected $fillable = [
        'ruc','razon_social','nombre_comercial',
        'rubro','telefono','celular','email',
        'n_trabajadores','aniversario','website',
        'tipo_empresa','ciudad','distrito','pais',
        'direccion','referencia', 'origen',
        'politica_pago','trabaja_con_proveedores',
        'procedimiento_especial','frecuencia_compra','adicionales',
        'estado_contribuyente','condicion_contribuyente',
        'estado',
        'department_id', 'province_id', 'district_id',
    ];

    protected $casts = [
        'trabaja_con_proveedores' => 'boolean',
        'aniversario' => 'date',
        'estado' => 'integer',
        'n_trabajadores' => 'integer',
    ];

    protected static function boot()
    {
      parent::boot();
    }

    public function contactos()
    {
        return $this->hasMany(Contacto::class, 'empresa_id');
    }
}
