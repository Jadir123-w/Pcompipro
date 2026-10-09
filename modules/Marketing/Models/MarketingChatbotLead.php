<?php

namespace Modules\Marketing\Models;

use App\Models\Tenant\ModelTenant;

class MarketingChatbotLead extends ModelTenant
{
    protected $table = 'marketing_chatbot_leads';

    protected $fillable = [
        // ── Datos de contacto (existentes) ──
        'name',
        'email',
        'phone',
        'company_name',
        'source',
        'status',
        'meta',

        // ── Campos nuevos: inteligencia conversacional ──
        'intent_detected',
        'conversation_summary',
        'structured_data',
        'tags',

        // ── Canal y asignación ──
        'channel',
        'assigned_to',

        // ── Estado de madurez ──
        'ready_to_convert',
        'urgency_level',

        // ── Referencia a sesión ──
        'session_id',
    ];

    protected $casts = [
        'meta'             => 'array',
        'structured_data'  => 'array',
        'tags'             => 'array',
        'ready_to_convert' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Intenciones válidas (lista cerrada del Documento Funcional §6.1)
    |--------------------------------------------------------------------------
    */
    const VALID_INTENTS = [
        'saludo',
        'consulta_general',
        'solicitar_catalogo',
        'registrar_para_cotizacion',
        'solicitar_muestra_virtual',
        'lead_de_feria',
        'postventa_simple',
        'reclamo_o_urgente',
        'pedir_humano',
        'no_relacionado',
        'no_entendido',
    ];

    /*
    |--------------------------------------------------------------------------
    | Estados del lead (Documento Funcional §13)
    |--------------------------------------------------------------------------
    */
    const STATUS_NEW                  = 'nuevo';
    const STATUS_IN_PROGRESS          = 'en_gestion';
    const STATUS_ASSIGNED             = 'derivado_a_ejecutiva';
    const STATUS_ESCALATED            = 'escalado_urgente';
    const STATUS_CONVERTED            = 'convertido_a_requerimiento';
    const STATUS_CLOSED_NO_INTEREST   = 'cerrado_sin_interes';

    const VALID_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_IN_PROGRESS,
        self::STATUS_ASSIGNED,
        self::STATUS_ESCALATED,
        self::STATUS_CONVERTED,
        self::STATUS_CLOSED_NO_INTEREST,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    /**
     * Sesión de chat que originó este lead.
     */
    public function session()
    {
        return $this->belongsTo(ChatbotSession::class, 'session_id', 'session_id');
    }

    /**
     * Ejecutiva asignada.
     */
    public function assignedUser()
    {
        return $this->belongsTo(\App\Models\Tenant\User::class, 'assigned_to');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeReadyToConvert($query)
    {
        return $query->where('ready_to_convert', true);
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeByIntent($query, $intent)
    {
        return $query->where('intent_detected', $intent);
    }

    public function scopeUrgent($query)
    {
        return $query->where('urgency_level', 'high');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [
            self::STATUS_NEW,
            self::STATUS_IN_PROGRESS,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * ¿Tiene contacto válido? (correo o celular)
     *
     * @return bool
     */
    public function hasValidContact()
    {
        return !empty($this->email) || !empty($this->phone);
    }

    /**
     * ¿Tiene datos comerciales útiles?
     *
     * @return bool
     */
    public function hasUsefulData()
    {
        $data = is_array($this->structured_data) ? $this->structured_data : [];

        $items = isset($data['items_solicitados']) ? $data['items_solicitados'] : [];
        $fecha = isset($data['fecha_objetivo']) ? $data['fecha_objetivo'] : null;
        $ciudad = isset($data['ciudad_entrega']) ? $data['ciudad_entrega'] : null;

        return !empty($items)
            || !empty($this->company_name)
            || !empty($fecha)
            || !empty($ciudad);
    }

    /**
     * ¿Cumple los 3 criterios para ser un lead registrable?
     * 1. Contacto válido
     * 2. Intención comercial clara
     * 3. Al menos un dato útil
     *
     * @return bool
     */
    public function meetsRegistrationCriteria()
    {
        $commercialIntents = [
            'solicitar_catalogo',
            'registrar_para_cotizacion',
            'solicitar_muestra_virtual',
            'lead_de_feria',
        ];

        return $this->hasValidContact()
            && in_array($this->intent_detected, $commercialIntents)
            && $this->hasUsefulData();
    }
}
