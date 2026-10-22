<?php

use Illuminate\Support\Facades\Route;
use Modules\Marketing\Http\Controllers\MarketingController;

Route::middleware(['auth', 'redirect.module', 'locked.tenant', 'check.email.verified'])
    ->prefix('marketing')
    ->name('tenant.marketing.')
    ->group(function () {

        // ── MKT-01: Página de configuración (Fase 3) ──
        // Reemplaza a la página legacy /chatbot que vivía en MarketingController::index
        // y a `chatbot/settings` (POST) del flujo viejo greeting/farewell (eliminados).
        Route::get('/chatbot/settings-page', [MarketingController::class, 'chatbotSettingsIndex'])
            ->name('chatbot.settings-page');

        Route::get('/chatbot/settings/config', [\Modules\Marketing\Http\Controllers\Api\ChatbotConfigApiController::class, 'index'])
            ->name('chatbot.settings.config.index');

        Route::get('/chatbot/settings/config/{group}', [\Modules\Marketing\Http\Controllers\Api\ChatbotConfigApiController::class, 'showGroup'])
            ->name('chatbot.settings.config.group');

        Route::put('/chatbot/settings/config/{key}', [\Modules\Marketing\Http\Controllers\Api\ChatbotConfigApiController::class, 'update'])
            ->name('chatbot.settings.config.update');

        Route::post('/chatbot/settings/config/bulk', [\Modules\Marketing\Http\Controllers\Api\ChatbotConfigApiController::class, 'bulkUpdate'])
            ->name('chatbot.settings.config.bulk');

        // ── Gestión de catálogos (consumido por la UI Vue de settings; el CRUD
        //    vive en la ruta web /marketing/chatbot/catalogs, no en /api) ──
        Route::get('/chatbot/catalogs', [\Modules\Marketing\Http\Controllers\Api\ChatbotCatalogsController::class, 'index'])
            ->name('chatbot.catalogs.index');

        Route::post('/chatbot/catalogs', [\Modules\Marketing\Http\Controllers\Api\ChatbotCatalogsController::class, 'store'])
            ->name('chatbot.catalogs.store');

        Route::put('/chatbot/catalogs/{id}', [\Modules\Marketing\Http\Controllers\Api\ChatbotCatalogsController::class, 'update'])
            ->name('chatbot.catalogs.update');

        Route::delete('/chatbot/catalogs/{id}', [\Modules\Marketing\Http\Controllers\Api\ChatbotCatalogsController::class, 'destroy'])
            ->name('chatbot.catalogs.destroy');

        // ── Gestión de FAQs (misma UI Vue; también se consume por ruta web) ──
        Route::get('/chatbot/faqs', [\Modules\Marketing\Http\Controllers\Api\ChatbotFaqsController::class, 'index'])
            ->name('chatbot.faqs.index');

        Route::get('/chatbot/faqs/categories', [\Modules\Marketing\Http\Controllers\Api\ChatbotFaqsController::class, 'categories'])
            ->name('chatbot.faqs.categories');

        Route::post('/chatbot/faqs', [\Modules\Marketing\Http\Controllers\Api\ChatbotFaqsController::class, 'store'])
            ->name('chatbot.faqs.store');

        Route::put('/chatbot/faqs/{id}', [\Modules\Marketing\Http\Controllers\Api\ChatbotFaqsController::class, 'update'])
            ->name('chatbot.faqs.update');

        Route::delete('/chatbot/faqs/{id}', [\Modules\Marketing\Http\Controllers\Api\ChatbotFaqsController::class, 'destroy'])
            ->name('chatbot.faqs.destroy');

        // ── "Probar conexión" — validar credenciales sin esperar al primer uso real ──
        Route::post('/chatbot/whatsapp/test-connection',
            [\Modules\Marketing\Http\Controllers\Api\ChatbotConnectionTestController::class, 'whatsapp'])
            ->name('chatbot.whatsapp.test-connection');

        Route::post('/chatbot/email-triage/test-connection',
            [\Modules\Marketing\Http\Controllers\Api\ChatbotConnectionTestController::class, 'emailTriage'])
            ->name('chatbot.email-triage.test-connection');

        // ══════════════════════════════════════════════
        // MKT-01 WhatsApp Nivel 1 — Aprobación humana
        // ══════════════════════════════════════════════

        // Vista principal: panel de pendientes
        Route::get('/whatsapp-pending', [MarketingController::class, 'whatsappPendingIndex'])
            ->name('whatsapp-pending.index');

        // API consumida por la UI Vue
        Route::get('/whatsapp-pending/list', [\Modules\Marketing\Http\Controllers\Api\WhatsAppPendingController::class, 'index'])
            ->name('whatsapp-pending.list');

        Route::get('/whatsapp-pending/count', [\Modules\Marketing\Http\Controllers\Api\WhatsAppPendingController::class, 'count'])
            ->name('whatsapp-pending.count');

        Route::get('/whatsapp-pending/{id}', [\Modules\Marketing\Http\Controllers\Api\WhatsAppPendingController::class, 'show'])
            ->name('whatsapp-pending.show');

        Route::post('/whatsapp-pending/{id}/approve', [\Modules\Marketing\Http\Controllers\Api\WhatsAppPendingController::class, 'approve'])
            ->name('whatsapp-pending.approve');

        Route::post('/whatsapp-pending/{id}/edit', [\Modules\Marketing\Http\Controllers\Api\WhatsAppPendingController::class, 'edit'])
            ->name('whatsapp-pending.edit');

        Route::post('/whatsapp-pending/{id}/reject', [\Modules\Marketing\Http\Controllers\Api\WhatsAppPendingController::class, 'reject'])
            ->name('whatsapp-pending.reject');

        Route::post('/whatsapp-pending/{id}/retry', [\Modules\Marketing\Http\Controllers\Api\WhatsAppPendingController::class, 'retry'])
            ->name('whatsapp-pending.retry');

        // ══════════════════════════════════════════════
        // MKT-01 Email Triage ET3 — Cola de Correos Pendientes
        // ══════════════════════════════════════════════

        // Vista principal
        Route::get('/email-triage/pending', [MarketingController::class, 'emailTriagePendingIndex'])
            ->name('email-triage.pending.index');

        // API — listado paginado con filtros (status, classification, search)
        Route::get('/email-triage/drafts',
            [\Modules\Marketing\Http\Controllers\Api\EmailTriagePendingController::class, 'index'])
            ->name('email-triage.drafts.index');

        // Badge para sidebar
        Route::get('/email-triage/drafts/count-pending',
            [\Modules\Marketing\Http\Controllers\Api\EmailTriagePendingController::class, 'countPending'])
            ->name('email-triage.drafts.count-pending');

        // "Revisar bandeja ahora" — dispara reader+clasificador ad-hoc (throttle 60s)
        Route::post('/email-triage/run-now',
            [\Modules\Marketing\Http\Controllers\Api\EmailTriagePendingController::class, 'runNow'])
            ->name('email-triage.run-now');

        // Detalle (correo original + borrador)
        Route::get('/email-triage/drafts/{id}',
            [\Modules\Marketing\Http\Controllers\Api\EmailTriagePendingController::class, 'show'])
            ->name('email-triage.drafts.show');

        // Editar borrador (subject/body/to/cc)
        Route::put('/email-triage/drafts/{id}',
            [\Modules\Marketing\Http\Controllers\Api\EmailTriagePendingController::class, 'update'])
            ->name('email-triage.drafts.update');

        // Aprobar
        Route::post('/email-triage/drafts/{id}/approve',
            [\Modules\Marketing\Http\Controllers\Api\EmailTriagePendingController::class, 'approve'])
            ->name('email-triage.drafts.approve');

        // Rechazar (con razón opcional)
        Route::post('/email-triage/drafts/{id}/reject',
            [\Modules\Marketing\Http\Controllers\Api\EmailTriagePendingController::class, 'reject'])
            ->name('email-triage.drafts.reject');

        // Enviar SMTP (manual, después de aprobar)
        Route::post('/email-triage/drafts/{id}/send',
            [\Modules\Marketing\Http\Controllers\Api\EmailTriagePendingController::class, 'send'])
            ->name('email-triage.drafts.send');

        // ── COM-02: Cotizador Inteligente ──
        Route::get('/quotation', [MarketingController::class, 'quotationIndex'])
            ->name('quotation.index');

        // COM-02: página de configuración de negocio (empresa, plantillas, márgenes…)
        Route::get('/quotation/settings', [MarketingController::class, 'quotationSettingsIndex'])
            ->name('quotation.settings');

        // COM-02: API endpoints accesibles desde la sesión web
        Route::post('/quotation/generate', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'generate'])
            ->name('quotation.generate');

        Route::get('/quotation/prequotes', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'index'])
            ->name('quotation.prequotes');

        Route::get('/quotation/prequotes/{id}', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'show'])
            ->name('quotation.prequotes.show');

        Route::put('/quotation/prequotes/{id}/item/{itemId}', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'updateItem'])
            ->name('quotation.prequotes.item.update');

        // COM-02 — "Cód. Artíc." editable por línea. Endpoint dedicado: sin
        // candado de estado (editable aun en aprobada) y sin recálculo.
        Route::put('/quotation/prequotes/{id}/item/{itemId}/codigo', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'updateItemCodigo'])
            ->name('quotation.prequotes.item.codigo');

        Route::delete('/quotation/prequotes/{id}/item/{itemId}', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'deleteItem'])
            ->name('quotation.prequotes.item.delete');

        // Agosto 2026 (punto 5): duplicar un producto completo, para cotizaciones por escala.
        Route::post('/quotation/prequotes/{id}/item/{itemId}/duplicate', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'duplicateItem'])
            ->name('quotation.prequotes.item.duplicate');

        Route::put('/quotation/prequotes/{id}/status', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'updateStatus'])
            ->name('quotation.prequotes.status');

        Route::put('/quotation/prequotes/{id}/sctr', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'updateSctr'])
            ->name('quotation.prequotes.sctr');

        Route::post('/quotation/prequotes/{id}/recalculate', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'recalculate'])
            ->name('quotation.prequotes.recalculate');

        Route::post('/quotation/prequotes/{id}/reinterpret-with-image',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'reinterpretWithImage'])
            ->name('quotation.prequotes.reinterpret-with-image');

        // COM-02 — "Referencia del producto": interpretar 1 ítem (texto XOR imagen)
        // y agregarlo sin borrar. Anclado en el requerimiento (crea la prequote si
        // no existe). throttle para acotar el gasto de LLM ante doble-click.
        Route::post('/quotation/requerimientos/{requerimientoId}/prequote/items/interpret',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'interpretItem'])
            ->middleware('throttle:10,1')
            ->name('quotation.requerimientos.prequote.items.interpret');

        // COM-02 — Ítem manual (alternativa sin IA a la referencia): agrega 1
        // ítem vacío sin borrar. Mismo anclaje y bootstrap que interpret.
        Route::post('/quotation/requerimientos/{requerimientoId}/prequote/items/manual',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'storeManualItem'])
            ->middleware('throttle:10,1')
            ->name('quotation.requerimientos.prequote.items.manual');

        Route::get('/quotation/prequotes/{id}/history', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'history'])
            ->name('quotation.prequotes.history');

        // E4: Sugerencia IA de proveedores por ítem
        Route::get('/quotation/prequotes/{id}/items/{itemId}/supplier-suggestions',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'supplierSuggestions'])
            ->name('quotation.prequotes.items.supplier-suggestions');

        // E4: Aplicar proveedor sugerido al ítem
        Route::post('/quotation/prequotes/{id}/items/{itemId}/apply-supplier',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'applySupplier'])
            ->name('quotation.prequotes.items.apply-supplier');

        // Comparativa de proveedores por ítem (mockup db): N cotizaciones
        // por ítem; el ganador define supplier + costo base de la línea.
        Route::get('/quotation/prequotes/{id}/items/{itemId}/quotes',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'supplierQuotes'])
            ->name('quotation.prequotes.items.quotes.index');

        Route::post('/quotation/prequotes/{id}/items/{itemId}/quotes',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'storeSupplierQuote'])
            ->name('quotation.prequotes.items.quotes.store');

        Route::put('/quotation/prequotes/{id}/items/{itemId}/quotes/{quoteId}',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'updateSupplierQuote'])
            ->name('quotation.prequotes.items.quotes.update');

        Route::delete('/quotation/prequotes/{id}/items/{itemId}/quotes/{quoteId}',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'destroySupplierQuote'])
            ->name('quotation.prequotes.items.quotes.destroy');

        Route::post('/quotation/prequotes/{id}/items/{itemId}/quotes/{quoteId}/winner',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'setWinnerSupplierQuote'])
            ->name('quotation.prequotes.items.quotes.winner');

        Route::post('/quotation/prequotes/{id}/items/{itemId}/quotes/{quoteId}/to-catalog',
            [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'toCatalogSupplierQuote'])
            ->name('quotation.prequotes.items.quotes.to-catalog');

        // ── Kits/paquetes comerciales (mockup db) ──
        Route::get('/quotation/prequotes/{id}/kits',
            [\Modules\Marketing\Http\Controllers\Api\ProposalKitController::class, 'index'])
            ->name('quotation.prequotes.kits.index');

        Route::post('/quotation/prequotes/{id}/kits',
            [\Modules\Marketing\Http\Controllers\Api\ProposalKitController::class, 'store'])
            ->name('quotation.prequotes.kits.store');

        Route::put('/quotation/prequotes/{id}/kits/{kitId}',
            [\Modules\Marketing\Http\Controllers\Api\ProposalKitController::class, 'update'])
            ->name('quotation.prequotes.kits.update');

        Route::delete('/quotation/prequotes/{id}/kits/{kitId}',
            [\Modules\Marketing\Http\Controllers\Api\ProposalKitController::class, 'destroy'])
            ->name('quotation.prequotes.kits.destroy');

        Route::post('/quotation/prequotes/{id}/kits/{kitId}/members',
            [\Modules\Marketing\Http\Controllers\Api\ProposalKitController::class, 'addMembers'])
            ->name('quotation.prequotes.kits.members.store');

        Route::delete('/quotation/prequotes/{id}/kits/{kitId}/members/{itemId}',
            [\Modules\Marketing\Http\Controllers\Api\ProposalKitController::class, 'removeMember'])
            ->name('quotation.prequotes.kits.members.destroy');

        Route::post('/quotation/prequotes/{id}/kits/{kitId}/photo',
            [\Modules\Marketing\Http\Controllers\Api\ProposalKitController::class, 'photo'])
            ->name('quotation.prequotes.kits.photo.store');

        Route::get('/quotation/prequotes/{id}/kits/{kitId}/photo',
            [\Modules\Marketing\Http\Controllers\Api\ProposalKitController::class, 'showPhoto'])
            ->name('quotation.prequotes.kits.photo.show');

        Route::post('/quotation/prequotes/{id}/convert', [\Modules\Marketing\Http\Controllers\Api\QuotationPreQuoteController::class, 'convert'])
            ->name('quotation.prequotes.convert');

        // ── COM-02 S1: Matching de productos del catálogo ──
        Route::get('/quotation/products/search',
            [\Modules\Marketing\Http\Controllers\Api\QuotationProductController::class, 'search'])
            ->name('quotation.products.search');

        Route::get('/quotation/prequote-items/{id}/suggestions',
            [\Modules\Marketing\Http\Controllers\Api\QuotationProductController::class, 'suggestions'])
            ->name('quotation.prequote-items.suggestions');

        Route::post('/quotation/prequote-items/{id}/match',
            [\Modules\Marketing\Http\Controllers\Api\QuotationProductController::class, 'manualMatch'])
            ->name('quotation.prequote-items.match');

        Route::post('/quotation/prequote-items/{id}/rematch',
            [\Modules\Marketing\Http\Controllers\Api\QuotationProductController::class, 'rematch'])
            ->name('quotation.prequote-items.rematch');

        // ── COM-02 S2: Imagen de referencia (multimodal AIEngine) ──
        Route::post('/quotation/prequotes/{id}/reference-image',
            [\Modules\Marketing\Http\Controllers\Api\QuotationImageController::class, 'uploadPrequoteImage'])
            ->name('quotation.prequotes.reference-image.upload');

        Route::get('/quotation/prequotes/{id}/reference-image',
            [\Modules\Marketing\Http\Controllers\Api\QuotationImageController::class, 'showPrequoteImage'])
            ->name('quotation.prequotes.reference-image.show');

        Route::delete('/quotation/prequotes/{id}/reference-image',
            [\Modules\Marketing\Http\Controllers\Api\QuotationImageController::class, 'deletePrequoteImage'])
            ->name('quotation.prequotes.reference-image.delete');

        Route::post('/quotation/prequote-items/{id}/reference-image',
            [\Modules\Marketing\Http\Controllers\Api\QuotationImageController::class, 'uploadItemImage'])
            ->name('quotation.prequote-items.reference-image.upload');

        Route::get('/quotation/prequote-items/{id}/reference-image',
            [\Modules\Marketing\Http\Controllers\Api\QuotationImageController::class, 'showItemImage'])
            ->name('quotation.prequote-items.reference-image.show');

        Route::delete('/quotation/prequote-items/{id}/reference-image',
            [\Modules\Marketing\Http\Controllers\Api\QuotationImageController::class, 'deleteItemImage'])
            ->name('quotation.prequote-items.reference-image.delete');

        // ── COM-02 S4: Sugerencias del sistema ──
        Route::get('/quotation/suggestions',
            [\Modules\Marketing\Http\Controllers\Api\QuotationSystemSuggestionController::class, 'index'])
            ->name('quotation.suggestions.index');

        Route::post('/quotation/suggestions/analyze-now',
            [\Modules\Marketing\Http\Controllers\Api\QuotationSystemSuggestionController::class, 'analyzeNow'])
            ->name('quotation.suggestions.analyze-now');

        Route::post('/quotation/suggestions/{id}/apply',
            [\Modules\Marketing\Http\Controllers\Api\QuotationSystemSuggestionController::class, 'apply'])
            ->name('quotation.suggestions.apply');

        Route::post('/quotation/suggestions/{id}/reject',
            [\Modules\Marketing\Http\Controllers\Api\QuotationSystemSuggestionController::class, 'reject'])
            ->name('quotation.suggestions.reject');

        Route::post('/quotation/suggestions/{id}/snooze',
            [\Modules\Marketing\Http\Controllers\Api\QuotationSystemSuggestionController::class, 'snooze'])
            ->name('quotation.suggestions.snooze');

        Route::get('/quotation/config', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'configIndex'])
            ->name('quotation.config');

        Route::put('/quotation/config/{key}', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'configUpdate'])
            ->name('quotation.config.update');

        Route::get('/quotation/margins', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'marginsIndex'])
            ->name('quotation.margins');

        Route::post('/quotation/margins', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'marginsStore'])
            ->name('quotation.margins.store');

        Route::put('/quotation/margins/{id}', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'marginsUpdate'])
            ->name('quotation.margins.update');

        Route::delete('/quotation/margins/{id}', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'marginsDestroy'])
            ->name('quotation.margins.destroy');

        Route::post('/quotation/margins/import', [\Modules\Marketing\Http\Controllers\Api\QuotationConfigController::class, 'marginsImport'])
            ->name('quotation.margins.import');

        // ────────────────────────────────────────────────────────────────
        // COM-02 E2: Cotización Final (Salida Comercial Derivada §26)
        // ────────────────────────────────────────────────────────────────

        // Obtener/crear la proposal de una prequote (la crea con IA si no existe)
        Route::get('/quotation/prequotes/{id}/proposal', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'show'])
            ->name('quotation.proposals.show');

        // Editar campos de la proposal
        Route::put('/quotation/proposals/{id}', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'update'])
            ->name('quotation.proposals.update');

        // Regenerar la proposal completa (o un campo específico) con IA
        Route::post('/quotation/proposals/{id}/regenerate-with-ai', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'regenerateWithAI'])
            ->name('quotation.proposals.regenerate');

        // Sincronizar items desde la prequote
        Route::post('/quotation/proposals/{id}/sync-items', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'syncItems'])
            ->name('quotation.proposals.sync-items');

        // Generar PDF (sin emitir)
        Route::post('/quotation/proposals/{id}/pdf', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'generatePdf'])
            ->name('quotation.proposals.pdf');

        // Emitir al cliente (congela snapshot + PDF final)
        Route::post('/quotation/proposals/{id}/emit', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'emit'])
            ->name('quotation.proposals.emit');

        // Enviar correo de PRUEBA a la casilla interna (NO cambia estado) — Req #14
        Route::post('/quotation/proposals/{id}/send-test-email', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'sendTestEmail'])
            ->name('quotation.proposals.send-test-email');

        // Cambiar estado (enviada/aprobada/rechazada)
        Route::put('/quotation/proposals/{id}/status', [\Modules\Marketing\Http\Controllers\Api\QuotationProposalController::class, 'updateStatus'])
            ->name('quotation.proposals.status');

        // ══════════════════════════════════════════════
        // MKT-03: Social Media Content Agent
        // ══════════════════════════════════════════════

        // ── Vista principal ──
        Route::get('/mkt03', [MarketingController::class, 'mkt03Index'])
            ->name('mkt03.index');

        // ── Modo A/B/C — Grillas ──
        Route::post('/mkt03/grids/generate', [\Modules\Marketing\Http\Controllers\Api\MKT03GridController::class, 'generate'])
            ->name('mkt03.grids.generate');

        Route::get('/mkt03/grids/consult', [\Modules\Marketing\Http\Controllers\Api\MKT03GridController::class, 'consult'])
            ->name('mkt03.grids.consult');

        Route::get('/mkt03/grids', [\Modules\Marketing\Http\Controllers\Api\MKT03GridController::class, 'index'])
            ->name('mkt03.grids.index');

        Route::get('/mkt03/grids/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03GridController::class, 'show'])
            ->name('mkt03.grids.show');

        Route::post('/mkt03/grids/{id}/extend', [\Modules\Marketing\Http\Controllers\Api\MKT03GridController::class, 'extend'])
            ->name('mkt03.grids.extend');

        // ── Modo F — Endpoint dedicado para el agente de ferias externo ──
        Route::post('/mkt03/ferias/receive',
            [\Modules\Marketing\Http\Controllers\Api\MKT03FeriaController::class, 'receiveFromExternalAgent'])
            ->name('mkt03.ferias.receive');

        // ── Modo C — Continuidad automática (§11.5 doc funcional) ──
        Route::get('/mkt03/continuity/status',
            [\Modules\Marketing\Http\Controllers\Api\MKT03GridController::class, 'continuityStatus'])
            ->name('mkt03.continuity.status');

        Route::post('/mkt03/continuity/run',
            [\Modules\Marketing\Http\Controllers\Api\MKT03GridController::class, 'continuityRun'])
            ->name('mkt03.continuity.run');

        Route::put('/mkt03/grids/{id}/status', [\Modules\Marketing\Http\Controllers\Api\MKT03GridController::class, 'updateStatus'])
            ->name('mkt03.grids.status');

        Route::delete('/mkt03/grids/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03GridController::class, 'destroy'])
            ->name('mkt03.grids.destroy');

        // ── CRUD de items de grilla ──
        Route::post('/mkt03/grids/{id}/items', [\Modules\Marketing\Http\Controllers\Api\MKT03GridController::class, 'storeItem'])
            ->name('mkt03.grids.items.store');

        Route::put('/mkt03/grids/{gridId}/items/{itemId}', [\Modules\Marketing\Http\Controllers\Api\MKT03GridController::class, 'updateItem'])
            ->name('mkt03.grids.items.update');

        Route::delete('/mkt03/grids/{gridId}/items/{itemId}', [\Modules\Marketing\Http\Controllers\Api\MKT03GridController::class, 'destroyItem'])
            ->name('mkt03.grids.items.destroy');

        // ── Modo D — Piezas ──
        Route::post('/mkt03/items/{itemId}/produce', [\Modules\Marketing\Http\Controllers\Api\MKT03PieceController::class, 'produce'])
            ->name('mkt03.items.produce');

        Route::get('/mkt03/pieces', [\Modules\Marketing\Http\Controllers\Api\MKT03PieceController::class, 'index'])
            ->name('mkt03.pieces.index');

        Route::get('/mkt03/pieces/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03PieceController::class, 'show'])
            ->name('mkt03.pieces.show');

        Route::put('/mkt03/pieces/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03PieceController::class, 'update'])
            ->name('mkt03.pieces.update');

        Route::post('/mkt03/pieces/{id}/regenerate-image', [\Modules\Marketing\Http\Controllers\Api\MKT03PieceController::class, 'regenerateImage'])
            ->name('mkt03.pieces.regenerate_image');

        Route::post('/mkt03/pieces/{id}/upload-image', [\Modules\Marketing\Http\Controllers\Api\MKT03PieceController::class, 'uploadImage'])
            ->name('mkt03.pieces.upload_image');

        Route::put('/mkt03/pieces/{id}/status', [\Modules\Marketing\Http\Controllers\Api\MKT03PieceController::class, 'updateStatus'])
            ->name('mkt03.pieces.status');

        Route::delete('/mkt03/pieces/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03PieceController::class, 'destroy'])
            ->name('mkt03.pieces.destroy');

        // ── Modo E — Contenido extraordinario ──
        Route::get('/mkt03/extraordinary', [\Modules\Marketing\Http\Controllers\Api\MKT03ExtraordinaryController::class, 'index'])
            ->name('mkt03.extraordinary.index');

        Route::get('/mkt03/extraordinary/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03ExtraordinaryController::class, 'show'])
            ->name('mkt03.extraordinary.show');

        Route::post('/mkt03/extraordinary', [\Modules\Marketing\Http\Controllers\Api\MKT03ExtraordinaryController::class, 'store'])
            ->name('mkt03.extraordinary.store');

        Route::put('/mkt03/extraordinary/{id}/decide', [\Modules\Marketing\Http\Controllers\Api\MKT03ExtraordinaryController::class, 'decide'])
            ->name('mkt03.extraordinary.decide');

        Route::delete('/mkt03/extraordinary/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03ExtraordinaryController::class, 'destroy'])
            ->name('mkt03.extraordinary.destroy');

        // ── Modo F — Ferias ──
        Route::get('/mkt03/ferias', [\Modules\Marketing\Http\Controllers\Api\MKT03FeriaController::class, 'index'])
            ->name('mkt03.ferias.index');

        Route::get('/mkt03/ferias/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03FeriaController::class, 'show'])
            ->name('mkt03.ferias.show');

        Route::post('/mkt03/ferias', [\Modules\Marketing\Http\Controllers\Api\MKT03FeriaController::class, 'store'])
            ->name('mkt03.ferias.store');

        Route::post('/mkt03/ferias/{id}/generate-items', [\Modules\Marketing\Http\Controllers\Api\MKT03FeriaController::class, 'generateItems'])
            ->name('mkt03.ferias.generate_items');

        Route::put('/mkt03/ferias/{id}/status', [\Modules\Marketing\Http\Controllers\Api\MKT03FeriaController::class, 'updateStatus'])
            ->name('mkt03.ferias.status');

        Route::delete('/mkt03/ferias/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03FeriaController::class, 'destroy'])
            ->name('mkt03.ferias.destroy');

        // ── Configuración de plataformas ──
        Route::get('/mkt03/platform-config', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'platformConfigIndex'])
            ->name('mkt03.platform_config.index');

        Route::get('/mkt03/platform-config/{red}', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'platformConfigShow'])
            ->name('mkt03.platform_config.show');

        Route::put('/mkt03/platform-config/{red}', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'platformConfigUpdate'])
            ->name('mkt03.platform_config.update');

        // ── Patrones aprendidos ──
        Route::get('/mkt03/patterns', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'patternsIndex'])
            ->name('mkt03.patterns.index');

        Route::get('/mkt03/patterns/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'patternsShow'])
            ->name('mkt03.patterns.show');

        Route::put('/mkt03/patterns/{id}/approve', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'patternsApprove'])
            ->name('mkt03.patterns.approve');

        Route::delete('/mkt03/patterns/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'patternsDestroy'])
            ->name('mkt03.patterns.destroy');

        // ── Grillas históricas ──
        Route::get('/mkt03/historical', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'historicalIndex'])
            ->name('mkt03.historical.index');

        Route::get('/mkt03/historical/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'historicalShow'])
            ->name('mkt03.historical.show');

        Route::post('/mkt03/historical/import', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'historicalImport'])
            ->name('mkt03.historical.import');

        Route::post('/mkt03/historical/{id}/parse', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'historicalParse'])
            ->name('mkt03.historical.parse');

        Route::post('/mkt03/historical/{id}/analyze', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'historicalAnalyze'])
            ->name('mkt03.historical.analyze');

        Route::delete('/mkt03/historical/{id}', [\Modules\Marketing\Http\Controllers\Api\MKT03ConfigController::class, 'historicalDestroy'])
            ->name('mkt03.historical.destroy');

        // ══════════════════════════════════════════════
        // Brand Asset Library (BAL) — compartido por los 3 agentes
        // ══════════════════════════════════════════════

        // Vista principal del BAL
        Route::get('/brand-assets', [MarketingController::class, 'brandAssetsIndex'])
            ->name('brand-assets.index');

        // CRUD API
        Route::get('/brand-assets/list', [\Modules\Marketing\Http\Controllers\Api\BrandAssetController::class, 'index'])
            ->name('brand-assets.list');

        Route::post('/brand-assets', [\Modules\Marketing\Http\Controllers\Api\BrandAssetController::class, 'store'])
            ->name('brand-assets.store');

        Route::get('/brand-assets/{id}', [\Modules\Marketing\Http\Controllers\Api\BrandAssetController::class, 'show'])
            ->name('brand-assets.show');

        Route::put('/brand-assets/{id}', [\Modules\Marketing\Http\Controllers\Api\BrandAssetController::class, 'update'])
            ->name('brand-assets.update');

        Route::post('/brand-assets/{id}/file', [\Modules\Marketing\Http\Controllers\Api\BrandAssetController::class, 'replaceFile'])
            ->name('brand-assets.replace-file');

        Route::delete('/brand-assets/{id}', [\Modules\Marketing\Http\Controllers\Api\BrandAssetController::class, 'destroy'])
            ->name('brand-assets.destroy');

        // ══════════════════════════════════════════════
        // Agents Config — provider + modelo + parámetros por agente
        // ══════════════════════════════════════════════

        // Vista principal
        Route::get('/agents-config', [MarketingController::class, 'agentsConfigIndex'])
            ->name('agents-config.index');

        // Catálogo de providers
        Route::get('/agents-config/providers', [\Modules\Marketing\Http\Controllers\Api\AgentConfigController::class, 'listProviders'])
            ->name('agents-config.providers.list');

        Route::put('/agents-config/providers/{id}', [\Modules\Marketing\Http\Controllers\Api\AgentConfigController::class, 'updateProvider'])
            ->name('agents-config.providers.update');

        // Configuración por agente
        Route::get('/agents-config/agents', [\Modules\Marketing\Http\Controllers\Api\AgentConfigController::class, 'listAgents'])
            ->name('agents-config.agents.list');

        Route::get('/agents-config/agents/{key}', [\Modules\Marketing\Http\Controllers\Api\AgentConfigController::class, 'showAgent'])
            ->name('agents-config.agents.show');

        Route::put('/agents-config/agents/{key}', [\Modules\Marketing\Http\Controllers\Api\AgentConfigController::class, 'updateAgent'])
            ->name('agents-config.agents.update');

        Route::post('/agents-config/agents/{key}/reset', [\Modules\Marketing\Http\Controllers\Api\AgentConfigController::class, 'resetAgent'])
            ->name('agents-config.agents.reset');


    });
