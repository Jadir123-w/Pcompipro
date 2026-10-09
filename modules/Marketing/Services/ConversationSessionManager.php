<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Marketing\Models\ChatbotSession;

/**
 * ConversationSessionManager
 *
 * Gestiona las sesiones de conversación del chatbot MKT-01.
 *
 * - Historial de mensajes en Redis/Cache (volátil, con TTL)
 * - Metadata de sesión en BD (chatbot_sessions)
 * - NO persiste historial completo en BD (solo resumen al cierre)
 */
class ConversationSessionManager
{
    /** @var string */
    private $cachePrefix;

    /** @var int */
    private $sessionTtl;

    public function __construct()
    {
        $this->cachePrefix = config('marketing.chatbot.cache_prefix', 'chatbot_session:');
        // session_ttl: prioridad BD (general_session_ttl) → .env (session_ttl) → 1800 = 30min
        $this->sessionTtl = (int) \Modules\Marketing\Models\ChatbotConfig::getOrEnv(
            'general_session_ttl',
            'session_ttl',
            1800
        );
    }

    /**
     * Crear o recuperar una sesión.
     *
     * @param  string  $sessionId
     * @param  string  $channel
     * @param  array   $meta
     * @return ChatbotSession
     */
    public function getOrCreateSession($sessionId, $channel = 'web', array $meta = [])
    {
        // Buscar sesión existente activa en BD
        $session = ChatbotSession::where('session_id', $sessionId)
            ->where('status', ChatbotSession::STATUS_ACTIVE)
            ->first();

        if ($session) {
            $session->touchActivity();
            return $session;
        }

        // Crear nueva sesión
        $session = ChatbotSession::create([
            'session_id'         => $sessionId,
            'channel'            => $channel,
            'channel_identifier' => isset($meta['channel_identifier']) ? $meta['channel_identifier'] : null,
            'status'             => ChatbotSession::STATUS_ACTIVE,
            'page_url'           => isset($meta['page_url']) ? $meta['page_url'] : null,
            'utm_source'         => isset($meta['utm_source']) ? $meta['utm_source'] : null,
            'utm_medium'         => isset($meta['utm_medium']) ? $meta['utm_medium'] : null,
            'utm_campaign'       => isset($meta['utm_campaign']) ? $meta['utm_campaign'] : null,
            'started_at'         => now(),
            'last_activity_at'   => now(),
        ]);

        // Inicializar historial vacío en cache
        $this->setHistory($sessionId, []);

        Log::info('ConversationSessionManager: nueva sesión creada', [
            'session_id' => $sessionId,
            'channel'    => $channel,
        ]);

        return $session;
    }

    /**
     * Agregar un mensaje al historial en cache.
     *
     * @param  string  $sessionId
     * @param  string  $role       'user' o 'assistant'
     * @param  string  $content
     * @return void
     */
    public function addMessage($sessionId, $role, $content)
    {
        $history = $this->getHistory($sessionId);

        $history[] = [
            'role'      => $role,
            'content'   => $content,
            'timestamp' => now()->toIso8601String(),
        ];

        // Limitar historial para no exceder el contexto del LLM
        // Prioridad BD (general_max_messages_per_session) → .env → 50
        $maxMessages = (int) \Modules\Marketing\Models\ChatbotConfig::getOrEnv(
            'general_max_messages_per_session',
            'max_messages_per_session',
            50
        );
        if (count($history) > $maxMessages) {
            // Mantener los primeros 2 (apertura) y los últimos N
            $keep = $maxMessages - 2;
            $first = array_slice($history, 0, 2);
            $last = array_slice($history, -$keep);
            $history = array_merge($first, $last);
        }

        $this->setHistory($sessionId, $history);

        // Actualizar actividad en BD
        $session = ChatbotSession::where('session_id', $sessionId)->first();
        if ($session) {
            $session->touchActivity();
        }
    }

    /**
     * Obtener historial de mensajes desde cache.
     *
     * @param  string  $sessionId
     * @return array
     */
    public function getHistory($sessionId)
    {
        $key = $this->cachePrefix . $sessionId;
        $history = Cache::get($key);

        if ($history === null) {
            return [];
        }

        return is_array($history) ? $history : [];
    }

    /**
     * Obtener historial formateado para el LLM (sin timestamps).
     *
     * @param  string  $sessionId
     * @return array
     */
    public function getHistoryForLLM($sessionId)
    {
        $history = $this->getHistory($sessionId);

        $formatted = [];
        foreach ($history as $msg) {
            $formatted[] = [
                'role'    => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        return $formatted;
    }

    /**
     * Contar mensajes en la sesión.
     *
     * @param  string  $sessionId
     * @return int
     */
    public function getMessageCount($sessionId)
    {
        return count($this->getHistory($sessionId));
    }

    /**
     * Cerrar sesión.
     *
     * @param  string  $sessionId
     * @param  string  $reason
     * @param  int|null  $leadId
     * @return void
     */
    public function closeSession($sessionId, $reason = 'user_closed', $leadId = null)
    {
        // Cerrar en BD
        $session = ChatbotSession::where('session_id', $sessionId)->first();
        if ($session) {
            $session->close($reason, $leadId);
        }

        // Limpiar cache (el historial ya no se necesita)
        $this->clearHistory($sessionId);

        Log::info('ConversationSessionManager: sesión cerrada', [
            'session_id' => $sessionId,
            'reason'     => $reason,
            'lead_id'    => $leadId,
        ]);
    }

    /**
     * Verificar si la sesión sigue activa.
     *
     * @param  string  $sessionId
     * @return bool
     */
    public function isSessionActive($sessionId)
    {
        $session = ChatbotSession::where('session_id', $sessionId)
            ->where('status', ChatbotSession::STATUS_ACTIVE)
            ->first();

        return $session !== null;
    }

    /**
     * Guardar historial en cache con TTL.
     *
     * @param  string  $sessionId
     * @param  array   $history
     * @return void
     */
    private function setHistory($sessionId, array $history)
    {
        $key = $this->cachePrefix . $sessionId;
        Cache::put($key, $history, $this->sessionTtl);
    }

    /**
     * Limpiar historial del cache.
     *
     * @param  string  $sessionId
     * @return void
     */
    private function clearHistory($sessionId)
    {
        $key = $this->cachePrefix . $sessionId;
        Cache::forget($key);
    }
}
