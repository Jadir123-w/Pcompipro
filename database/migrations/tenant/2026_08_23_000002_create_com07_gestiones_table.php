<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM-07 — Gestiones registradas sobre una oportunidad.
 *
 * Cada fila es una atención realizada por la ejecutiva: qué resultado obtuvo,
 * qué observó y, cuando corresponde, qué acción sigue y en qué fecha.
 *
 * ── La tabla es de sólo agregar ──
 * Una gestión no se edita ni se borra: cada atención suma una fila. Así el
 * historial del contacto se reconstruye leyendo la secuencia, sin necesidad de
 * una bitácora de auditoría aparte.
 *
 * ── Sobre el seguimiento ──
 * El seguimiento no es una entidad separada porque siempre nace de una gestión:
 * es la respuesta a "¿y ahora qué?". Vive en las columnas `seguimiento_*` de la
 * gestión que lo programó.
 *
 * `seguimiento_estado` guarda sólo las tres condiciones que dependen de una
 * decisión humana —pendiente, atendido, cancelado—. La cuarta, VENCIDO, no se
 * guarda: se deriva comparando `seguimiento_at` con la fecha de hoy. Guardarla
 * exigiría un proceso que recorriera la tabla cada día sólo para mover un
 * estado, y entre corridas quedaría mintiendo.
 *
 * Una reprogramación conserva la fecha previa en `seguimiento_at_anterior` y
 * escribe la nueva en `seguimiento_at`; una cancelación conserva el motivo. En
 * ninguno de los dos casos se pierde el registro.
 *
 * Sin foreign keys, como el resto de las tablas del ERP en tenant.
 * Idempotente: el CI corre `tenancy:migrate` en Pruebas y Producción.
 */
class CreateCom07GestionesTable extends Migration
{
    public function up()
    {
        if (Schema::connection('tenant')->hasTable('com07_gestiones')) {
            return;
        }

        Schema::connection('tenant')->create('com07_gestiones', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('oportunidad_id')
                  ->comment('Oportunidad sobre la que se registro la gestion');
            $table->unsignedInteger('user_id')->nullable()
                  ->comment('Usuario que realizo la gestion');

            // ── Resultado de la atención ──
            $table->string('resultado', 40)
                  ->comment('sin_respuesta | requiere_nuevo_seguimiento | existe_interes | necesidad_futura | requiere_continuidad | concluida');
            $table->text('observacion')->nullable()
                  ->comment('Contexto para poder retomar la gestion despues');
            $table->string('proxima_accion', 255)->nullable()
                  ->comment('Que corresponde hacer a continuacion');

            // ── Seguimiento programado por esta gestión ──
            $table->date('seguimiento_at')->nullable()
                  ->comment('Fecha del siguiente seguimiento. NULL = la gestion no dejo continuidad');
            $table->date('seguimiento_at_anterior')->nullable()
                  ->comment('Fecha previa, cuando el seguimiento fue reprogramado');
            $table->string('seguimiento_estado', 20)->nullable()
                  ->comment('pendiente | atendido | cancelado. NULL = no programo seguimiento');
            $table->string('seguimiento_motivo', 255)->nullable()
                  ->comment('Por que dejo de aplicar, cuando fue cancelado');

            $table->timestamps();

            $table->index('oportunidad_id');
            $table->index('user_id');
            $table->index('seguimiento_at');
            // Consulta de la vista de supervisión: seguimientos vigentes por fecha.
            $table->index(['seguimiento_estado', 'seguimiento_at']);
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('com07_gestiones');
    }
}
