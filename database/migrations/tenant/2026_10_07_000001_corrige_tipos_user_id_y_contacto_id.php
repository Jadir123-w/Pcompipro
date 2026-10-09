<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige dos columnas cuyo tipo no coincide con la clave primaria a la que
 * apuntan, lo que impedía crear sus claves foráneas:
 *
 *   - `empresa.user_id`            BIGINT UNSIGNED → INT UNSIGNED
 *     (`users.id` es INT UNSIGNED)
 *   - `requerimiento.contacto_id`  INT UNSIGNED → BIGINT UNSIGNED
 *     (`contacto.id` es BIGINT UNSIGNED)
 *
 * Las migraciones originales que crearon esas columnas ya quedaron corregidas,
 * de modo que una base nueva nace con los tipos correctos y sus claves foráneas;
 * esta migración solo actúa sobre las bases que ya existían.
 *
 * No modifica datos: si encuentra referencias huérfanas que impedirían crear la
 * clave foránea, deja constancia en el log y continúa sin crearla.
 */
class CorrigeTiposUserIdYContactoId extends Migration
{
    public function up()
    {
        $this->ajustaTipo('empresa', 'user_id', 'bigint', 'INT UNSIGNED NULL');
        $this->creaForanea('empresa', 'user_id', 'users', 'fk_empresa_user');

        $this->ajustaTipo('requerimiento', 'contacto_id', 'int', 'BIGINT UNSIGNED NULL');
        $this->creaForanea('requerimiento', 'contacto_id', 'contacto', 'fk_requerimiento_contacto');
    }

    public function down()
    {
        // Sin reversa: devolver las columnas a su tipo anterior reintroduciría
        // el problema que esta migración corrige.
    }

    /**
     * Conexión del tenant en curso.
     */
    private function conexion()
    {
        return DB::connection('tenant');
    }

    /**
     * Tipo de dato actual de una columna, en minúsculas (int, bigint, ...).
     */
    private function tipoActual($tabla, $columna)
    {
        $fila = $this->conexion()->selectOne(
            'SELECT DATA_TYPE AS tipo
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->conexion()->getDatabaseName(), $tabla, $columna]
        );

        return $fila ? strtolower($fila->tipo) : null;
    }

    /**
     * Cambia el tipo de la columna solo si hoy tiene el tipo incorrecto.
     */
    private function ajustaTipo($tabla, $columna, $tipoIncorrecto, $definicion)
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable($tabla) || !$schema->hasColumn($tabla, $columna)) {
            return;
        }

        if ($this->tipoActual($tabla, $columna) !== $tipoIncorrecto) {
            return;
        }

        try {
            $this->conexion()->statement("ALTER TABLE `{$tabla}` MODIFY `{$columna}` {$definicion}");
        } catch (\Exception $e) {
            \Log::warning("No se pudo ajustar el tipo de {$tabla}.{$columna}: " . $e->getMessage());
        }
    }

    /**
     * Crea la clave foránea si no existe y si no hay referencias huérfanas.
     */
    private function creaForanea($tabla, $columna, $tablaReferida, $nombre)
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable($tabla) ||
            !$schema->hasTable($tablaReferida) ||
            !$schema->hasColumn($tabla, $columna)) {
            return;
        }

        if ($this->foraneaExiste($tabla, $nombre)) {
            return;
        }

        try {
            $huerfanos = $this->conexion()->selectOne(
                "SELECT COUNT(*) AS total
                   FROM `{$tabla}` t
                   LEFT JOIN `{$tablaReferida}` r ON r.id = t.`{$columna}`
                  WHERE t.`{$columna}` IS NOT NULL AND r.id IS NULL"
            );

            if ($huerfanos && (int) $huerfanos->total > 0) {
                \Log::warning(
                    "No se creó la clave foránea {$nombre}: {$huerfanos->total} registro(s) de " .
                    "{$tabla}.{$columna} no tienen correspondencia en {$tablaReferida}."
                );
                return;
            }

            $this->conexion()->statement(
                "ALTER TABLE `{$tabla}`
                    ADD CONSTRAINT `{$nombre}` FOREIGN KEY (`{$columna}`)
                    REFERENCES `{$tablaReferida}` (`id`) ON DELETE SET NULL"
            );
        } catch (\Exception $e) {
            \Log::warning("No se pudo crear la clave foránea {$nombre}: " . $e->getMessage());
        }
    }

    /**
     * ¿Ya existe esa clave foránea en la tabla?
     */
    private function foraneaExiste($tabla, $nombre)
    {
        $fila = $this->conexion()->selectOne(
            "SELECT CONSTRAINT_NAME
               FROM information_schema.TABLE_CONSTRAINTS
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = ?
                AND CONSTRAINT_NAME = ?
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$this->conexion()->getDatabaseName(), $tabla, $nombre]
        );

        return $fila !== null;
    }
}
