<?php

namespace Tests\Feature\Marketing;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Mkt01ChatbotSettingsRoutesTest — Regresión de rutas de la UI de settings.
 *
 * La UI Vue de "Configuración del Chatbot" (chatbot-settings/index.vue) consume
 * su CRUD por rutas WEB bajo /marketing/chatbot/... (igual que la config, las
 * pruebas de conexión y whatsapp-pending). El CRUD de catálogos y FAQs quedó,
 * por error, SOLO en api.php (/api/marketing/chatbot/...), por lo que guardar un
 * catálogo devolvía 404 "No se encontró la URL especificada".
 *
 * Este test blinda que las rutas web existan, para que no vuelva a romperse.
 */
class Mkt01ChatbotSettingsRoutesTest extends TestCase
{
    /** @test */
    public function las_rutas_web_del_crud_de_catalogos_existen()
    {
        $this->assertTrue(Route::has('tenant.marketing.chatbot.catalogs.index'),
            'Falta GET /marketing/chatbot/catalogs (index)');
        $this->assertTrue(Route::has('tenant.marketing.chatbot.catalogs.store'),
            'Falta POST /marketing/chatbot/catalogs (store) — era el que daba 404 al guardar');
        $this->assertTrue(Route::has('tenant.marketing.chatbot.catalogs.update'),
            'Falta PUT /marketing/chatbot/catalogs/{id} (update)');
        $this->assertTrue(Route::has('tenant.marketing.chatbot.catalogs.destroy'),
            'Falta DELETE /marketing/chatbot/catalogs/{id} (destroy)');
    }

    /** @test */
    public function las_rutas_web_del_crud_de_faqs_existen()
    {
        // Mismo bug latente que catálogos: la UI las llama por ruta web.
        $this->assertTrue(Route::has('tenant.marketing.chatbot.faqs.index'),
            'Falta GET /marketing/chatbot/faqs (index)');
        $this->assertTrue(Route::has('tenant.marketing.chatbot.faqs.categories'),
            'Falta GET /marketing/chatbot/faqs/categories');
        $this->assertTrue(Route::has('tenant.marketing.chatbot.faqs.store'),
            'Falta POST /marketing/chatbot/faqs (store)');
        $this->assertTrue(Route::has('tenant.marketing.chatbot.faqs.update'),
            'Falta PUT /marketing/chatbot/faqs/{id} (update)');
        $this->assertTrue(Route::has('tenant.marketing.chatbot.faqs.destroy'),
            'Falta DELETE /marketing/chatbot/faqs/{id} (destroy)');
    }

    /** @test */
    public function el_crud_de_catalogos_apunta_al_controller_correcto()
    {
        $route = Route::getRoutes()->getByName('tenant.marketing.chatbot.catalogs.store');
        $this->assertNotNull($route);
        $this->assertContains('ChatbotCatalogsController@store', $route->getActionName());
        // Debe estar protegida por auth (es del panel de administración).
        $this->assertContains('auth', $route->gatherMiddleware());
    }
}
