<?php

namespace Modules\Payroll\Traits;

use Modules\Payroll\Models\PayrollAuditory;
use Illuminate\Support\Facades\Auth;

trait PayrollAuditable
{
    protected static function bootPayrollAuditable()
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        // En el evento deleting, guardamos temporalmente el ID y registramos la auditoría
        // ANTES de borrar el registro para que podamos acceder a los datos
        static::deleting(function ($model) {
            if (Auth::check()) {
                // Guardamos en una propiedad temporal para usarla después
                $model->_temp_deleting_user_id = Auth::id();
                $model->deleted_by = Auth::id();
            }
            
            // Registrar auditoría ANTES de eliminar
            $model->registrarAuditoria('DELETE');
        });

        static::created(function ($model) {
            $model->registrarAuditoria('CREATE');
        });

        static::updated(function ($model) {
            $model->registrarAuditoria('UPDATE');
        });
    }

    protected function registrarAuditoria($accion)
    {
        $usuario = Auth::user();
        $tabla = $this->getTable();
        $registroId = $this->id;

        if ($accion === 'CREATE') {
            PayrollAuditory::create([
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'accion' => $accion,
                'usuario_id' => $usuario->id ?? null,
                'usuario_nombre' => $usuario->name ?? 'Sistema',
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } elseif ($accion === 'UPDATE') {
            $cambios = $this->getDirty();
            $original = $this->getOriginal();

            foreach ($cambios as $campo => $valorNuevo) {
                if (in_array($campo, ['updated_at', 'updated_by'])) {
                    continue;
                }

                $valorAnterior = $original[$campo] ?? null;

                PayrollAuditory::create([
                    'tabla' => $tabla,
                    'registro_id' => $registroId,
                    'accion' => $accion,
                    'usuario_id' => $usuario->id ?? null,
                    'usuario_nombre' => $usuario->name ?? 'Sistema',
                    'campo' => $campo,
                    'valor_anterior' => $valorAnterior,
                    'valor_nuevo' => $valorNuevo,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        } elseif ($accion === 'DELETE') {
            PayrollAuditory::create([
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'accion' => $accion,
                'usuario_id' => $usuario->id ?? null,
                'usuario_nombre' => $usuario->name ?? 'Sistema',
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
    }

    public function auditorias()
    {
        return $this->morphMany(PayrollAuditory::class, 'auditable');
    }
}