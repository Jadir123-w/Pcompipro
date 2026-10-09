<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

/**
 * QuotationConfig — Configuración paramétrica del cotizador (COM-02)
 *
 * Almacena parámetros de negocio editables sin cambiar código:
 * márgenes default, IGV, colores, lados, técnica, empaque, tiempo, etc.
 */
class QuotationConfig extends ModelTenant
{
    protected $table = 'quotation_config';

    protected $fillable = [
        'config_key',
        'config_value',
        'config_type',
        'description',
    ];

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener un valor de configuración por clave.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public static function getValue($key, $default = null)
    {
        $config = static::where('config_key', $key)->first();

        if (!$config) {
            return $default;
        }

        return self::castValue($config->config_value, $config->config_type);
    }

    /**
     * Establecer un valor de configuración.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public static function setValue($key, $value)
    {
        $config = static::where('config_key', $key)->first();

        if ($config) {
            $config->update(['config_value' => (string) $value]);
        }
    }

    /**
     * Obtener toda la configuración como array key => value.
     *
     * @return array
     */
    public static function getAllAsArray()
    {
        $configs = static::all();
        $result = [];

        foreach ($configs as $config) {
            $result[$config->config_key] = self::castValue($config->config_value, $config->config_type);
        }

        return $result;
    }

    /**
     * Castear el valor según su tipo.
     *
     * @param  string  $value
     * @param  string  $type
     * @return mixed
     */
    private static function castValue($value, $type)
    {
        switch ($type) {
            case 'integer':
                return (int) $value;
            case 'decimal':
                return (float) $value;
            case 'boolean':
                return (bool) $value;
            case 'json':
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            default:
                return $value;
        }
    }
}
