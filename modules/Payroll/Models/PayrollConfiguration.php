<?php

namespace Modules\Payroll\Models;

use App\Models\Tenant\ModelTenant;
use Modules\Payroll\Traits\PayrollAuditable;
use App\Models\Tenant\User;

class PayrollConfiguration extends ModelTenant
{
    use PayrollAuditable;

    protected $table = 'payroll_configurations';

    protected $fillable = [
        'clave',
        'nombre',
        'valor',
        'tipo',
        'descripcion',
        'anio',
        'created_by',
        'updated_by',
    ];

    // Relaciones
    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Métodos estáticos
    public static function obtenerValor($clave, $anio = null)
    {
        $anio = $anio ?? date('Y');
        
        $config = self::where('clave', $clave)
            ->where('anio', $anio)
            ->first();

        if (!$config) {
            return null;
        }

        switch ($config->tipo) {
            case 'decimal':
                return (float) $config->valor;
            case 'entero':
                return (int) $config->valor;
            case 'boolean':
                return filter_var($config->valor, FILTER_VALIDATE_BOOLEAN);
            default:
                return $config->valor;
        }
    }

    public static function actualizarValor($clave, $valor, $anio = null)
    {
        $anio = $anio ?? date('Y');
        
        return self::where('clave', $clave)
            ->where('anio', $anio)
            ->update(['valor' => $valor]);
    }

    // Métodos de configuración específicos
    public static function obtenerRMV()
    {
        return self::obtenerValor('rmv');
    }

    public static function obtenerUIT()
    {
        return self::obtenerValor('uit');
    }

    public static function obtenerTasaEsSalud()
    {
        return self::obtenerValor('porcentaje_essalud') ?? 9;
    }

    public static function obtenerTasaONP()
    {
        return self::obtenerValor('onp');
    }

    public static function obtenerAsignacionFamiliar()
    {
        $rmv = self::obtenerRMV();
        $porcentaje = self::obtenerValor('asignacion_familiar_porcentaje');
        return ($rmv * $porcentaje) / 100;
    }
}