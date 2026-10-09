<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Modules\Marketing\Models\ChatbotConfig;

/**
 * ChatbotSettingsController
 *
 * Sirve la configuración pública que necesita el widget.
 *
 * GET /api/marketing/chatbot/settings
 */
class ChatbotSettingsController extends Controller
{
    public function show()
    {
        // Defaults vienen de config/marketing.php; si la tabla
        // marketing_chatbot_config tiene valores los preferimos.
        $enabled  = $this->resolveBool('general_enabled',  config('marketing.chatbot.widget.enabled', true));
        $greeting = $this->resolveString('general_greeting', config('marketing.chatbot.widget.greeting'));
        $farewell = $this->resolveString('general_farewell', config('marketing.chatbot.widget.farewell'));
        $timeout1 = $this->resolveInt('general_timeout_1', config('marketing.chatbot.timeout_1', 50));
        $timeout2 = $this->resolveInt('general_timeout_2', config('marketing.chatbot.timeout_2', 150));
        $maxMb    = $this->resolveInt('general_max_upload_mb', 5);

        return response()->json([
            'enabled'        => $enabled,
            'greeting'       => $greeting,
            'farewell'       => $farewell,
            'timeout_1'      => $timeout1,
            'timeout_2'      => $timeout2,
            'max_upload_mb'  => $maxMb,
        ]);
    }

    private function resolveBool($key, $default)
    {
        try {
            $val = ChatbotConfig::get($key);
            if ($val !== null) return (bool) $val;
        } catch (\Exception $e) {}
        return (bool) $default;
    }

    private function resolveString($key, $default)
    {
        try {
            $val = ChatbotConfig::get($key);
            if ($val !== null && $val !== '') return (string) $val;
        } catch (\Exception $e) {}
        return $default;
    }

    private function resolveInt($key, $default)
    {
        try {
            $val = ChatbotConfig::get($key);
            if ($val !== null && $val !== '') {
                $i = (int) $val;
                if ($i > 0) return $i;
            }
        } catch (\Exception $e) {}
        return (int) $default;
    }
}
