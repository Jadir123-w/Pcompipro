<?php

namespace App\Console\Commands;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Comercial\Support\Telefono;

/**
 * Genera (sin escribir en la BD) el SQL que normaliza teléfonos y correos de contactos,
 * y un reporte de revisión. El SQL lo ejecuta una persona, después de un backup y con la
 * migración 2026_10_20_000001_add_deleted_at_to_contacto_telefonos ya aplicada.
 *
 * Los archivos incluyen datos personales: guardarlos fuera de carpetas públicas y borrarlos
 * después de usarlos.
 */
class ContactosNormalizacionSqlCommand extends Command
{
    protected $signature = 'contactos:normalizacion-sql
                            {--tenant= : ID del website (empresa). Sin valor: todas}
                            {--salida= : Carpeta de salida (por defecto storage/app/contactos-normalizacion)}';

    protected $description = 'Genera el SQL de normalización de teléfonos/correos de contactos y un reporte (solo lectura)';

    // Separadores reales de varios números en un mismo campo.
    const PATRON_VARIOS = '/\s*[\/;,|]\s*|\s+y\s+|\s+o\s+/u';

    public function handle()
    {
        $salida = $this->option('salida') ?: storage_path('app/contactos-normalizacion');
        if (!is_dir($salida) && !mkdir($salida, 0750, true)) {
            $this->error("No se pudo crear la carpeta {$salida}");
            return 1;
        }

        $websites = $this->option('tenant') ? Website::where('id', $this->option('tenant'))->get() : Website::all();
        if ($websites->isEmpty()) {
            $this->error('No hay empresas para procesar.');
            return 1;
        }

        foreach ($websites as $website) {
            app(Environment::class)->tenant($website);
            $db = DB::connection('tenant');
            $db->statement('SET SESSION TRANSACTION READ ONLY');

            [$sql, $reporte, $resumen] = $this->generar($db, $website->uuid);
            $base = $salida . DIRECTORY_SEPARATOR . 'contactos-' . $website->uuid . '-' . date('Ymd-His');
            file_put_contents($base . '.sql', $sql);
            file_put_contents($base . '-reporte.md', $reporte);

            $this->info("{$website->uuid}: {$resumen}");
            $this->line("  SQL: {$base}.sql");
            $this->line("  Reporte: {$base}-reporte.md");
        }

        return 0;
    }

    /**
     * @return array [sql, reporte, resumen]
     */
    private function generar($db, $bd)
    {
        $q = function ($v) use ($db) {
            return $v === null ? 'NULL' : $db->getPdo()->quote($v);
        };
        $sql = [];
        $rep = ['separar' => [], 'revisar_varios' => [], 'no_reconocidos' => [], 'repetidos_mismo' => 0];
        $cambios = ['telefonos' => 0, 'insertados' => 0, 'directos' => 0, 'bajas_repetidos' => 0];

        $telefonos = $db->table('contacto_telefonos')->orderBy('contacto_id')->orderBy('id')
            ->get(['id', 'contacto_id', 'telefono', 'tipo', 'estado']);

        // 1) Valor final de cada fila existente y números extra a separar.
        $finales = [];
        $extras = [];
        foreach ($telefonos as $t) {
            $partes = preg_split(self::PATRON_VARIOS, (string) $t->telefono, -1, PREG_SPLIT_NO_EMPTY);
            $varios = count($partes) > 1;

            // Solo se separa si cada parte es un número completo (6+ dígitos); si no, se revisa a mano.
            if ($varios) {
                foreach ($partes as $p) {
                    if (strlen(preg_replace('/\D/', '', Telefono::separarAnexo($p)['numero'])) < 6) {
                        $rep['revisar_varios'][] = [$t->contacto_id, $t->telefono];
                        $finales[] = [(int) $t->id, (int) $t->contacto_id, $t->telefono, (int) $t->estado];
                        continue 2;
                    }
                }
            }

            $normalizados = array_values(array_unique(array_map(function ($p) {
                return Telefono::normalizar($p);
            }, $varios ? $partes : [$t->telefono])));
            $primero = $normalizados[0] !== '' ? $normalizados[0] : $t->telefono;

            if ($primero !== $t->telefono) {
                $sql[] = 'UPDATE contacto_telefonos SET telefono = ' . $q($primero) . ', updated_at = NOW() WHERE id = ' . (int) $t->id
                    . ' AND telefono = ' . $q($t->telefono) . ';';
                $cambios['telefonos']++;
            }
            if (!preg_match('/^(9\d{8}|0\d{8}|\+\d{8,})( anexo \d+)?$/', $primero)) {
                $rep['no_reconocidos'][] = [$t->contacto_id, $t->telefono];
            }
            $finales[] = [(int) $t->id, (int) $t->contacto_id, $primero, (int) $t->estado];

            foreach (array_slice($normalizados, 1) as $extra) {
                $extras[] = [(int) $t->contacto_id, $extra, (int) $t->estado, $t->telefono];
            }
        }

        // 2) Mismo contacto con el mismo número activo dos veces: queda el más antiguo.
        $vistos = [];
        $tiene = [];
        $bajas = [];
        foreach ($finales as $fila) {
            list($id, $contactoId, $valor, $estado) = $fila;
            $tiene[$contactoId][$valor] = true;
            if ($estado !== 1) {
                continue;
            }
            if (isset($vistos[$contactoId][$valor])) {
                $bajas[] = $id;
            }
            $vistos[$contactoId][$valor] = true;
        }
        if ($bajas) {
            $sql[] = 'UPDATE contacto_telefonos SET estado = 0, deleted_at = NOW(), updated_at = NOW() WHERE id IN (' . implode(',', $bajas) . ');';
            $cambios['bajas_repetidos'] = count($bajas);
        }

        // 3) Números extra: solo si el contacto no lo tiene ya en ninguna fila.
        foreach ($extras as $fila) {
            list($contactoId, $extra, $estado, $original) = $fila;
            if (isset($tiene[$contactoId][$extra])) {
                continue;
            }
            $tiene[$contactoId][$extra] = true;
            $tipo = Telefono::esCelular($extra) ? 'movil' : 'fijo';
            $sql[] = 'INSERT INTO contacto_telefonos (contacto_id, telefono, tipo, es_principal, slot, estado, created_at, updated_at) '
                . 'SELECT ' . $contactoId . ', ' . $q($extra) . ', ' . $q($tipo) . ", 0, 'adicional', " . $estado . ', NOW(), NOW() FROM DUAL '
                . 'WHERE NOT EXISTS (SELECT 1 FROM contacto_telefonos WHERE contacto_id = ' . $contactoId . ' AND telefono = ' . $q($extra) . ');';
            $cambios['insertados']++;
            $rep['separar'][] = [$contactoId, $original];
        }

        // Campos directos del contacto.
        foreach ($db->table('contacto')->get(['id', 'celular', 'telefono']) as $c) {
            foreach (['celular', 'telefono'] as $campo) {
                $actual = $c->{$campo};
                if ($actual === null || trim($actual) === '') {
                    continue;
                }
                $partes = preg_split(self::PATRON_VARIOS, $actual, -1, PREG_SPLIT_NO_EMPTY);
                $nuevo = Telefono::normalizar($partes[0]);
                if ($nuevo !== '' && $nuevo !== $actual) {
                    $sql[] = "UPDATE contacto SET {$campo} = " . $q($nuevo) . ' WHERE id = ' . (int) $c->id . " AND {$campo} = " . $q($actual) . ';';
                    $cambios['directos']++;
                }
            }
        }

        $encabezado = [
            '-- Normalización de teléfonos y correos de contactos · BD ' . $bd . ' · generado ' . date('Y-m-d H:i:s'),
            '-- Requisitos: backup previo y migración 2026_10_20_000001_add_deleted_at_to_contacto_telefonos aplicada.',
            '-- Cada UPDATE por id solo aplica si el valor sigue igual que al generar (no pisa cambios posteriores).',
            'SET NAMES utf8mb4;',
            'START TRANSACTION;',
            '',
            '-- Liberar datos de contactos eliminados (baja con fecha) e inactivos (suspendidos, sin fecha).',
            'UPDATE contacto_telefonos t JOIN contacto c ON c.id = t.contacto_id SET t.estado = 0, t.deleted_at = NOW(), t.updated_at = NOW() WHERE t.estado = 1 AND c.deleted_at IS NOT NULL;',
            'UPDATE contacto_emails e JOIN contacto c ON c.id = e.contacto_id SET e.estado = 0, e.deleted_at = NOW(), e.updated_at = NOW() WHERE e.estado = 1 AND c.deleted_at IS NOT NULL;',
            'UPDATE contacto_telefonos t JOIN contacto c ON c.id = t.contacto_id SET t.estado = 0, t.updated_at = NOW() WHERE t.estado = 1 AND c.estado = 0 AND c.deleted_at IS NULL;',
            'UPDATE contacto_emails e JOIN contacto c ON c.id = e.contacto_id SET e.estado = 0, e.updated_at = NOW() WHERE e.estado = 1 AND c.estado = 0 AND c.deleted_at IS NULL;',
            '',
            '-- Correos sin espacios y en minúsculas.',
            'UPDATE contacto_emails SET email = LOWER(TRIM(email)) WHERE email COLLATE utf8mb4_bin <> LOWER(TRIM(email));',
            'UPDATE contacto SET email = LOWER(TRIM(email)) WHERE email IS NOT NULL AND email COLLATE utf8mb4_bin <> LOWER(TRIM(email));',
            '',
            '-- Teléfonos normalizados, separación de varios números y repetidos del mismo contacto.',
        ];
        $script = implode("\n", array_merge($encabezado, $sql, ['', 'COMMIT;', '']));

        $reporte = $this->reporte($db, $bd, $cambios, $rep);
        $resumen = "{$cambios['telefonos']} teléfonos normalizados, {$cambios['insertados']} insertados al separar, "
            . "{$cambios['bajas_repetidos']} repetidos dados de baja, {$cambios['directos']} campos directos";

        return [$script, $reporte, $resumen];
    }

    /**
     * Reporte de revisión (solo lectura): lo que el script no corrige solo.
     */
    private function reporte($db, $bd, array $cambios, array $rep)
    {
        $l = ['# Revisión de contactos · BD ' . $bd . ' · ' . date('Y-m-d H:i'), ''];
        $l[] = "- Teléfonos normalizados: {$cambios['telefonos']} · insertados al separar: {$cambios['insertados']} · repetidos del mismo contacto dados de baja: {$cambios['bajas_repetidos']} · campos directos: {$cambios['directos']}";
        $l[] = '';

        $lista = function ($titulo, array $filas) use (&$l) {
            $l[] = "## {$titulo} (" . count($filas) . ')';
            $l[] = '';
            foreach ($filas as $f) {
                $l[] = '- contacto ' . implode(' · ', $f);
            }
            $l[] = '';
        };

        $lista('Campos con varios números que no se separaron (alguna parte incompleta)', $rep['revisar_varios']);
        $lista('Teléfonos no reconocidos (incompletos, 6 dígitos sin código de ciudad, con #)', $rep['no_reconocidos']);

        // Celulares activos en más de un contacto activo (comparando ya normalizado).
        $porCelular = [];
        $filas = $db->table('contacto_telefonos as t')->join('contacto as c', 'c.id', '=', 't.contacto_id')
            ->where('t.estado', 1)->where('c.estado', 1)->whereNull('c.deleted_at')
            ->get(['t.contacto_id', 'c.empresa_id', 't.telefono']);
        foreach ($filas as $f) {
            foreach (preg_split(self::PATRON_VARIOS, (string) $f->telefono, -1, PREG_SPLIT_NO_EMPTY) as $p) {
                $n = Telefono::normalizar($p);
                if (Telefono::esCelular($n)) {
                    $porCelular[$n][$f->contacto_id] = $f->empresa_id;
                }
            }
        }
        $dup = [];
        foreach ($porCelular as $n => $contactos) {
            if (count($contactos) > 1) {
                $dup[] = [$n, 'contactos ' . implode(', ', array_keys($contactos)), 'empresas ' . implode(', ', array_unique($contactos))];
            }
        }
        $l[] = '## Celulares en más de un contacto activo (' . count($dup) . ')';
        $l[] = '';
        foreach ($dup as $d) {
            $l[] = '- ' . implode(' · ', $d);
        }
        $l[] = '';

        $correos = $db->select("SELECT LOWER(TRIM(e.email)) email, GROUP_CONCAT(DISTINCT e.contacto_id) contactos
            FROM contacto_emails e JOIN contacto c ON c.id = e.contacto_id
            WHERE e.estado = 1 AND c.estado = 1 AND c.deleted_at IS NULL
            GROUP BY LOWER(TRIM(e.email)) HAVING COUNT(DISTINCT e.contacto_id) > 1");
        $l[] = '## Correos en más de un contacto activo (' . count($correos) . ')';
        $l[] = '';
        foreach ($correos as $c) {
            $l[] = "- {$c->email} · contactos {$c->contactos}";
        }
        $l[] = '';

        $invalidos = $db->select("SELECT contacto_id, email FROM contacto_emails
            WHERE TRIM(email) NOT REGEXP '^[^@[:space:]]+@[^@[:space:]]+\\\\.[A-Za-z]{2,}$'");
        $l[] = '## Correos con formato inválido (' . count($invalidos) . ')';
        $l[] = '';
        foreach ($invalidos as $c) {
            $l[] = "- contacto {$c->contacto_id} · {$c->email}";
        }

        return implode("\n", $l) . "\n";
    }
}
