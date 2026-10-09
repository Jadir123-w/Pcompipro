<?php

namespace Modules\Marketing\Services;

/**
 * EditorialGuidelinesService — MKT-03 M4
 *
 * Consolida los **hallazgos editoriales del cliente COMPIPRO** en un único
 * lugar para que los 3 engines (analyzer, generator, producer) puedan
 * inyectar el mismo set de principios al system prompt del LLM.
 *
 * Fuente de los hallazgos:
 *   1. Análisis de los 3 PDFs de COMPIPRO (Instagram / LinkedIn / TikTok)
 *      enviados por el cliente el 16-abr-2026.
 *   2. Análisis de los 3 planners históricos (2022, 2023, 2024-2025)
 *      importados con `marketing:mkt03-import-historical` (M1).
 *   3. Documento Funcional (Dev) — MKT-03, secciones 6.x, 7.x, 9.x.
 *
 * Estos principios se inyectan al final del system prompt para reforzar
 * los aspectos editoriales más sensibles (anti-plantilla, storytelling,
 * 7 historias destacadas Instagram, 10 líneas comerciales, etc.).
 */
class EditorialGuidelinesService
{
    /**
     * 10 líneas comerciales reconocidas de COMPIPRO.
     * Detectadas en los planners históricos 2022-2024 y validadas por el cliente.
     */
    const LINEAS_COMERCIALES = [
        'Artículos Promocionales',
        'Artículos de Cuero y Madera',
        'Artículos Impresos',
        'Kits Corporativos',
        'Publicidad Exterior',
        'Artículos Ecológicos',
        'Línea Textil',
        'Línea Premium',
        'Calendarios y Almanaques',
        'Cuadernos Corporativos',
    ];

    /**
     * 7 historias destacadas de Instagram (@compipro) — definidas por el
     * cliente. Aparecen en el perfil y deben respetarse al adaptar
     * contenido para Instagram.
     */
    const HISTORIAS_DESTACADAS_INSTAGRAM = [
        'Artículos Promocionales',
        'Artículos de Cuero y Madera',
        'Artículos Impresos',
        'Kits Corporativos',
        'Publicidad Exterior',
        'Artículos Ecológicos',
        'Línea Textil',
    ];

    /**
     * 3 tipos dominantes de contenido detectados en los planners históricos
     * 2023+ (cuando se introdujo la columna TIPO DE CONTENIDO).
     */
    const TIPOS_CONTENIDO_HISTORICO = [
        'Promoción',
        'Trivia',
        'Contenido de valor',
    ];

    /**
     * Identidad visual de COMPIPRO a PRESERVAR (no es vicio, es marca).
     */
    const IDENTIDAD_VISUAL_PRESERVAR = [
        'Color primario: naranja COMPIPRO',
        'Color secundario: gris',
        'Fondo: blanco / limpio',
        'Tipografía: gruesa, protagonista, sans-serif bold',
        'Iluminación del producto: brillante, estilo estudio',
        'Sensación: profesional, ordenada, claridad cromática',
    ];

    /**
     * Vicios estilísticos detectados que se deben CORREGIR al generar
     * contenido nuevo (NO son identidad de marca).
     */
    const VICIOS_A_CORREGIR = [
        'Plantilla repetida: "bloque naranja grande + título mayúsculas + producto debajo"',
        'Texto descriptivo sin narrativa ("producto X sirve para…")',
        'Falta de storytelling y estrategia emocional',
        'Contenido tipo catálogo dominante',
        'Monotonía visual entre piezas consecutivas',
    ];

    /**
     * Estructura narrativa obligatoria para el copy (storytelling).
     */
    const ESTRUCTURA_STORYTELLING = [
        '1. Hook / Problema / Porqué emocional (primera frase)',
        '2. Solución (cómo COMPIPRO lo resuelve, con el producto)',
        '3. Justificación de autoridad (por qué COMPIPRO es la elección correcta)',
        '4. CTA (acción esperada)',
    ];

    /**
     * Optimizaciones por red social, derivadas de los PDFs del cliente.
     * Cada entry: límites + objetivo estratégico + señales algorítmicas a perseguir.
     */
    const OPTIMIZACIONES_POR_RED = [
        'facebook' => [
            'objetivo'    => 'Cercanía, visibilidad, valor comercial, recordación de marca',
            'tono'        => 'Útil, accesible, cercano',
            'max_hashtags' => 5,
            'longitud_copy' => 'Media (sin excesos)',
            'señales_priorizadas' => 'Engagement temprano, compartidos',
            'evitar'      => 'Tecnicismo excesivo, tono institucional, copy frío',
        ],
        'instagram' => [
            'objetivo'    => 'Posicionarse como AUTORIDAD del sector',
            'tono'        => 'Estético, breve, con gancho visual',
            'max_hashtags' => 15,
            'longitud_copy' => 'Corta (gancho en primeras palabras)',
            'aspect_ratio' => '4:5 (publicaciones), 9:16 (reels/historias)',
            'señales_priorizadas' => 'Compartidos por DM (la más fuerte), guardados, tiempo de visualización, comentarios reales',
            'evitar'      => 'Texto pesado, pieza cargada, tono LinkedIn',
        ],
        'linkedin' => [
            'objetivo'    => 'Aumentar visibilidad de marca; autoridad corporativa y académica',
            'tono'        => 'Profesional, ejecutivo, narrativo, B2B',
            'max_hashtags' => 5,
            'longitud_copy' => '800-1500 caracteres (óptimo para algoritmo)',
            'aspect_ratio' => '1:1 imagen individual, 3:2 conjuntos',
            'arranque_obligatorio' => 'Pregunta, afirmación fuerte, o historia',
            'penalizado'   => 'Enlaces externos, contenido muy promocional, más de 5 hashtags',
            'señales_priorizadas' => 'Comentarios (la más fuerte), tiempo de lectura, clicks, compartidos, likes',
            'reglas_extra' => 'REDUCIR drásticamente contenido promocional. Priorizar discusión profesional, contenido educativo, experiencias reflexivas.',
        ],
        'tiktok' => [
            'objetivo'    => 'Visibilidad, creatividad, dinamismo. Verificación azul + 1000 seguidores',
            'tono'        => 'Dinámico, gancho fuerte en 3 primeros segundos',
            'max_hashtags' => 10,
            'longitud_copy' => 'Corta con gancho',
            'formato_etapa1' => 'IMAGEN ESTÁTICA + brief audiovisual (NO video — humano decide producir video después)',
            'aspect_ratio' => '9:16',
            'señales_priorizadas' => 'Tiempo de visualización (la más fuerte), tasa de finalización, revisualización, compartidos, comentarios, likes',
            'evitar'      => 'Contenido estático tipo catálogo, copy pesado, tono corporativo rígido',
        ],
    ];

    /**
     * Principios editoriales generales (sec 14.7 del MKT03_Analysis_And_Plan).
     */
    const PRINCIPIOS_EDITORIALES = [
        'Romper la repetición de plantilla — cada pieza debe variar composición, estructura visual y encuadre, preservando la paleta cromática.',
        'Elevar el mensaje con storytelling — los copys NO deben ser solo descriptivos. Estructura narrativa: problema → solución → autoridad.',
        'Alternar formatos y temáticas — evitar secuencias monotemáticas. Alternar entre: producto limpio, carrusel educativo, caso real de uso, proceso detrás del producto, comparativa, trivia, contenido de valor.',
        'Micro contenido educativo regular — para posicionar COMPIPRO como autoridad del sector.',
        'Identidad visual a preservar (NO tocar) — naranja como color primario, claridad cromática, iluminación y fondo limpio.',
    ];

    /**
     * Generar el addendum textual para inyectar al system prompt de cualquier
     * engine (generator, producer). Este addendum se concatena al final del
     * prompt principal y refuerza los hallazgos editoriales.
     *
     * @param  string|null  $redSocial  Si se pasa una red, agrega su sección específica.
     *                                  Si null, agrega el bloque global sin sección por red.
     * @return string
     */
    public function buildSystemPromptAddendum($redSocial = null)
    {
        $out = "\n\n---\n\n# HALLAZGOS EDITORIALES COMPIPRO (consolidados)\n\n";

        // ── Líneas comerciales reconocidas ──
        $out .= "## Líneas comerciales reconocidas (10)\n\n";
        $out .= "Cuando un ítem mencione un producto, mapéalo a UNA de estas líneas:\n";
        foreach (self::LINEAS_COMERCIALES as $i => $linea) {
            $out .= sprintf("  %d. %s\n", $i + 1, $linea);
        }
        $out .= "\nSi detectas una línea nueva, anótala explícitamente como 'línea_comercial: nueva' en observaciones.\n\n";

        // ── 7 destacadas Instagram ──
        $out .= "## 7 historias destacadas de Instagram (@compipro)\n\n";
        $out .= "El feed/stories de Instagram tiene 7 destacadas definidas por el cliente. Si la pieza es para Instagram, alinéala con UNA de estas líneas siempre que sea posible:\n";
        foreach (self::HISTORIAS_DESTACADAS_INSTAGRAM as $i => $h) {
            $out .= sprintf("  %d. %s\n", $i + 1, $h);
        }
        $out .= "\n";

        // ── 3 tipos de contenido del histórico ──
        $out .= "## Tipos de contenido del histórico (3 dominantes)\n\n";
        $out .= "Los planners 2023+ usan 3 tipos. Distribuye los ítems entre ellos para evitar saturación promocional:\n";
        foreach (self::TIPOS_CONTENIDO_HISTORICO as $tipo) {
            $out .= "  - {$tipo}\n";
        }
        $out .= "\n";

        // ── Identidad visual ──
        $out .= "## Identidad visual a PRESERVAR (no es vicio, es marca)\n\n";
        foreach (self::IDENTIDAD_VISUAL_PRESERVAR as $iv) {
            $out .= "  - {$iv}\n";
        }
        $out .= "\n";

        // ── Vicios a corregir ──
        $out .= "## Vicios a CORREGIR (NO replicar)\n\n";
        foreach (self::VICIOS_A_CORREGIR as $v) {
            $out .= "  - {$v}\n";
        }
        $out .= "\n";

        // ── Storytelling ──
        $out .= "## Estructura narrativa obligatoria del copy\n\n";
        foreach (self::ESTRUCTURA_STORYTELLING as $s) {
            $out .= "  {$s}\n";
        }
        $out .= "\n";

        // ── Principios generales ──
        $out .= "## Principios editoriales generales\n\n";
        foreach (self::PRINCIPIOS_EDITORIALES as $i => $p) {
            $out .= sprintf("  %d. %s\n", $i + 1, $p);
        }
        $out .= "\n";

        // ── Si hay red específica, agregar sus optimizaciones ──
        if ($redSocial !== null && isset(self::OPTIMIZACIONES_POR_RED[$redSocial])) {
            $opt = self::OPTIMIZACIONES_POR_RED[$redSocial];
            $out .= "## Optimizaciones específicas para " . strtoupper($redSocial) . "\n\n";
            foreach ($opt as $k => $v) {
                $out .= "  - " . ucfirst(str_replace('_', ' ', $k)) . ": {$v}\n";
            }
            $out .= "\n";
        }

        return $out;
    }

    /**
     * Devolver las 10 líneas comerciales como array.
     *
     * @return array
     */
    public function getLineasComerciales()
    {
        return self::LINEAS_COMERCIALES;
    }

    /**
     * Devolver las 7 historias destacadas de Instagram como array.
     *
     * @return array
     */
    public function getHistoriasDestacadasInstagram()
    {
        return self::HISTORIAS_DESTACADAS_INSTAGRAM;
    }

    /**
     * Devolver los 3 tipos de contenido del histórico como array.
     *
     * @return array
     */
    public function getTiposContenidoHistorico()
    {
        return self::TIPOS_CONTENIDO_HISTORICO;
    }

    /**
     * Devolver las optimizaciones específicas para una red dada.
     *
     * @param  string  $redSocial
     * @return array|null
     */
    public function getOptimizacionesPorRed($redSocial)
    {
        return isset(self::OPTIMIZACIONES_POR_RED[$redSocial])
            ? self::OPTIMIZACIONES_POR_RED[$redSocial]
            : null;
    }
}
