<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ExtendLeadFields extends Migration
{
    public function up()
    {
        Schema::table('lead', function (Blueprint $t) {
            // Nuevos campos (coloca después de columnas existentes seguras)
            if (!Schema::hasColumn('lead','ruc'))                 $t->string('ruc', 14)->nullable()->after('empresa_id');
            if (!Schema::hasColumn('lead','razon_social'))        $t->string('razon_social',191)->nullable()->after('ruc');

            if (!Schema::hasColumn('lead','asunto_email'))        $t->string('asunto_email',191)->nullable()->after('descripcion');
            if (!Schema::hasColumn('lead','adjunto_path'))        $t->string('adjunto_path',255)->nullable()->after('asunto_email');

            if (!Schema::hasColumn('lead','empresa_detectada_id'))   $t->unsignedBigInteger('empresa_detectada_id')->nullable()->after('empresa_id');
            if (!Schema::hasColumn('lead','contacto_detectado_id'))  $t->unsignedBigInteger('contacto_detectado_id')->nullable()->after('empresa_detectada_id');

            if (!Schema::hasColumn('lead','conflicto_detectado'))    $t->boolean('conflicto_detectado')->default(false)->after('contacto_detectado_id');
            if (!Schema::hasColumn('lead','estado_revision'))        $t->string('estado_revision',40)->default('pendiente')->after('conflicto_detectado');
            if (!Schema::hasColumn('lead','procesado'))              $t->boolean('procesado')->default(false)->after('estado_revision');

            // Índices (nombres explícitos)
            $t->index('ruc',             'idx_lead_ruc');
            $t->index('email',           'idx_lead_email');
            $t->index('telefono',        'idx_lead_telefono');  // 👈 en lead es "telefono"
            $t->index('estado_revision', 'idx_lead_estado_rev');
            $t->index('procesado',       'idx_lead_procesado');
        });
    }

    public function down()
    {
        Schema::table('lead', function (Blueprint $t) {
            // Quita índices por nombre
            if (Schema::hasColumn('lead','ruc'))             $t->dropIndex('idx_lead_ruc');
            if (Schema::hasColumn('lead','email'))           $t->dropIndex('idx_lead_email');
            if (Schema::hasColumn('lead','telefono'))        $t->dropIndex('idx_lead_telefono');
            if (Schema::hasColumn('lead','estado_revision')) $t->dropIndex('idx_lead_estado_rev');
            if (Schema::hasColumn('lead','procesado'))       $t->dropIndex('idx_lead_procesado');

            // Quita columnas si existen
            foreach ([
                'ruc','razon_social','asunto_email','adjunto_path',
                'empresa_detectada_id','contacto_detectado_id',
                'conflicto_detectado','estado_revision','procesado',
            ] as $col) {
                if (Schema::hasColumn('lead', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
}
