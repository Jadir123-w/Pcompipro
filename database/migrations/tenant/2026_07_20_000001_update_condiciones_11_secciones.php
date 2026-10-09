<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Modules\Marketing\Support\QuotationCondiciones;

/**
 * COM-02 — Condiciones Generales del PDF: de 7 a 11 secciones (plantilla nueva).
 *
 * Rediseño de Dante sobre `PLATILLA_MAESTRA - General.pdf`. La sección "Términos
 * y Condiciones Generales" pasa de 7 a 11 bloques, todos en formato markdown-lite
 * (1 línea = viñeta, "> " = párrafo, `**` = negrita). Esta migración:
 *
 *   1. UPDATE condicional de las 6 claves existentes a los textos de la plantilla
 *      (solo si el tenant NO editó el default de fábrica → preserva ediciones).
 *   2. Convierte `condiciones_default_consideraciones_entrega` de JSON array a
 *      string markdown-lite (config_type json → string), junto con su valor.
 *   3. Inserta las 4 secciones nuevas (producción, instalación, garantía,
 *      upcycling) con sus textos de la plantilla.
 *
 * Los textos salen de `QuotationCondiciones::factoryDefaults()` (fuente única).
 *
 * El CI corre `tenancy:migrate --force` en Pruebas/Prod. Las cotizaciones ya
 * EMITIDAS no se tocan: guardan su snapshot de condiciones y se renderizan tal
 * cual (el generador rellena las claves nuevas faltantes con '' = fila oculta).
 */
class UpdateCondiciones11Secciones extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');
        $db  = DB::connection('tenant');
        $factory = QuotationCondiciones::factoryDefaults();

        // Defaults de fábrica ANTERIORES (seed 2026_04_20_000002). El UPDATE es
        // condicional a que el valor actual siga siendo este → así no pisamos un
        // texto que el tenant haya personalizado desde la config.
        $oldDefaults = [
            'condiciones_default_costo_servicio'          => 'Expuesto en cuadro superior. Precios incluyen aplicación logo, gastos de envío mas no IGV. No incluye servicio de diseño.',
            'condiciones_default_forma_pago'              => 'A elegir, previa emisión de Orden Compra y/o Servicio: Cancelación crédito a 7 a 15 días, previo envío de Orden Servicio.',
            'condiciones_default_tiempo_ejecucion'        => 'Expuesto en cuadro superior. Antes de iniciar, deberá considerar: Orden de Compra, Arte logo del cliente, Muestra virtual y/o física.',
            'condiciones_default_moneda_texto'            => 'Todos los precios son expresados en Nuevo Soles.',
            'condiciones_default_servicio_complementario' => 'Se cuenta con Seguro Complementario de Trabajo de Riesgo (SCTR).',
            'cotizacion_validez_default'                  => '3 a 5 días útiles, sujeto a variación sin previo aviso.',
        ];

        // config_key existente → clave lógica del factory
        $updatablePairs = [
            'condiciones_default_costo_servicio'          => 'costo_servicio',
            'condiciones_default_forma_pago'              => 'forma_pago',
            'condiciones_default_tiempo_ejecucion'        => 'tiempo_ejecucion',
            'condiciones_default_moneda_texto'            => 'moneda_texto',
            'condiciones_default_servicio_complementario' => 'servicio_complementario',
            'cotizacion_validez_default'                  => 'validez_oferta',
        ];

        // ── 1) UPDATE condicional de las claves existentes ──
        foreach ($updatablePairs as $configKey => $logicKey) {
            if (!isset($factory[$logicKey])) {
                continue;
            }
            $db->table('quotation_config')
                ->where('config_key', $configKey)
                ->where('config_value', $oldDefaults[$configKey]) // anti-clobber
                ->update([
                    'config_value' => $factory[$logicKey],
                    'config_type'  => 'string',
                    'updated_at'   => $now,
                ]);
        }

        // ── 2) consideraciones_entrega: JSON array → string markdown-lite ──
        // Solo si sigue como config de fábrica (config_type = json); si el tenant
        // ya lo tiene como string editado, no se toca.
        $entrega = $db->table('quotation_config')
            ->where('config_key', 'condiciones_default_consideraciones_entrega')
            ->first();
        if ($entrega && $entrega->config_type === 'json') {
            $db->table('quotation_config')
                ->where('config_key', 'condiciones_default_consideraciones_entrega')
                ->update([
                    'config_value' => $factory['consideraciones_entrega'],
                    'config_type'  => 'string',
                    'updated_at'   => $now,
                ]);
        }

        // ── 3) INSERT de las 4 secciones nuevas (solo si no existen) ──
        $nuevas = [
            'condiciones_default_consideraciones_produccion' => ['consideraciones_produccion', 'Condición: Consideraciones de producción'],
            'condiciones_default_terminos_instalacion'       => ['terminos_instalacion',       'Condición: Términos para servicio instalación'],
            'condiciones_default_garantia_servicio'          => ['garantia_servicio',          'Condición: Garantía del Servicio'],
            'condiciones_default_especificacion_upcycling'   => ['especificacion_upcycling',   'Condición: Especificación del servicio UPCYCLING'],
        ];
        foreach ($nuevas as $configKey => $meta) {
            $exists = $db->table('quotation_config')->where('config_key', $configKey)->exists();
            if ($exists) {
                continue;
            }
            $db->table('quotation_config')->insert([
                'config_key'   => $configKey,
                'config_value' => isset($factory[$meta[0]]) ? $factory[$meta[0]] : '',
                'config_type'  => 'string',
                'description'  => $meta[1],
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }
    }

    public function down()
    {
        $db      = DB::connection('tenant');
        $factory = QuotationCondiciones::factoryDefaults();

        // Quitar las 4 claves nuevas.
        $db->table('quotation_config')->whereIn('config_key', [
            'condiciones_default_consideraciones_produccion',
            'condiciones_default_terminos_instalacion',
            'condiciones_default_garantia_servicio',
            'condiciones_default_especificacion_upcycling',
        ])->delete();

        // Revertir consideraciones_entrega a JSON array (default viejo) solo si su
        // valor actual es el markdown-lite que escribió esta migración.
        $oldEntregaJson = '["Gastos de envío incluidos para subtotal mayor a S/.550 en Lima Metropolitana.",'
            . '"Incremento de S/.30 por envío si subtotal es menor a S/.550.",'
            . '"Costo de envío fuera de Lima es asumido por el cliente."]';
        $db->table('quotation_config')
            ->where('config_key', 'condiciones_default_consideraciones_entrega')
            ->where('config_value', $factory['consideraciones_entrega'])
            ->update(['config_value' => $oldEntregaJson, 'config_type' => 'json']);

        // Los textos actualizados (costo, forma_pago, tiempo, moneda, servicio,
        // validez) NO se auto-revierten: no es seguro distinguir una edición
        // manual del texto de la plantilla.
    }
}
