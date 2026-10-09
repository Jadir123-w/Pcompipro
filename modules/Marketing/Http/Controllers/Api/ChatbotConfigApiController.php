<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\ChatbotConfig;

/**
 * ChatbotConfigApiController — Settings del agente MKT-01.
 *
 * Endpoints:
 *   GET  /marketing/chatbot/settings/config           todas las claves agrupadas
 *   GET  /marketing/chatbot/settings/config/{group}   solo las de un grupo (general|whatsapp|email|faqs)
 *   PUT  /marketing/chatbot/settings/config/{key}     actualizar una clave
 *   POST /marketing/chatbot/settings/config/test-imap probar conexión IMAP con la config actual (futuro)
 *
 * Las claves marcadas como secret_env_var (passwords, tokens) se enmascaran:
 * la UI muestra "configurado / faltante" sin exponer el valor real.
 */
class ChatbotConfigApiController extends Controller
{
    /**
     * GET /marketing/chatbot/settings/config
     */
    public function index()
    {
        $rows = ChatbotConfig::ordered()->get();

        // Enriquecer cada fila con información útil para la UI
        $rows->each(function ($r) {
            // Si NO es sensitive, exponer el valor casteado para que la UI lo muestre
            if (!$r->is_sensitive) {
                $r->typed_value = $r->getTypedValue();
            } else {
                // Sensitive: ocultar el valor real pero indicar si está configurado
                $hasValue = !empty($r->config_value);
                $r->config_value = null;
                $r->typed_value  = null;
                $r->is_set       = $hasValue; // bool: la UI muestra "Configurado ●●●●●●" o "No configurado"
            }
            $r->env_secret_set = $r->isEnvSecretConfigured(); // null|bool (legacy)
        });

        // Agrupar por group para que la UI lo arme directo
        $grouped = $rows->groupBy('group');

        return response()->json([
            'ok'      => true,
            'groups'  => $grouped,
            'count'   => $rows->count(),
        ]);
    }

    /**
     * GET /marketing/chatbot/settings/config/{group}
     */
    public function showGroup($group)
    {
        $rows = ChatbotConfig::ofGroup($group)->ordered()->get();
        $rows->each(function ($r) {
            if (!$r->is_sensitive) {
                $r->typed_value = $r->getTypedValue();
            } else {
                $hasValue = !empty($r->config_value);
                $r->config_value = null;
                $r->typed_value  = null;
                $r->is_set       = $hasValue;
            }
            $r->env_secret_set = $r->isEnvSecretConfigured();
        });
        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    /**
     * PUT /marketing/chatbot/settings/config/{key}
     *
     * Body: { config_value: <value> }
     *
     * Si la clave es is_sensitive, rechaza el update — los secrets viven en .env.
     */
    public function update(Request $request, $key)
    {
        $row = ChatbotConfig::byKey($key);
        if (!$row) {
            return response()->json(['ok' => false, 'message' => "Clave no existe: {$key}"], 404);
        }

        $v = Validator::make($request->all(), [
            'config_value' => 'present',
        ]);
        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        try {
            $newRow = ChatbotConfig::set($key, $request->input('config_value'));

            // Para sensitive: nunca devolver el valor en la response
            if ($newRow->is_sensitive) {
                $hasValue = !empty($newRow->config_value);
                $newRow->config_value = null;
                $newRow->typed_value  = null;
                $newRow->is_set       = $hasValue;
            } else {
                $newRow->typed_value = $newRow->getTypedValue();
            }
            $newRow->env_secret_set = $newRow->isEnvSecretConfigured();

            return response()->json(['ok' => true, 'row' => $newRow]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => 'Error guardando: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /marketing/chatbot/settings/config/bulk
     *
     * Body: { items: [ {key, value}, {key, value}, ... ] }
     *
     * Permite actualizar varias claves en un solo request (más eficiente para
     * el botón "Guardar" de un tab que toca varios campos).
     */
    public function bulkUpdate(Request $request)
    {
        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            return response()->json(['ok' => false, 'message' => 'items vacío o inválido'], 422);
        }

        $results = ['updated' => [], 'rejected' => []];
        foreach ($items as $item) {
            if (!isset($item['key']) || !array_key_exists('value', $item)) {
                $results['rejected'][] = ['item' => $item, 'reason' => 'falta key/value'];
                continue;
            }
            $row = ChatbotConfig::byKey($item['key']);
            if (!$row) {
                $results['rejected'][] = ['key' => $item['key'], 'reason' => 'no existe'];
                continue;
            }
            // Sensitive en bulk: si el value es null o "" lo saltamos (no queremos
            // borrar accidentalmente un secret al guardar todo el form)
            if ($row->is_sensitive && ($item['value'] === null || $item['value'] === '')) {
                continue; // skip silently — el usuario no tocó este campo en el form
            }
            try {
                ChatbotConfig::set($item['key'], $item['value']);
                $results['updated'][] = $item['key'];
            } catch (\Exception $e) {
                $results['rejected'][] = ['key' => $item['key'], 'reason' => $e->getMessage()];
            }
        }

        return response()->json([
            'ok'      => true,
            'updated' => $results['updated'],
            'rejected'=> $results['rejected'],
        ]);
    }
}
