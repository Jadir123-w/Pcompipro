<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Detalle de contacto" (contacto.tipo_contacto) pasa de VARCHAR(255) a TEXT:
 * en el formulario es un cuadro de texto y no debe cortarse a 255 caracteres.
 * Cambiar a TEXT conserva el contenido.
 */
class ChangeTipoContactoToTextOnContacto extends Migration
{
    public function up()
    {
        if ($this->tipoActual() !== 'text') {
            DB::statement('ALTER TABLE contacto MODIFY tipo_contacto TEXT NULL');
        }
    }

    /**
     * Vuelve a VARCHAR(255). Falla si hay detalles de más de 255 caracteres (modo estricto):
     * en ese caso hay que acortarlos antes de revertir.
     */
    public function down()
    {
        if ($this->tipoActual() === 'text') {
            DB::statement('ALTER TABLE contacto MODIFY tipo_contacto VARCHAR(255) NULL');
        }
    }

    private function tipoActual()
    {
        $fila = DB::selectOne(
            'SELECT DATA_TYPE AS tipo FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            ['contacto', 'tipo_contacto']
        );

        return $fila ? strtolower($fila->tipo) : null;
    }
}
