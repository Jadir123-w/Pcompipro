<?php

/*
|--------------------------------------------------------------------------
| Rutas API del Chatbot — Módulo Marketing
|--------------------------------------------------------------------------
|
| Prefijo: /api/marketing/chatbot
|
| Estas rutas se registran desde ChatbotServiceProvider.
|
*/

use Illuminate\Support\Facades\Route;
use Modules\Marketing\Http\Controllers\Api\ChatbotMessageController;
use Modules\Marketing\Http\Controllers\Api\ChatbotLeadsController;
use Modules\Marketing\Http\Controllers\Api\ChatbotSettingsController;
use Modules\Marketing\Http\Controllers\Api\ChatbotCatalogsController;
use Modules\Marketing\Http\Controllers\Api\ChatbotFaqsController;
use Modules\Marketing\Http\Controllers\Api\ChatbotUploadController;
use Modules\Marketing\Http\Controllers\Api\WhatsAppPendingController;
use Modules\Marketing\Http\Controllers\Api\WhatsAppWebhookController;

/*
|--------------------------------------------------------------------------
| Rutas PÚBLICAS (sin autenticación)
|--------------------------------------------------------------------------
|
| Estas rutas son accedidas por el widget embebido y por WhatsApp.
| Protegidas solo por rate limiting.
|
*/
Route::prefix('marketing/chatbot')->group(function () {

    // ── Endpoint principal de conversación ──
    // POST /api/marketing/chatbot/message
    Route::post('/message', [ChatbotMessageController::class, 'handle'])
        ->middleware('throttle:' . config('marketing.chatbot.max_requests_per_minute', 20) . ',1')
        ->name('api.marketing.chatbot.message');

    // ── Settings públicos del widget ──
    // GET /api/marketing/chatbot/settings
    Route::get('/settings', [ChatbotSettingsController::class, 'show'])
        ->name('api.marketing.chatbot.settings');

    // ── WhatsApp Cloud API webhooks ──
    // GET  → verificación del webhook (challenge de Meta)
    // POST → recepción de mensajes entrantes
    Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])
        ->name('api.marketing.chatbot.whatsapp.verify');

    Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])
        ->name('api.marketing.chatbot.whatsapp.handle');

    // ── Upload de archivos (público, rate limit estricto) ──
    // POST /api/marketing/chatbot/upload
    Route::post('/upload', [ChatbotUploadController::class, 'upload'])
        ->middleware('throttle:5,1')
        ->name('api.marketing.chatbot.upload');
});


/*
|--------------------------------------------------------------------------
| Rutas PROTEGIDAS (con autenticación del ERP)
|--------------------------------------------------------------------------
|
| Estas rutas son accedidas desde el panel de administración del ERP.
|
*/
Route::middleware(['auth', 'redirect.module', 'locked.tenant', 'check.email.verified'])
    ->prefix('marketing/chatbot')
    ->name('api.marketing.chatbot.')
    ->group(function () {

        // ── Gestión de leads ──
        Route::get('/leads', [ChatbotLeadsController::class, 'index'])
            ->name('leads.index');

        Route::get('/leads/{id}', [ChatbotLeadsController::class, 'show'])
            ->name('leads.show');

        Route::put('/leads/{id}/status', [ChatbotLeadsController::class, 'updateStatus'])
            ->name('leads.status');

        Route::post('/leads/{id}/convert', [ChatbotLeadsController::class, 'convert'])
            ->name('leads.convert');

        // ── Gestión de catálogos ──
        Route::get('/catalogs', [ChatbotCatalogsController::class, 'index'])
            ->name('catalogs.index');

        Route::post('/catalogs', [ChatbotCatalogsController::class, 'store'])
            ->name('catalogs.store');

        Route::put('/catalogs/{id}', [ChatbotCatalogsController::class, 'update'])
            ->name('catalogs.update');

        Route::delete('/catalogs/{id}', [ChatbotCatalogsController::class, 'destroy'])
            ->name('catalogs.destroy');

        // ── Gestión de FAQs ──
        Route::get('/faqs', [ChatbotFaqsController::class, 'index'])
            ->name('faqs.index');

        Route::get('/faqs/categories', [ChatbotFaqsController::class, 'categories'])
            ->name('faqs.categories');

        Route::post('/faqs', [ChatbotFaqsController::class, 'store'])
            ->name('faqs.store');

        Route::put('/faqs/{id}', [ChatbotFaqsController::class, 'update'])
            ->name('faqs.update');

        Route::delete('/faqs/{id}', [ChatbotFaqsController::class, 'destroy'])
            ->name('faqs.destroy');

        // ── Attachments (visualización y limpieza por la ejecutiva) ──
        Route::get('/attachments/{id}/download', [ChatbotUploadController::class, 'download'])
            ->name('attachments.download');

        Route::delete('/attachments/{id}', [ChatbotUploadController::class, 'destroy'])
            ->name('attachments.destroy');

        Route::get('/leads/{leadId}/attachments', [ChatbotUploadController::class, 'indexByLead'])
            ->name('leads.attachments');

        // ── WhatsApp Nivel 1 — Aprobación humana ──
        // (Las rutas para la UI Vue viven en web.php; estas son por si algún
        //  cliente externo quiere consumir vía API.)
        Route::get('/whatsapp/pending', [WhatsAppPendingController::class, 'index'])
            ->name('whatsapp.pending.index');

        Route::get('/whatsapp/pending/count', [WhatsAppPendingController::class, 'count'])
            ->name('whatsapp.pending.count');
    });


/*
|--------------------------------------------------------------------------
| COM-02: Cotizador Inteligente — Rutas protegidas
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'redirect.module', 'locked.tenant', 'check.email.verified'])
    ->prefix('marketing/quotation')
    ->name('api.marketing.quotation.')
    ->group(function () {

        // ── Generar precotización ──
        Route::post('/generate', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'generate'])
            ->name('generate');

        // ── CRUD precotizaciones ──
        Route::get('/prequotes', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'index'])
            ->name('prequotes.index');

        Route::get('/prequotes/{id}', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'show'])
            ->name('prequotes.show');

        Route::put('/prequotes/{id}/item/{itemId}', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'updateItem'])
            ->name('prequotes.item.update');

        // COM-02 — "Cód. Artíc." editable por línea (espejo de web.php: el
        // frontend consume el grupo 'marketing', este es el de /api).
        Route::put('/prequotes/{id}/item/{itemId}/codigo', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'updateItemCodigo'])
            ->name('prequotes.item.codigo');

        Route::delete('/prequotes/{id}/item/{itemId}', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'deleteItem'])
            ->name('prequotes.item.delete');

        // Agosto 2026 (punto 5): duplicar un producto completo, para cotizaciones por escala.
        Route::post('/prequotes/{id}/item/{itemId}/duplicate', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'duplicateItem'])
            ->name('prequotes.item.duplicate');

        Route::put('/prequotes/{id}/status', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'updateStatus'])
            ->name('prequotes.status');

        Route::put('/prequotes/{id}/sctr', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'updateSctr'])
            ->name('prequotes.sctr');

        Route::post('/prequotes/{id}/recalculate', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'recalculate'])
            ->name('prequotes.recalculate');

        Route::get('/prequotes/{id}/history', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'history'])
            ->name('prequotes.history');

        Route::post('/prequotes/{id}/convert', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'convert'])
            ->name('prequotes.convert');

        // E4: Sugerencia IA de proveedores por ítem
        Route::get('/prequotes/{id}/items/{itemId}/supplier-suggestions',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'supplierSuggestions'])
            ->name('prequotes.items.supplier-suggestions');

        // E4: Aplicar proveedor sugerido al ítem
        Route::post('/prequotes/{id}/items/{itemId}/apply-supplier',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'applySupplier'])
            ->name('prequotes.items.apply-supplier');

        // ── Configuración del cotizador ──
        Route::get('/config', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'configIndex'])
            ->name('config.index');

        Route::put('/config/{key}', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'configUpdate'])
            ->name('config.update');

        // ── Reglas de margen ──
        Route::get('/margins', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'marginsIndex'])
            ->name('margins.index');

        Route::post('/margins', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'marginsStore'])
            ->name('margins.store');

        Route::put('/margins/{id}', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'marginsUpdate'])
            ->name('margins.update');

        Route::delete('/margins/{id}', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'marginsDestroy'])
            ->name('margins.destroy');

        Route::post('/margins/import', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'marginsImport'])
            ->name('margins.import');

        // ────────────────────────────────────────────────────────────────
        // COM-02 E2: Cotización Final (Salida Comercial Derivada §26)
        // ────────────────────────────────────────────────────────────────

        // Obtener/crear la proposal de una prequote (la crea con IA si no existe)
        Route::get('/prequotes/{id}/proposal', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'show'])
            ->name('proposals.show');

        // Editar campos de la proposal
        Route::put('/proposals/{id}', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'update'])
            ->name('proposals.update');

        // Regenerar la proposal completa (o un campo específico) con IA
        Route::post('/proposals/{id}/regenerate-with-ai', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'regenerateWithAI'])
            ->name('proposals.regenerate');

        // Sincronizar items desde la prequote
        Route::post('/proposals/{id}/sync-items', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'syncItems'])
            ->name('proposals.sync-items');

        // Generar PDF (sin emitir)
        Route::post('/proposals/{id}/pdf', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'generatePdf'])
            ->name('proposals.pdf');

        // Emitir al cliente (congela snapshot + PDF final)
        Route::post('/proposals/{id}/emit', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'emit'])
            ->name('proposals.emit');

        // Cambiar estado (enviada/aprobada/rechazada)
        Route::put('/proposals/{id}/status', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'updateStatus'])
            ->name('proposals.status');
    });
