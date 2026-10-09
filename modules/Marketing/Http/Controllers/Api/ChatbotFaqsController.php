<?php

namespace Modules\Marketing\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Marketing\Models\ChatbotFaq;

/**
 * ChatbotFaqsController
 *
 * CRUD de FAQs del chatbot MKT-01 desde el panel de administración del ERP.
 * Rutas protegidas con autenticación.
 *
 * Endpoints:
 *   GET    /marketing/chatbot/faqs            listado completo (incluye inactivas)
 *   POST   /marketing/chatbot/faqs            crear
 *   PUT    /marketing/chatbot/faqs/{id}       actualizar
 *   DELETE /marketing/chatbot/faqs/{id}       eliminar
 *   GET    /marketing/chatbot/faqs/categories listado de categorías existentes
 */
class ChatbotFaqsController extends Controller
{
    /**
     * Listar FAQs.
     */
    public function index(Request $request)
    {
        $q = ChatbotFaq::query();

        // Filtro opcional por categoría
        $cat = $request->input('category');
        if (!empty($cat)) {
            $q->where('category', $cat);
        }

        // Filtro opcional por activo
        if ($request->has('active')) {
            $active = filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN);
            $q->where('active', $active);
        }

        $faqs = $q->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'ok'   => true,
            'faqs' => $faqs,
        ]);
    }

    /**
     * Crear FAQ.
     */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'question'   => ['required', 'string', 'max:500'],
            'answer'     => ['required', 'string'],
            'keywords'   => ['nullable', 'string', 'max:500'],
            'category'   => ['nullable', 'string', 'max:80'],
            'active'     => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $faq = ChatbotFaq::create([
            'question'   => trim($request->input('question')),
            'answer'     => trim($request->input('answer')),
            'keywords'   => $request->input('keywords') !== null ? trim($request->input('keywords')) : null,
            'category'   => $request->input('category') !== null ? trim($request->input('category')) : null,
            'active'     => $request->input('active', true),
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return response()->json(['ok' => true, 'faq' => $faq], 201);
    }

    /**
     * Actualizar FAQ.
     */
    public function update(Request $request, $id)
    {
        $faq = ChatbotFaq::findOrFail($id);

        $v = Validator::make($request->all(), [
            'question'   => ['sometimes', 'string', 'max:500'],
            'answer'     => ['sometimes', 'string'],
            'keywords'   => ['sometimes', 'nullable', 'string', 'max:500'],
            'category'   => ['sometimes', 'nullable', 'string', 'max:80'],
            'active'     => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $payload = [];
        foreach (['question', 'answer', 'keywords', 'category', 'active', 'sort_order'] as $field) {
            if ($request->has($field)) {
                $val = $request->input($field);
                if (in_array($field, ['question', 'answer', 'keywords', 'category']) && is_string($val)) {
                    $val = trim($val);
                    // Para keywords/category: '' → null para mantener la columna limpia
                    if (in_array($field, ['keywords', 'category']) && $val === '') {
                        $val = null;
                    }
                }
                $payload[$field] = $val;
            }
        }

        $faq->update($payload);

        return response()->json(['ok' => true, 'faq' => $faq->fresh()]);
    }

    /**
     * Eliminar FAQ.
     */
    public function destroy($id)
    {
        $faq = ChatbotFaq::findOrFail($id);
        $faq->delete();

        return response()->json(['ok' => true, 'message' => 'FAQ eliminada.']);
    }

    /**
     * Listar categorías existentes (para autocompletado en la UI).
     */
    public function categories()
    {
        return response()->json([
            'ok'         => true,
            'categories' => ChatbotFaq::distinctCategories(),
        ]);
    }
}
