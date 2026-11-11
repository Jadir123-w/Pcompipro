<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proveedor de IA
    |--------------------------------------------------------------------------
    |
    | Motor de inteligencia artificial para las conversaciones.
    | Valores soportados: "claude", "openai", "gemini"
    |
    */
    'ai_provider' => env('CHATBOT_AI_PROVIDER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | Configuración del proveedor de IA
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'claude' => [
            'api_key'    => env('CHATBOT_CLAUDE_API_KEY'),
            'model'      => env('CHATBOT_CLAUDE_MODEL', 'claude-sonnet-4-6'),
            'base_url'   => 'https://api.anthropic.com/v1/messages',
            'max_tokens' => 1024,
        ],
        'openai' => [
            'api_key'    => env('CHATBOT_OPENAI_API_KEY'),
            'model'      => env('CHATBOT_OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url'   => 'https://api.openai.com/v1/chat/completions',
            'max_tokens' => 1024,
        ],
        'gemini' => [
            'api_key'    => env('CHATBOT_GEMINI_API_KEY'),
            'model'      => env('CHATBOT_GEMINI_MODEL', 'gemini-3.6-flash'),
            'base_url'   => env('CHATBOT_GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/models'),
            'max_tokens' => (int) env('CHATBOT_GEMINI_MAX_TOKENS', 1024),
        ],
        'cloudflare' => [
            'api_key'    => env('CLOUDFLARE_API_TOKEN'),
            'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
            'model'      => env('CLOUDFLARE_IMAGE_MODEL', '@cf/black-forest-labs/flux-1-schnell'),
            'base_url'   => env('CLOUDFLARE_AI_BASE_URL', 'https://api.cloudflare.com/client/v4/accounts'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeouts de inactividad (segundos)
    |--------------------------------------------------------------------------
    |
    | timeout_1: primer reenganche suave (45-60s según playbook)
    | timeout_2: segundo reenganche y cierre (120-180s según playbook)
    | session_ttl: tiempo máximo de sesión en cache (segundos)
    |
    */
    'timeout_1'   => env('CHATBOT_TIMEOUT_1', 50),
    'timeout_2'   => env('CHATBOT_TIMEOUT_2', 150),
    'session_ttl' => env('CHATBOT_SESSION_TTL', 1800), // 30 minutos

    /*
    |--------------------------------------------------------------------------
    | Límites de conversación
    |--------------------------------------------------------------------------
    */
    'max_messages_per_session' => env('CHATBOT_MAX_MESSAGES', 50),
    'max_requests_per_minute'  => env('CHATBOT_RATE_LIMIT', 20),

    /*
    |--------------------------------------------------------------------------
    | Control anti-saturación WhatsApp
    |--------------------------------------------------------------------------
    |
    | Máximo de mensajes consecutivos del bot sin respuesta del usuario.
    |
    */
    'whatsapp_max_consecutive' => env('CHATBOT_WA_MAX_CONSECUTIVE', 2),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Driver para almacenar historial de conversación en memoria.
    | Recomendado: redis. Alternativa: file, database.
    |
    */
    'cache_driver' => env('CHATBOT_CACHE_DRIVER', 'redis'),
    'cache_prefix' => 'chatbot_session:',

    /*
    |--------------------------------------------------------------------------
    | Widget
    |--------------------------------------------------------------------------
    */
    'widget' => [
        'enabled'  => env('CHATBOT_ENABLED', true),
        'greeting' => 'Hola, soy el Asistente Virtual de COMPIPRO. Te ayudo con tu consulta y, si hace falta, te derivo con una ejecutiva. ¿Qué estás buscando para tu empresa?',
        'farewell' => 'Gracias por escribirnos. ¡Que tengas un excelente día!',
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API (POR VALIDAR)
    |--------------------------------------------------------------------------
    */
    'whatsapp' => [
        'enabled'         => env('CHATBOT_WA_ENABLED', false),
        'token'           => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'verify_token'    => env('WHATSAPP_VERIFY_TOKEN'),
        'api_version'     => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Asignación por defecto
    |--------------------------------------------------------------------------
    |
    | ID de la ejecutiva comercial por defecto para leads nuevos.
    | null = sin asignación automática.
    |
    */
    'default_assigned_to' => env('CHATBOT_DEFAULT_ASSIGNED_TO', null),

    /*
    |--------------------------------------------------------------------------
    | MKT-03: Social Media Content Agent
    |--------------------------------------------------------------------------
    |
    | Configuración del tercer agente del módulo Marketing.
    | Reutiliza la misma cuenta de OpenAI para generación de texto e imágenes.
    |
    */
    'mkt03' => [
        // ── Generación de imágenes ──
        // Providers: 'dalle3' | 'gpt-image-2' | 'nano-banana' | 'cloudflare' (Flux 1 Schnell)
        'image_provider'      => env('MKT03_IMAGE_PROVIDER', 'dalle3'),
        'image_model'         => env('MKT03_IMAGE_MODEL'),

        // Quality (solo aplica a gpt-image-2): low | medium | high | auto
        // Cada nivel cambia el costo significativamente (low ≈ $0.006, medium ≈ $0.053, high ≈ $0.211 por 1024×1024)
        'image_quality'       => env('MKT03_IMAGE_QUALITY', 'medium'),

        // Format de salida (solo aplica a gpt-image-2): png | jpeg | webp
        'image_format'        => env('MKT03_IMAGE_FORMAT', 'png'),

        'image_storage_disk'  => env('MKT03_IMAGE_STORAGE_DISK', 'public'),
        'image_storage_path'  => env('MKT03_IMAGE_STORAGE_PATH', 'mkt03/pieces'),

        // ── Habilitar módulo ──
        'enabled' => env('MKT03_ENABLED', true),

        // ── Generación automática de imágenes al producir piezas ──
        // true = al producir pieza se genera imagen automáticamente (consume créditos)
        // false = solo se genera copy + prompt; la imagen se genera on-demand desde la UI
        'auto_generate_image' => env('MKT03_AUTO_GENERATE_IMAGE', false),
    ],

];
