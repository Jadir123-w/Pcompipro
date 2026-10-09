<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed de ferias típicas peruanas del rubro merchandising / publicidad
 * para que MKT-03 pueda demostrar el Modo F (§13.6 doc funcional) sin
 * tener que esperar a que el agente de ferias externo esté construido.
 *
 * Las 5 ferias se sembran con `source = 'manual'` y `external_feria_id`
 * vacío para no chocar con futuras notificaciones del agente externo
 * (que usará `source = 'agente_ferias'` y un external_id propio).
 *
 * Idempotente: si una feria con el mismo nombre + fecha ya existe, no
 * se duplica. Se puede correr `tenancy:migrate` varias veces.
 */
class SeedMktFeriasDemo extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        // 5 ferias del rubro merchandising/publicidad/textil en Perú.
        // Fechas en futuro relativo a la fecha del seed (Q4 2026 + 2027) para
        // que el cálculo de fase editorial activa retorne valores útiles.
        $ferias = [
            [
                'nombre'                  => 'EXPOMARKETING 2026',
                'fecha'                   => '2026-10-15',
                'sector'                  => 'Marketing B2B y publicidad',
                'ciudad'                  => 'Lima',
                'tipo_participacion'      => 'expositor',
                'objetivo_comunicacional' => 'Visibilidad de marca COMPIPRO entre agencias y empresas',
                'enfoque_sugerido'        => 'Posicionar a COMPIPRO como aliado estratégico en merchandising corporativo sostenible',
                'redes_sociales_target'   => ['facebook', 'instagram', 'linkedin'],
            ],
            [
                'nombre'                  => 'EXPOTEXTIL PERU 2026',
                'fecha'                   => '2026-11-10',
                'sector'                  => 'Textil y confección',
                'ciudad'                  => 'Lima',
                'tipo_participacion'      => 'expositor',
                'objetivo_comunicacional' => 'Mostrar la línea textil de COMPIPRO',
                'enfoque_sugerido'        => 'Destacar polos, casacas y uniformes corporativos con foco en calidad y personalización',
                'redes_sociales_target'   => ['facebook', 'instagram', 'linkedin', 'tiktok'],
            ],
            [
                'nombre'                  => 'EXPO CORPORATE GIFTS 2027',
                'fecha'                   => '2027-04-15',
                'sector'                  => 'Merchandising corporativo y regalos publicitarios',
                'ciudad'                  => 'Lima',
                'tipo_participacion'      => 'expositor + auspicio',
                'objetivo_comunicacional' => 'Liderazgo en kits corporativos premium',
                'enfoque_sugerido'        => 'Casos reales de fidelización con kits corporativos y artículos premium',
                'redes_sociales_target'   => ['facebook', 'instagram', 'linkedin'],
            ],
            [
                'nombre'                  => 'PERU MODA 2027',
                'fecha'                   => '2027-07-25',
                'sector'                  => 'Moda, textil y diseño',
                'ciudad'                  => 'Lima',
                'tipo_participacion'      => 'visitante',
                'objetivo_comunicacional' => 'Estar presentes en la conversación de moda y textil',
                'enfoque_sugerido'        => 'Tendencias en uniformes corporativos y la línea textil COMPIPRO',
                'redes_sociales_target'   => ['instagram', 'linkedin'],
            ],
            [
                'nombre'                  => 'FERIA DEL EMPRENDEDOR 2027',
                'fecha'                   => '2027-08-18',
                'sector'                  => 'Networking PyMEs',
                'ciudad'                  => 'Lima',
                'tipo_participacion'      => 'expositor',
                'objetivo_comunicacional' => 'Captar PyMEs como clientes corporativos',
                'enfoque_sugerido'        => 'Soluciones promocionales para emprendedores y PyMEs en crecimiento',
                'redes_sociales_target'   => ['facebook', 'linkedin'],
            ],
        ];

        foreach ($ferias as $f) {
            // Idempotencia: si ya existe una feria con este nombre + fecha, skip
            $exists = DB::table('mkt_ferias_received')
                ->where('nombre', $f['nombre'])
                ->where('fecha', $f['fecha'])
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('mkt_ferias_received')->insert([
                'nombre'                   => $f['nombre'],
                'fecha'                    => $f['fecha'],
                'sector'                   => $f['sector'],
                'ciudad'                   => $f['ciudad'],
                'tipo_participacion'       => $f['tipo_participacion'],
                'objetivo_comunicacional'  => $f['objetivo_comunicacional'],
                'enfoque_sugerido'         => $f['enfoque_sugerido'],
                'contexto_interno'         => null,
                'fase_editorial_actual'    => null,
                'piezas_2m_generadas'      => 0,
                'piezas_1m_generadas'      => 0,
                'piezas_15d_generadas'     => 0,
                'source'                   => 'manual',
                'external_feria_id'        => null,
                'redes_sociales_target'    => json_encode($f['redes_sociales_target']),
                'estado'                   => 'activa',
                'created_at'               => $now,
                'updated_at'               => $now,
            ]);
        }
    }

    public function down()
    {
        // No se hace rollback automático: las ferias seed son data demo y
        // pueden haber sido modificadas por el usuario después del seed.
        // Si se requiere limpiar, hacerlo manualmente con SQL.
    }
}
