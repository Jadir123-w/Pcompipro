<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * ChatbotConfig — Config key/value del agente MKT-01.
 *
 * Tabla: marketing_chatbot_config
 *
 * Helpers:
 *   ChatbotConfig::get('general_greeting')       → string|null
 *   ChatbotConfig::set('wa_enabled', true)
 *   ChatbotConfig::group('email')                → Collection ordenada por sort_order
 *   ChatbotConfig::byKey('email_imap_host')      → instancia o null
 *
 * Casts dinámicos por value_type (int, bool, json, string, text).
 */
class ChatbotConfig extends ModelTenant
{
    protected $table = 'marketing_chatbot_config';

    const TYPE_STRING = 'string';
    const TYPE_TEXT   = 'text';
    const TYPE_INT    = 'int';
    const TYPE_BOOL   = 'bool';
    const TYPE_JSON   = 'json';
    const TYPE_SELECT = 'select';

    const GROUP_GENERAL  = 'general';
    const GROUP_WHATSAPP = 'whatsapp';
    const GROUP_EMAIL    = 'email';
    const GROUP_FAQS     = 'faqs';
    const GROUP_MKT03    = 'mkt03';
    const GROUP_IA       = 'ia';

    protected $fillable = [
        'config_key', 'config_value', 'value_type', 'group',
        'display_label', 'description', 'options',
        'secret_env_var', 'is_sensitive', 'is_encrypted',
        'sort_order',
    ];

    protected $casts = [
        'is_sensitive' => 'boolean',
        'is_encrypted' => 'boolean',
        'sort_order'   => 'integer',
        'options'      => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeOfGroup($query, $group)
    {
        return $query->where('group', $group);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers de instancia
    |--------------------------------------------------------------------------
    */

    /**
     * Valor casteado al tipo declarado.
     *
     * Si is_encrypted=1, descifra el valor antes de retornarlo. El descifrado
     * usa APP_KEY del .env como master key (Laravel Crypt). Si APP_KEY cambió
     * después de cifrar, retorna null y deja warning en log.
     *
     * @return mixed
     */
    public function getTypedValue()
    {
        if ($this->config_value === null) {
            return null;
        }

        $rawValue = $this->config_value;

        // Descifrar si es necesario
        if ($this->is_encrypted) {
            try {
                $rawValue = Crypt::decryptString($this->config_value);
            } catch (\Exception $e) {
                Log::warning('ChatbotConfig: no se pudo descifrar config_value', [
                    'config_key' => $this->config_key,
                    'error'      => $e->getMessage(),
                ]);
                return null;
            }
        }

        switch ($this->value_type) {
            case self::TYPE_INT:
                return (int) $rawValue;
            case self::TYPE_BOOL:
                return in_array(strtolower((string) $rawValue), ['1', 'true', 'yes']);
            case self::TYPE_JSON:
                $decoded = json_decode($rawValue, true);
                return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
            default:
                return (string) $rawValue;
        }
    }

    /**
     * @return bool
     */
    public function isSecretMetadata()
    {
        return !empty($this->secret_env_var);
    }

    /**
     * Si esta clave es metadata de un secret, indica si la env var realmente está configurada.
     *
     * @return bool|null  null si no es secret-metadata
     */
    public function isEnvSecretConfigured()
    {
        if (!$this->isSecretMetadata()) return null;
        $val = env($this->secret_env_var);
        return !empty($val);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers estáticos
    |--------------------------------------------------------------------------
    */

    /**
     * Obtener una clave por su nombre.
     *
     * @param  string  $key
     * @return ChatbotConfig|null
     */
    public static function byKey($key)
    {
        return static::where('config_key', $key)->first();
    }

    /**
     * Obtener el valor (casteado) de una clave.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $row = static::byKey($key);
        if (!$row) return $default;
        $val = $row->getTypedValue();
        return $val === null ? $default : $val;
    }

    /**
     * Setear el valor de una clave.
     *
     * Si is_encrypted=1, cifra el valor antes de guardarlo (usa APP_KEY).
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return ChatbotConfig|null
     */
    public static function set($key, $value)
    {
        $row = static::byKey($key);
        if (!$row) return null;

        // Serializar según value_type
        switch ($row->value_type) {
            case self::TYPE_BOOL:
                $val = ($value === true || $value === '1' || $value === 1 || strtolower((string) $value) === 'true') ? '1' : '0';
                break;
            case self::TYPE_INT:
                $val = $value === null || $value === '' ? null : (string) ((int) $value);
                break;
            case self::TYPE_JSON:
                $val = is_string($value) ? $value : json_encode($value);
                break;
            default:
                $val = $value === null ? null : (string) $value;
        }

        // Cifrar si corresponde (solo si hay valor real, no null)
        if ($row->is_encrypted && $val !== null && $val !== '') {
            try {
                $val = Crypt::encryptString($val);
            } catch (\Exception $e) {
                Log::error('ChatbotConfig: no se pudo cifrar config_value', [
                    'config_key' => $key,
                    'error'      => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $row->config_value = $val;
        $row->save();
        return $row;
    }

    /**
     * Obtener todas las filas de un grupo, ordenadas.
     *
     * @param  string  $groupKey
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function group($groupKey)
    {
        return static::ofGroup($groupKey)->ordered()->get();
    }

    /**
     * Resolver un setting con prioridad BD → config('marketing.chatbot.X') → default.
     *
     * Helper central usado por los servicios del módulo (ConversationSessionManager,
     * ChatbotOrchestrator, LeadRegistrationService, WhatsAppService, etc.) para
     * leer configs operativas que viven en la tabla marketing_chatbot_config y
     * tienen un fallback al config Laravel (.env) por compatibilidad con
     * instalaciones que aún no migraron.
     *
     * Uso típico:
     *   $ttl = ChatbotConfig::getOrEnv('general_session_ttl', 'session_ttl', 1800);
     *
     * Si la tabla aún no existe (instalación fresh, antes de migrar), o si el
     * tenant no está resuelto, no falla: devuelve el valor del config Laravel
     * (que típicamente lee de .env).
     *
     * @param  string  $configKey         Clave en marketing_chatbot_config (ej: general_session_ttl)
     * @param  string  $legacyConfigPath  Sub-path en config('marketing.chatbot.X')  (ej: session_ttl)
     * @param  mixed   $default
     * @return mixed
     */
    public static function getOrEnv($configKey, $legacyConfigPath, $default = null)
    {
        try {
            $val = static::get($configKey);
            if ($val !== null && $val !== '') {
                return $val;
            }
        } catch (\Exception $e) {
            // Tabla no disponible (instalación fresh, sin tenant resuelto) — fallback silencioso
        }
        // Laravel config() devuelve null si el valor está seteado a null (no aplica el default).
        // Por eso también validamos null acá para caer al $default explícito si todo viene vacío.
        $fromEnv = config('marketing.chatbot.' . $legacyConfigPath, $default);
        if ($fromEnv === null || $fromEnv === '') {
            return $default;
        }
        return $fromEnv;
    }
}
