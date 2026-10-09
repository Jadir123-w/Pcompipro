<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Refina el seed de mkt_platform_config con los detalles absorbidos
 * de los 3 PDFs de análisis del cliente (IG, LinkedIn, TikTok).
 *
 * Ver sección §14.7.2 del MKT03_Analysis_And_Plan.md
 */
class RefineMktPlatformConfig extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        // ── FACEBOOK ──
        DB::table('mkt_platform_config')
            ->where('red_social', 'facebook')
            ->update([
                'funcion_editorial_principal' => 'Cercanía, visibilidad, valor comercial, difusión, recordación de marca. Orientada a llegar al público final con tono útil y accesible.',
                'que_priorizar' => implode(' | ', [
                    'Claridad y piezas visuales entendibles',
                    'Copy accesible, tono útil y cercano',
                    'Variar composiciones (evitar repetición de plantilla)',
                    'Storytelling: problema → solución → autoridad',
                    'Alternar tipos de contenido: Promoción, Trivia, Contenido de valor',
                ]),
                'que_evitar' => implode(' | ', [
                    'Exceso de tecnicismo',
                    'Tono demasiado institucional',
                    'Copy muy frío o extenso sin necesidad',
                    'Repetición de plantilla: bloque naranja + título mayúsculas + producto debajo',
                ]),
                'tono_recomendado' => 'Humano, cercano, útil, con narrativa emocional',
                'max_copy_length'  => 2200,
                'max_hashtags'     => 5,
                'updated_at'       => $now,
            ]);

        // ── INSTAGRAM ──
        DB::table('mkt_platform_config')
            ->where('red_social', 'instagram')
            ->update([
                'funcion_editorial_principal' => 'Impacto visual, recordación, atractivo estético, posicionamiento visual de marca. Objetivo estratégico: posicionarse como autoridad en el sector.',
                'que_priorizar' => implode(' | ', [
                    'Buena presencia visual, gancho en los primeros 1-3 segundos',
                    'Copy breve con narrativa',
                    'Contenido compartible, guardable (guías, listas, tutoriales)',
                    'Generar conversación (debates, preguntas abiertas)',
                    'Contenido educativo y técnico (micro-cursos)',
                    'Variar formatos alternando: producto → carrusel educativo → caso real → proceso → comparativa',
                ]),
                'que_evitar' => implode(' | ', [
                    'Texto muy pesado',
                    'Pieza muy cargada',
                    'Mensaje más propio de LinkedIn',
                    'Repetición de plantilla (bloque naranja + título mayúsculas + producto)',
                    'Contenido reciclado con marcas de agua de otras plataformas',
                ]),
                'tono_recomendado' => 'Visual, estético, breve, emocional, con storytelling',
                'max_copy_length'  => 2200,
                'max_hashtags'     => 15,
                'updated_at'       => $now,
            ]);

        // ── LINKEDIN ──
        DB::table('mkt_platform_config')
            ->where('red_social', 'linkedin')
            ->update([
                'funcion_editorial_principal' => 'Reputación, posicionamiento institucional, valor empresarial, credibilidad B2B. Objetivo estratégico: autoridad corporativa y académica.',
                'que_priorizar' => implode(' | ', [
                    'Enfoque profesional, claridad estratégica',
                    'Lenguaje ejecutivo, valor corporativo',
                    'Formato narrativo: iniciar con pregunta, afirmación fuerte o historia',
                    'Estructura visual: saltos de línea y párrafos cortos',
                    'Longitud óptima: 800-1500 caracteres',
                    'Contenido educativo, reflexivo, de experiencia profesional',
                    'Discusión profesional para maximizar comentarios (señal más fuerte)',
                ]),
                'que_evitar' => implode(' | ', [
                    'Tono demasiado informal',
                    'Contenido muy promocional (penalizado como spam por el algoritmo)',
                    'Visuales o copys que parezcan de TikTok o Instagram recreativo',
                    'Enlaces externos (reducen alcance)',
                    'Más de 5 hashtags (reducen alcance)',
                    'Repetición de plantilla',
                ]),
                'tono_recomendado' => 'Profesional, ejecutivo, estratégico, reflexivo',
                'max_copy_length'  => 3000,
                'max_hashtags'     => 5,
                'updated_at'       => $now,
            ]);

        // ── TIKTOK ──
        DB::table('mkt_platform_config')
            ->where('red_social', 'tiktok')
            ->update([
                'funcion_editorial_principal' => 'Atención, dinamismo, creatividad, formato corto, impacto inicial. NOTA: en este alcance el agente SOLO genera imagen estática + brief visual. La producción del video queda a cargo humano.',
                'que_priorizar' => implode(' | ', [
                    'Gancho visual en los primeros 3 segundos',
                    'Ritmo e idea de video (brief para producción humana)',
                    'Formato ágil, dinámico',
                    'Copy corto con pregunta o CTA',
                    'Tendencias adaptadas al contexto B2B',
                ]),
                'que_evitar' => implode(' | ', [
                    'Contenido estático disfrazado de TikTok',
                    'Copy pesado',
                    'Tono corporativo rígido',
                    'Marca de agua de otras plataformas',
                    'Hashtags de spam',
                ]),
                'tono_recomendado' => 'Dinámico, creativo, ágil, juvenil',
                'max_copy_length'  => 2200,
                'max_hashtags'     => 10,
                'updated_at'       => $now,
            ]);
    }

    public function down()
    {
        // Rollback: restaurar valores del seed original (migration 000007)
        $now = date('Y-m-d H:i:s');

        DB::table('mkt_platform_config')
            ->where('red_social', 'facebook')
            ->update([
                'funcion_editorial_principal' => 'Cercanía, visibilidad, valor comercial, difusión, recordación de marca',
                'que_priorizar' => 'Claridad, piezas visuales entendibles, copy accesible, tono útil y cercano',
                'que_evitar' => 'Exceso de tecnicismo, tono demasiado institucional, copy muy frío o muy extenso',
                'tono_recomendado' => 'humano, cercano, útil',
                'updated_at' => $now,
            ]);

        DB::table('mkt_platform_config')
            ->where('red_social', 'instagram')
            ->update([
                'funcion_editorial_principal' => 'Impacto visual, recordación, atractivo estético, posicionamiento visual de marca',
                'que_priorizar' => 'Buena presencia visual, copy más corto, gancho visual, valor estético de la pieza',
                'que_evitar' => 'Texto muy pesado, pieza muy cargada, mensaje más propio de LinkedIn',
                'tono_recomendado' => 'visual, estético, breve',
                'updated_at' => $now,
            ]);

        DB::table('mkt_platform_config')
            ->where('red_social', 'linkedin')
            ->update([
                'funcion_editorial_principal' => 'Reputación, posicionamiento institucional, valor empresarial, credibilidad B2B',
                'que_priorizar' => 'Enfoque profesional, claridad estratégica, valor corporativo, lenguaje ejecutivo',
                'que_evitar' => 'Tono demasiado informal, contenido promocional, visuales tipo TikTok',
                'tono_recomendado' => 'profesional, ejecutivo, estratégico',
                'updated_at' => $now,
            ]);

        DB::table('mkt_platform_config')
            ->where('red_social', 'tiktok')
            ->update([
                'funcion_editorial_principal' => 'Atención, dinamismo, creatividad, formato corto, impacto inicial',
                'que_priorizar' => 'Gancho, ritmo, idea de video, lógica audiovisual, sonido o música, formato ágil',
                'que_evitar' => 'Contenido estático disfrazado, copy pesado, tono corporativo rígido',
                'tono_recomendado' => 'dinámico, creativo, ágil',
                'updated_at' => $now,
            ]);
    }
}
