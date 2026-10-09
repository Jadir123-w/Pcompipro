<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Tabla quotation_proposals — Salida Comercial Derivada (Doc Funcional COM-02 §26)
 *
 * Relación 1:1 con quotation_prequotes. Representa la cotización FORMAL que se
 * envía al cliente (equivalente al PDF externo que hoy COMPIPRO arma del Excel).
 *
 * Al crear la proposal desde una prequote, la IA (QuotationAIEngine) pre-llena
 * cabecera, destinatario, notas, condiciones y decide si incluir datos
 * bancarios. La ejecutiva solo revisa y ajusta.
 *
 * Una vez emitida, la proposal es inmutable (snapshot): aunque se edite la
 * prequote, la proposal no cambia — mantiene integridad del documento enviado.
 */
class CreateQuotationProposalsTable extends Migration
{
    public function up()
    {
        Schema::connection('tenant')->create('quotation_proposals', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── Relación con prequote (1:1) ──
            $table->unsignedBigInteger('prequote_id')->unique()
                ->comment('FK 1:1 a quotation_prequotes');

            // ── Numeración comercial ──
            // Formato: {prefijo}{YYMMDD}-{secuencial_dia}_{version_externa}
            // Ej: MAP240917-07_03 = prefijo MAP + 17-sep-2024 + 7mo del día + versión 3
            $table->string('numero_cotizacion', 30)->unique()
                ->comment('Número completo de la cotización, ej: MAP240917-07_03');
            $table->string('prefijo_numeracion', 10)->default('MAP')
                ->comment('Prefijo configurable, default MAP');
            $table->date('fecha_emision')
                ->comment('Fecha que aparece en el PDF');
            $table->integer('secuencial_dia')->default(1)
                ->comment('Número secuencial dentro del día de emisión');
            $table->integer('version_externa')->default(1)
                ->comment('Versión del documento externo (recotizaciones _02, _03...)');

            // ── Destinatario (snapshot — copiado al emitir) ──
            $table->string('destinatario_nombre', 255)->nullable()
                ->comment('Ej: Raysa Reyes Valencia');
            $table->string('destinatario_cargo', 255)->nullable()
                ->comment('Ej: Encargada de Gestión de Activos');
            $table->string('destinatario_sede', 255)->nullable()
                ->comment('Ej: Perú - Parque Eólico San Juan');
            $table->string('destinatario_empresa', 255)->nullable()
                ->comment('Razón social del cliente, snapshot. Ej: ACCIONA PERÚ');

            // ── Contenido editorial ──
            $table->text('titulo_descriptivo')->nullable()
                ->comment('Ej: RECOTIZACIÓN PROPUESTA_ACCIONA_Libretas eco, Bolsas notex_17.09.24');

            $table->text('notas_encabezado')->nullable()
                ->comment('JSON: 3 líneas de notas arriba de la tabla de productos');

            // ── Totales y moneda ──
            $table->boolean('precios_incluyen_igv')->default(false)
                ->comment('Si TRUE, el PDF no muestra fila separada de IGV');

            $table->char('moneda', 3)->default('PEN')
                ->comment('PEN o USD (Etapa 1 solo PEN)');

            // ── Condiciones generales (7 bloques estándar) ──
            $table->text('condiciones')->nullable()
                ->comment('JSON: {costo_servicio, forma_pago, tiempo_ejecucion, moneda_texto, validez_oferta, consideraciones_entrega, servicio_complementario}');

            // ── Flags para bloques opcionales del footer ──
            $table->boolean('incluye_datos_empresa')->default(true)
                ->comment('Si TRUE, muestra bloque DATOS DE LA EMPRESA (razón social + RUC + dir + tel)');
            $table->boolean('incluye_datos_bancarios')->default(false)
                ->comment('Si TRUE, muestra bloque NÚMERO DE CUENTA');

            $table->unsignedInteger('bank_account_id')->nullable()
                ->comment('FK a bank_accounts del ERP core (solo si incluye_datos_bancarios=true)');

            // ── Firma ──
            $table->unsignedInteger('ejecutiva_id')->nullable()
                ->comment('FK a users — quién firma el PDF');

            // ── PDF generado ──
            $table->string('pdf_path', 500)->nullable()
                ->comment('Ruta del PDF en storage');

            // ── Estado del documento externo ──
            $table->string('estado', 30)->default('borrador')
                ->comment('borrador, emitida, enviada_cliente, aprobada_cliente, rechazada_cliente');

            $table->timestamp('emitida_at')->nullable()
                ->comment('Timestamp de cuando se congeló el snapshot y se emitió al cliente');

            // ── Metadata IA ──
            $table->string('ai_provider', 30)->nullable()
                ->comment('Proveedor de IA usado para pre-llenar la proposal');
            $table->string('ai_model', 60)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ── Índices ──
            $table->index('prequote_id');
            $table->index('estado');
            $table->index('fecha_emision');
            $table->index('ejecutiva_id');
            $table->index('bank_account_id');
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('quotation_proposals');
    }
}
