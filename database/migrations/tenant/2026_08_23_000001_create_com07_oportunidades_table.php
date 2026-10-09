<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM-07 — Oportunidades de recuperación comercial.
 *
 * Cada fila es un contacto que el proceso automático diario identificó como
 * pendiente de atención, sea porque su Requerimiento quedó en "Cotización caída"
 * o "No cotizado", sea porque la cuenta lleva 8 semanas o más sin actividad
 * comercial.
 *
 * La oportunidad es del CONTACTO, no de la empresa: la actividad de otro
 * contacto de la misma empresa no reinicia la antigüedad del contacto evaluado.
 * La empresa se guarda como contexto para mostrarla y filtrar.
 *
 * ── Sobre los campos de contexto ──
 * `ultima_actividad_at` y `monto_historico` son un RESUMEN, no la fuente de
 * verdad: se derivan cruzando interacciones, requerimientos, cotizaciones y
 * proyectos, y el proceso diario los refresca. Se guardan porque el panel los
 * muestra y ordena por ellos, y recalcularlos en cada consulta obligaría a
 * repetir ese cruce por cada fila de la pantalla.
 *
 * La ANTIGÜEDAD no se guarda: se deriva de `ultima_actividad_at`. Guardarla en
 * días o en rango la dejaría desactualizada entre corridas del proceso.
 *
 * ── Sobre `proxima_atencion_at` ──
 * Es el único dato operativo de "cuándo corresponde actuar". Al detectarse la
 * oportunidad vale la fecha de detección; cuando la ejecutiva programa un
 * seguimiento, pasa a valer esa fecha. La condición Pendiente/Vencido se deriva
 * comparándola con la fecha de hoy, y por eso no existe un estado "vencido"
 * almacenado que pudiera quedar viejo.
 *
 * ── Sobre el responsable ──
 * `responsable_comercial_id` NULL significa "pendiente de asignación": la
 * oportunidad existe y es visible para supervisión, pero no entra al panel de
 * ninguna ejecutiva.
 *
 * Sin foreign keys, como el resto de las tablas del ERP en tenant.
 * Idempotente: el CI corre `tenancy:migrate` en Pruebas y Producción.
 */
class CreateCom07OportunidadesTable extends Migration
{
    public function up()
    {
        if (Schema::connection('tenant')->hasTable('com07_oportunidades')) {
            return;
        }

        Schema::connection('tenant')->create('com07_oportunidades', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ── A quién corresponde ──
            $table->unsignedBigInteger('contacto_id')
                  ->comment('Contacto sobre el que se abre la oportunidad');
            $table->unsignedInteger('empresa_id')->nullable()
                  ->comment('Empresa del contacto, como contexto');

            // ── Por qué se detectó ──
            $table->string('origen', 30)
                  ->comment('cotizacion_caida | no_cotizado | sin_movimiento');
            $table->unsignedInteger('requerimiento_id')->nullable()
                  ->comment('Requerimiento que originó la oportunidad, cuando aplica');

            // ── Quién debe atenderla ──
            $table->unsignedInteger('responsable_comercial_id')->nullable()
                  ->comment('Usuario responsable. NULL = pendiente de asignacion');

            // ── Contexto comercial (resumen refrescado por el proceso diario) ──
            $table->date('ultima_actividad_at')->nullable()
                  ->comment('Fecha de la ultima actividad comercial valida del contacto');
            $table->decimal('monto_historico', 12, 2)->nullable()
                  ->comment('Mayor importe historico entre cotizaciones, requerimientos y proyectos');

            // ── Operativo ──
            $table->date('proxima_atencion_at')->nullable()
                  ->comment('Cuando corresponde atenderla. NULL = sin atencion pendiente');
            $table->string('estado', 20)->default('pendiente')
                  ->comment('pendiente | en_gestion | concluida');

            $table->timestamp('detectada_at')->nullable()
                  ->comment('Cuando la genero el proceso automatico');

            $table->timestamps();

            // Búsqueda del panel y de la vista de supervisión.
            $table->index('contacto_id');
            $table->index('empresa_id');
            $table->index('origen');
            $table->index('responsable_comercial_id');
            $table->index('proxima_atencion_at');
            // Compuesto: es la consulta del proceso diario al comprobar si el
            // contacto ya tiene una oportunidad vigente antes de crear otra.
            $table->index(['contacto_id', 'estado']);
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('com07_oportunidades');
    }
}
