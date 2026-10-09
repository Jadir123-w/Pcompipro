<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * MarketingController — entry points (views) del módulo Marketing.
 *
 * Solo retorna las vistas raíz (Vue SPA mounts). Toda la lógica vive en los
 * controllers Api/*  y se consume vía fetch desde los componentes Vue.
 */
class MarketingController extends Controller
{
    /**
     * GET /marketing/quotation
     * Pantalla del cotizador inteligente COM-02
     */
    public function quotationIndex()
    {
        return view('marketing::quotation.index');
    }

    /**
     * GET /marketing/mkt03
     * Pantalla del agente de contenido social MKT-03
     */
    public function mkt03Index()
    {
        return view('marketing::mkt03.index');
    }

    /**
     * GET /marketing/brand-assets
     * Brand Asset Library — repositorio compartido por los 3 agentes
     */
    public function brandAssetsIndex()
    {
        return view('marketing::brand-assets.index');
    }

    /**
     * GET /marketing/agents-config
     * Configuración por agente (provider/modelo/temperature/etc.) compartida
     * para los 3 agentes con tabs.
     */
    public function agentsConfigIndex()
    {
        return view('marketing::agents-config.index');
    }

    /**
     * GET /marketing/quotation/settings
     * Configuración de negocio del cotizador COM-02:
     * empresa, numeración, precios/envío, plantillas, condiciones, márgenes.
     */
    public function quotationSettingsIndex()
    {
        return view('marketing::quotation-settings.index');
    }

    /**
     * GET /marketing/chatbot/settings-page
     * Configuración del agente MKT-01: general, catálogos, WhatsApp, correo, FAQs.
     */
    public function chatbotSettingsIndex()
    {
        return view('marketing::chatbot-settings.index');
    }

    /**
     * GET /marketing/whatsapp-pending
     * Panel de aprobación humana de respuestas de WhatsApp (Nivel 1).
     */
    public function whatsappPendingIndex()
    {
        return view('marketing::whatsapp-pending.index');
    }

    /**
     * GET /marketing/email-triage/pending
     * Panel de aprobación humana de borradores de correo (MKT-01 Email Triage ET3).
     */
    public function emailTriagePendingIndex()
    {
        return view('marketing::email-triage-pending.index');
    }

    /**
     * GET /marketing/com07/panel
     * Panel de Trabajo de la ejecutiva: oportunidades de recuperación asignadas
     * a su cartera (COM-07).
     */
    public function com07PanelIndex()
    {
        return view('marketing::com07.panel');
    }

    /**
     * GET /marketing/com07/supervision
     * Vista de Supervisión Comercial: consolidado de todas las carteras y de
     * las oportunidades pendientes de asignación (COM-07).
     */
    public function com07SupervisionIndex()
    {
        return view('marketing::com07.supervision');
    }
}
