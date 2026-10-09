<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTextoEnImagenVideoToMktPieces extends Migration
{
    public function up()
    {
        Schema::table('mkt_pieces', function (Blueprint $table) {
            $table->text('texto_en_imagen_video')->nullable()->after('referencia_visual')
                ->comment('Brief del texto que irá superpuesto en la pieza visual (distinto del copy que va como pie de post). Detectado como campo en planner 2024-2025.');
        });
    }

    public function down()
    {
        Schema::table('mkt_pieces', function (Blueprint $table) {
            $table->dropColumn('texto_en_imagen_video');
        });
    }
}
