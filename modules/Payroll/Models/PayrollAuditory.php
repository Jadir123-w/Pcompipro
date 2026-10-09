<?php

namespace Modules\Payroll\Models;

use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\User;

class PayrollAuditory extends ModelTenant
{
    protected $table = 'payroll_auditory';

    public $timestamps = false;

    protected $fillable = [
        'tabla',
        'registro_id',
        'accion',
        'usuario_id',
        'usuario_nombre',
        'campo',
        'valor_anterior',
        'valor_nuevo',
        'ip',
        'user_agent',
        'fecha_hora',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];

    // Relaciones
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // Scopes
    public function scopePorTabla($query, $tabla)
    {
        return $query->where('tabla', $tabla);
    }

    public function scopePorRegistro($query, $tabla, $registroId)
    {
        return $query->where('tabla', $tabla)->where('registro_id', $registroId);
    }

    public function scopePorUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopePorFecha($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_hora', [$fechaInicio, $fechaFin]);
    }
}