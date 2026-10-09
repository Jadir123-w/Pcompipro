<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Seed de defaults COMPIPRO para COM-02:
 *
 *  1. Inserta claves de configuración en `quotation_config` con los datos
 *     fijos de la empresa (razón social, RUC, dirección, teléfonos, URLs),
 *     plantillas de saludo/despedida y condiciones default.
 *
 *  2. Inserta la cuenta bancaria BCP Soles de COMPIPRO en `bank_accounts`
 *     del ERP core (solo si no existe), para que la proposal pueda
 *     referenciarla cuando incluya el bloque de datos bancarios.
 *
 * Los valores son extraídos de los PDFs reales del cliente (MAP240917 de
 * ACCIONA y MAP250327 de BSF), revisados el 20-abr-2026.
 */
class SeedCompiproDefaults extends Migration
{
    public function up()
    {
        $now = date('Y-m-d H:i:s');

        /*
        |--------------------------------------------------------------------------
        | 1) Defaults en quotation_config
        |--------------------------------------------------------------------------
        */
        $defaults = [
            // ── Identidad de la empresa ──
            ['empresa_razon_social', 'GRUPO COMPINA SAC', 'string', 'Razón social de la empresa facturadora'],
            ['empresa_ruc', '20537420252', 'string', 'RUC de COMPIPRO / GRUPO COMPINA'],
            ['empresa_direccion', 'Cal. Coyllur Nro. 176 Urb. Zarate Lima - Lima - San Juan De Lurigancho', 'string', 'Dirección fiscal'],
            ['empresa_telefono', '7804649 / 7926135', 'string', 'Teléfonos principales'],
            ['empresa_email_info', 'info@compina.net', 'string', 'Email corporativo del pie de página'],
            ['empresa_web_principal', 'www.compina.net', 'string', 'Web principal'],
            ['empresa_catalogo_url', 'http://compipro.com/presentacion/catalogo_compipro.pdf', 'string', 'URL del catálogo online'],
            ['empresa_facebook', 'COMPIPRO', 'string', 'Nombre de la página de Facebook'],

            // ── Numeración de cotizaciones ──
            ['cotizacion_prefijo_default', 'MAP', 'string', 'Prefijo default del número de cotización (editable por cotización)'],
            ['cotizacion_prefijos_permitidos', '["MAP"]', 'json', 'Lista de prefijos válidos (se ampliará cuando el cliente confirme)'],

            // ── Parámetros comerciales ──
            ['cotizacion_validez_default', '3 a 5 días útiles, sujeto a variación sin previo aviso.', 'string', 'Validez de la oferta por defecto'],
            ['cotizacion_envio_umbral_lima', '550', 'decimal', 'Monto mínimo (S/) para envío gratis Lima Metropolitana'],
            ['cotizacion_envio_adicional', '30', 'decimal', 'Costo adicional de envío (S/) si subtotal < umbral'],

            // ── Plantillas de texto para el PDF ──
            ['plantilla_saludo',
                'Me es grato dirigirme a Ud. con el fin de saludarle y poner a su consideración:',
                'string',
                'Texto fijo después del destinatario',
            ],
            ['plantilla_despedida',
                'Esperando que esta propuesta sea de su agrado, quedo a la espera de sus apreciables comentarios. '.
                'Aprovecho la oportunidad a invitarle a visitar nuestro catálogo online '.
                'http://compipro.com/presentacion/catalogo_compipro.pdf o nuestra página en FACEBOOK como COMPIPRO.',
                'string',
                'Texto fijo al final del PDF antes de la firma',
            ],

            // ── Condiciones default (7 bloques) ──
            ['condiciones_default_costo_servicio',
                'Expuesto en cuadro superior. Precios incluyen aplicación logo, gastos de envío mas no IGV. No incluye servicio de diseño.',
                'string',
                'Condición: Costo del Servicio',
            ],
            ['condiciones_default_forma_pago',
                'A elegir, previa emisión de Orden Compra y/o Servicio: Cancelación crédito a 7 a 15 días, previo envío de Orden Servicio.',
                'string',
                'Condición: Forma de Pago',
            ],
            ['condiciones_default_tiempo_ejecucion',
                'Expuesto en cuadro superior. Antes de iniciar, deberá considerar: Orden de Compra, Arte logo del cliente, Muestra virtual y/o física.',
                'string',
                'Condición: Tiempo de ejecución',
            ],
            ['condiciones_default_moneda_texto',
                'Todos los precios son expresados en Nuevo Soles.',
                'string',
                'Condición: Moneda (texto)',
            ],
            ['condiciones_default_consideraciones_entrega',
                '["Gastos de envío incluidos para subtotal mayor a S/.550 en Lima Metropolitana.",'
                .'"Incremento de S/.30 por envío si subtotal es menor a S/.550.",'
                .'"Costo de envío fuera de Lima es asumido por el cliente."]',
                'json',
                'Condición: Consideraciones para Entrega (array de bullets)',
            ],
            ['condiciones_default_servicio_complementario',
                'Se cuenta con Seguro Complementario de Trabajo de Riesgo (SCTR).',
                'string',
                'Condición: Servicio complementario',
            ],
        ];

        // Forzar conexión tenant (la default 'system' no ve las tablas del tenant)
        $db = DB::connection('tenant');

        foreach ($defaults as $d) {
            $exists = $db->table('quotation_config')->where('config_key', $d[0])->exists();
            if ($exists) continue;

            $db->table('quotation_config')->insert([
                'config_key'   => $d[0],
                'config_value' => $d[1],
                'config_type'  => $d[2],
                'description'  => $d[3],
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | 2) Cuenta bancaria BCP Cta. Cte. Soles de COMPIPRO
        |--------------------------------------------------------------------------
        | Solo insertar si:
        |   - BCP existe en tabla `banks` (id esperado: 2)
        |   - No hay aún cuenta con ese número en bank_accounts
        */
        $bcp = $db->table('banks')
            ->where('description', 'LIKE', '%CREDITO%DEL%PERU%')
            ->orWhere('description', 'LIKE', '%BCP%')
            ->first();

        if ($bcp) {
            $cuentaExiste = $db->table('bank_accounts')
                ->where('number', '191 1917216 0 17')
                ->exists();

            if (!$cuentaExiste) {
                $penExists = $db->table('cat_currency_types')->where('id', 'PEN')->exists();
                if (!$penExists) {
                    $firstCurrency = $db->table('cat_currency_types')->first();
                    $currencyId = $firstCurrency ? $firstCurrency->id : 'PEN';
                } else {
                    $currencyId = 'PEN';
                }

                $db->table('bank_accounts')->insert([
                    'bank_id'             => $bcp->id,
                    'description'         => 'BCP Cta. Cte. Nuevos Soles — COMPIPRO (GRUPO COMPINA SAC)',
                    'number'              => '191 1917216 0 17',
                    'cci'                 => '002 191 001917216017 58',
                    'currency_type_id'    => $currencyId,
                    'status'              => 1,
                    'initial_balance'     => 0,
                    'show_in_documents'   => 1,
                    'establishment_id'    => null,
                ]);
            }
        }
    }

    public function down()
    {
        // Eliminar solo las claves insertadas por este seed (para preservar otras)
        $keys = [
            'empresa_razon_social', 'empresa_ruc', 'empresa_direccion', 'empresa_telefono',
            'empresa_email_info', 'empresa_web_principal', 'empresa_catalogo_url', 'empresa_facebook',
            'cotizacion_prefijo_default', 'cotizacion_prefijos_permitidos',
            'cotizacion_validez_default', 'cotizacion_envio_umbral_lima', 'cotizacion_envio_adicional',
            'plantilla_saludo', 'plantilla_despedida',
            'condiciones_default_costo_servicio', 'condiciones_default_forma_pago',
            'condiciones_default_tiempo_ejecucion', 'condiciones_default_moneda_texto',
            'condiciones_default_consideraciones_entrega', 'condiciones_default_servicio_complementario',
        ];

        $db = DB::connection('tenant');

        $db->table('quotation_config')->whereIn('config_key', $keys)->delete();

        // Eliminar cuenta bancaria COMPIPRO solo si fue insertada por este seed
        $db->table('bank_accounts')
            ->where('number', '191 1917216 0 17')
            ->delete();
    }
}
