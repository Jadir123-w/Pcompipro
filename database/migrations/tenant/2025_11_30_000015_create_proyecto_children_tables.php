<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProyectoChildrenTables extends Migration
{
    public function up()
    {
        // HISTORIAL
        if (!Schema::hasTable('proyecto_historial')) {
            Schema::create('proyecto_historial', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('proyecto_id'); // <- INT UNSIGNED
                $table->string('etapa', 100);
                $table->string('motivo', 255)->nullable();
                $table->unsignedInteger('usuario_id')->nullable(); // opcional, mantengo INT para homogeneidad
                $table->dateTime('created_at')->useCurrent();

                $table->index('proyecto_id', 'idx_hist_proyecto');
                $table->index('etapa', 'idx_hist_etapa');
                $table->foreign('proyecto_id')->references('id')->on('proyecto')
                      ->onUpdate('cascade')->onDelete('cascade');
            });
        }

        // INTERACCIONES
        if (!Schema::hasTable('proyecto_interaccion')) {
            Schema::create('proyecto_interaccion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('proyecto_id'); // <- INT UNSIGNED
                $table->enum('canal', ['Llamada','WhatsApp','Email','Reunión']);
                $table->text('resumen');
                $table->dateTime('proximo_seguimiento')->nullable();
                $table->unsignedInteger('usuario_id')->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->index('proyecto_id', 'idx_int_proyecto');
                $table->index('proximo_seguimiento', 'idx_int_prox');
                $table->foreign('proyecto_id')->references('id')->on('proyecto')
                      ->onUpdate('cascade')->onDelete('cascade');
            });
        }

        // DOCUMENTOS
        if (!Schema::hasTable('proyecto_documento')) {
            Schema::create('proyecto_documento', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('proyecto_id'); // <- INT UNSIGNED
                $table->string('tipo', 50);
                $table->string('nombre', 255);
                $table->text('url');
                $table->string('version', 50)->nullable();
                $table->string('observacion', 255)->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->index('proyecto_id', 'idx_doc_proyecto');
                $table->index('tipo', 'idx_doc_tipo');
                $table->foreign('proyecto_id')->references('id')->on('proyecto')
                      ->onUpdate('cascade')->onDelete('cascade');
            });
        }

        // HITOS
        if (!Schema::hasTable('proyecto_hito')) {
            Schema::create('proyecto_hito', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('proyecto_id'); // <- INT UNSIGNED
                $table->string('nombre', 150);
                $table->date('fecha_planeada')->nullable();
                $table->date('fecha_real')->nullable();
                $table->enum('estado', ['Pendiente','En proceso','Completado','Retrasado'])->default('Pendiente');
                $table->dateTime('created_at')->useCurrent();

                $table->index('proyecto_id', 'idx_hito_proyecto');
                $table->index('estado', 'idx_hito_estado');
                $table->foreign('proyecto_id')->references('id')->on('proyecto')
                      ->onUpdate('cascade')->onDelete('cascade');
            });
        }

        // ENTREGAS
        if (!Schema::hasTable('proyecto_entrega')) {
            Schema::create('proyecto_entrega', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('proyecto_id'); // <- INT UNSIGNED
                $table->string('descripcion', 255);
                $table->date('fecha_programada')->nullable();
                $table->date('fecha_entrega')->nullable();
                $table->enum('estado', ['Pendiente','Parcial','Entregado','Observado'])->default('Pendiente');
                $table->string('recibido_por', 150)->nullable();
                $table->string('observacion', 255)->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->index('proyecto_id', 'idx_ent_proyecto');
                $table->index('estado', 'idx_ent_estado');
                $table->foreign('proyecto_id')->references('id')->on('proyecto')
                      ->onUpdate('cascade')->onDelete('cascade');
            });
        }

        // FACTURACIÓN / COBROS
        if (!Schema::hasTable('proyecto_facturacion')) {
            Schema::create('proyecto_facturacion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('proyecto_id'); // <- INT UNSIGNED
                $table->enum('tipo_doc', ['Factura','Boleta','Nota']);
                $table->string('numero', 100)->nullable();
                $table->date('fecha_emision')->nullable();
                $table->decimal('monto', 12, 2);
                $table->decimal('porcentaje', 6, 3)->nullable();
                $table->enum('estado_cobro', ['Pendiente','Parcial','Cancelado'])->default('Pendiente');
                $table->date('fecha_cobro')->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->index('proyecto_id', 'idx_fac_proyecto');
                $table->index('estado_cobro', 'idx_fac_estado');
                $table->foreign('proyecto_id')->references('id')->on('proyecto')
                      ->onUpdate('cascade')->onDelete('cascade');
            });
        }

        // RIESGOS
        if (!Schema::hasTable('proyecto_riesgo')) {
            Schema::create('proyecto_riesgo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('proyecto_id'); // <- INT UNSIGNED
                $table->string('categoria', 100)->nullable();
                $table->enum('severidad', ['Baja','Media','Alta'])->default('Media');
                $table->string('descripcion', 255);
                $table->enum('estado', ['Abierto','Mitigando','Cerrado'])->default('Abierto');
                $table->string('accion_mitigacion', 255)->nullable();
                $table->dateTime('created_at')->useCurrent();

                $table->index('proyecto_id', 'idx_rie_proyecto');
                $table->index('estado', 'idx_rie_estado');
                $table->foreign('proyecto_id')->references('id')->on('proyecto')
                      ->onUpdate('cascade')->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        foreach ([
            'proyecto_riesgo',
            'proyecto_facturacion',
            'proyecto_entrega',
            'proyecto_hito',
            'proyecto_documento',
            'proyecto_interaccion',
            'proyecto_historial',
        ] as $tbl) {
            if (Schema::hasTable($tbl)) {
                Schema::drop($tbl);
            }
        }
    }
}
