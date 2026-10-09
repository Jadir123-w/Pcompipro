<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed inicial de mkt_learning_patterns con los patrones detectados
 * en los insumos del cliente (3 PDFs de análisis + 3 Excels históricos).
 *
 * Ver sección §14.7.3 del MKT03_Analysis_And_Plan.md
 */
class SeedMktInitialPatterns extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        $patterns = [
            // ── Patrones de FRECUENCIA ──
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'frecuencia',
                'red_social'  => 'facebook',
                'titulo'      => 'Facebook: 2 posts por día laborable',
                'descripcion' => 'En los planners históricos 2022-2025, Facebook siempre tuvo 2 posts diarios de lunes a viernes. Sábados sin post.',
                'datos_patron' => json_encode([
                    'posts_por_dia'     => 2,
                    'dias_activos'      => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'],
                    'dias_inactivos'    => ['sabado', 'domingo'],
                ]),
                'ocurrencias' => 3,
                'confianza'   => 95.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'frecuencia',
                'red_social'  => 'instagram',
                'titulo'      => 'Instagram: 2 posts por día laborable (hasta 2023 compartía con FB)',
                'descripcion' => 'Hasta 2023 Instagram compartía grilla base con Facebook (2 posts/día). En 2024-2025 se separó de Facebook pero mantiene frecuencia similar.',
                'datos_patron' => json_encode([
                    'posts_por_dia'     => 2,
                    'dias_activos'      => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'],
                    'dias_inactivos'    => ['sabado', 'domingo'],
                ]),
                'ocurrencias' => 3,
                'confianza'   => 90.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'frecuencia',
                'red_social'  => 'linkedin',
                'titulo'      => 'LinkedIn: 1 post por día laborable',
                'descripcion' => 'En los planners históricos 2022-2025, LinkedIn siempre tuvo 1 post diario de lunes a viernes. Sábados sin post.',
                'datos_patron' => json_encode([
                    'posts_por_dia'     => 1,
                    'dias_activos'      => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes'],
                    'dias_inactivos'    => ['sabado', 'domingo'],
                ]),
                'ocurrencias' => 3,
                'confianza'   => 98.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'frecuencia',
                'red_social'  => 'tiktok',
                'titulo'      => 'TikTok: frecuencia baja histórica, a definir',
                'descripcion' => 'En el planner 2023 TikTok apareció por primera vez con solo 67 filas (vs 1000+ de FB-IG). Frecuencia real baja. Pendiente definir con el cliente.',
                'datos_patron' => json_encode([
                    'posts_por_dia' => null,
                    'nota' => 'TikTok no consolidado en histórico; definir manualmente',
                ]),
                'ocurrencias' => 1,
                'confianza'   => 40.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],

            // ── Patrones de DISTRIBUCION DE RED ──
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'distribucion_red',
                'red_social'  => null,
                'titulo'      => 'FB-IG compartían grilla base 2022-2023; se separaron en 2024',
                'descripcion' => 'Hasta 2023 los planners tenían una hoja "FB IG" compartida. En 2024 el cliente separó Facebook en hoja propia. Esto refleja una evolución estratégica de diferenciar contenido entre ambas redes.',
                'datos_patron' => json_encode([
                    'union'      => ['2022', '2023'],
                    'separacion' => ['2024', '2025'],
                ]),
                'ocurrencias' => 3,
                'confianza'   => 100.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],

            // ── Patrones de TIPO DE CONTENIDO ──
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'tipo_pieza',
                'red_social'  => null,
                'titulo'      => '3 tipos de contenido dominantes: Promoción, Trivia, Contenido de valor',
                'descripcion' => 'Desde el planner 2023, el campo "TIPO DE CONTENIDO" clasifica las piezas en 3 categorías principales. Se recomienda mantener esta taxonomía.',
                'datos_patron' => json_encode([
                    'tipos' => ['Promoción', 'Trivia', 'Contenido de valor'],
                ]),
                'ocurrencias' => 2,
                'confianza'   => 95.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],

            // ── Patrones de TONO/IDENTIDAD VISUAL ──
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'tono',
                'red_social'  => null,
                'titulo'      => 'Identidad visual: naranja + tipografía gruesa + fondo blanco',
                'descripcion' => 'Los 4 PDFs de análisis confirman que COMPIPRO tiene una identidad visual consistente: color primario naranja, tipografía gruesa protagonista, bloques con esquinas redondeadas, fondo limpio (blanco), iluminación del producto. Se DEBE preservar.',
                'datos_patron' => json_encode([
                    'color_primario'       => 'naranja',
                    'color_secundario'     => 'gris',
                    'fondo'                => 'blanco',
                    'tipografia'           => 'gruesa, fuerte, protagonista',
                    'bloques'              => 'esquinas redondeadas',
                    'iluminacion_producto' => 'brillante',
                ]),
                'ocurrencias' => 4,
                'confianza'   => 100.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'tono',
                'red_social'  => null,
                'titulo'      => 'VICIO A CORREGIR: repetición de plantilla (bloque naranja + título mayúsculas + producto abajo)',
                'descripcion' => 'Los 4 PDFs coinciden en identificar un vicio estilístico: el agente NO debe replicar mecánicamente esta plantilla. Cada pieza debe variar composición y estructura visual, preservando solo la paleta cromática.',
                'datos_patron' => json_encode([
                    'evitar' => [
                        'bloque_naranja_grande_dominante',
                        'titulo_siempre_en_mayusculas',
                        'producto_siempre_debajo_del_titulo',
                    ],
                    'variar' => ['composicion', 'encuadre', 'jerarquia_visual', 'distribucion_espacial'],
                ]),
                'ocurrencias' => 4,
                'confianza'   => 95.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'tono',
                'red_social'  => null,
                'titulo'      => 'Storytelling: elevar discurso de descriptivo a narrativo',
                'descripcion' => 'Los PDFs recomiendan que cada copy siga estructura: 1) Problema/necesidad/porqué emocional, 2) Solución (producto), 3) Justificación (autoridad). Evitar textos puramente descriptivos o comerciales.',
                'datos_patron' => json_encode([
                    'estructura_narrativa' => ['problema_o_porque_emocional', 'solucion_producto', 'justificacion_autoridad'],
                    'evitar' => ['texto_puramente_descriptivo', 'texto_puramente_comercial'],
                ]),
                'ocurrencias' => 3,
                'confianza'   => 90.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],

            // ── Patrones de COMUNICACION RECURRENTE (líneas comerciales) ──
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'comunicacion_recurrente',
                'red_social'  => null,
                'titulo'      => 'Líneas comerciales recurrentes (catálogo del cliente)',
                'descripcion' => 'Lista de líneas de producto/servicio que COMPIPRO maneja. Detectadas en el PDF de Instagram (7 historias destacadas) y complementadas con las encontradas en copies históricos. Cada ítem de grilla puede asociarse a una línea.',
                'datos_patron' => json_encode([
                    'lineas' => [
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
                    ],
                ]),
                'ocurrencias' => 4,
                'confianza'   => 95.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],

            // ── Patrones de ALGORITMO por red ──
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'ritmo',
                'red_social'  => 'instagram',
                'titulo'      => 'Instagram: señal más fuerte = compartidos por DM',
                'descripcion' => 'Según análisis del algoritmo IG 2025, las señales en orden de importancia son: 1) Compartidos por DM, 2) Guardados, 3) Tiempo de visualización, 4) Comentarios reales (los largos pesan más que cortos), 5) Relación con la audiencia.',
                'datos_patron' => json_encode([
                    'senales_priorizadas' => [
                        'compartidos_por_dm'    => 'maxima',
                        'guardados'             => 'alta',
                        'tiempo_visualizacion'  => 'alta',
                        'comentarios_largos'    => 'alta',
                        'comentarios_cortos'    => 'media',
                        'relacion_con_audiencia'=> 'media',
                    ],
                    'contenido_priorizado' => ['compartible', 'educativo', 'tutorial', 'guía', 'lista', 'análisis_técnico', 'genera_conversación'],
                ]),
                'ocurrencias' => 1,
                'confianza'   => 85.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'ritmo',
                'red_social'  => 'linkedin',
                'titulo'      => 'LinkedIn: señal más fuerte = comentarios; reducir contenido promocional',
                'descripcion' => 'Según análisis del algoritmo LinkedIn, las señales son: 1) Comentarios (la más fuerte), 2) Tiempo de lectura, 3) Clicks, 4) Compartidos, 5) Likes. IMPORTANTE: contenido muy promocional se penaliza como spam. Evitar enlaces externos y más de 5 hashtags. Longitud óptima: 800-1500 caracteres.',
                'datos_patron' => json_encode([
                    'senales_priorizadas' => [
                        'comentarios'      => 'maxima',
                        'tiempo_lectura'   => 'alta',
                        'clicks'           => 'alta',
                        'compartidos'      => 'alta',
                        'likes'            => 'media',
                    ],
                    'penalizaciones' => [
                        'enlaces_externos',
                        'contenido_muy_promocional',
                        'mas_de_5_hashtags',
                    ],
                    'formato_optimo' => [
                        'longitud_caracteres' => [800, 1500],
                        'inicio' => ['pregunta', 'afirmacion_fuerte', 'historia'],
                        'estructura' => ['saltos_de_linea', 'parrafos_cortos'],
                    ],
                    'contenido_priorizado' => ['discusion_profesional', 'educativo', 'experiencias_reflexivas'],
                ]),
                'ocurrencias' => 1,
                'confianza'   => 90.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'ritmo',
                'red_social'  => 'tiktok',
                'titulo'      => 'TikTok: señal más fuerte = tiempo de visualización y tasa de finalización',
                'descripcion' => 'Según análisis del algoritmo TikTok, las señales son: 1) Tiempo de visualización, 2) Tasa de finalización (video corto completo > video largo incompleto), 3) Tasa de revisualización, 4) Compartidos, 5) Comentarios, 6) Likes. Impacto en primeros 3 segundos es decisivo. NOTA: en este alcance NO se generan videos, solo imagen + brief.',
                'datos_patron' => json_encode([
                    'senales_priorizadas' => [
                        'tiempo_visualizacion' => 'maxima',
                        'tasa_finalizacion'    => 'alta',
                        'revisualizacion'      => 'alta',
                        'compartidos'          => 'alta',
                        'comentarios'          => 'media',
                        'likes'                => 'media',
                    ],
                    'nota_alcance' => 'El agente genera solo imagen estática + brief visual. La producción del video queda a cargo humano.',
                ]),
                'ocurrencias' => 1,
                'confianza'   => 85.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],

            // ── Patrones de COMUNICACION RECURRENTE (Instagram Historias Destacadas) ──
            [
                'fuente'      => 'historico',
                'tipo_patron' => 'comunicacion_recurrente',
                'red_social'  => 'instagram',
                'titulo'      => 'Instagram: Historias Destacadas configuradas (7 categorías)',
                'descripcion' => 'El perfil de Instagram de COMPIPRO tiene 7 líneas definidas como historias destacadas. El agente puede sugerir contenido alineado a estas categorías.',
                'datos_patron' => json_encode([
                    'historias_destacadas' => [
                        'Artículos Promocionales',
                        'Artículos de Cuero y Madera',
                        'Artículos Impresos',
                        'Kits Corporativos',
                        'Publicidad Exterior',
                        'Artículos Ecológicos',
                        'Línea Textil',
                    ],
                    'otras_historias' => ['Quienes somos', 'Eventos', 'Contáctanos'],
                ]),
                'ocurrencias' => 1,
                'confianza'   => 100.00,
                'active'      => 1,
                'aprobado_por_usuario' => 0,
            ],
        ];

        foreach ($patterns as $p) {
            $p['created_at'] = $now;
            $p['updated_at'] = $now;
            DB::table('mkt_learning_patterns')->insert($p);
        }
    }

    public function down()
    {
        DB::table('mkt_learning_patterns')->truncate();
    }
}
