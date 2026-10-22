<?php

namespace App\Console\Commands;

use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Genera (sin escribir en la BD) el SQL que copia la auditoría guardada por error en la BD
 * central a la BD de cada empresa, según el dominio de la URL de cada registro.
 * No borra nada de la BD central. El SQL lo ejecuta una persona, después de un backup y con
 * config/audit.php (conexión tenant) ya desplegado.
 */
class AuditoriaCopiarHistorialSqlCommand extends Command
{
    protected $signature = 'auditoria:copiar-historial-sql
                            {--salida= : Carpeta de salida (por defecto storage/app/auditoria-historial)}';

    protected $description = 'Genera el SQL para copiar la auditoría de la BD central a la BD de cada empresa (solo lectura)';

    const COLUMNAS = 'user_type, user_id, event, auditable_type, auditable_id, old_values, new_values, url, ip_address, user_agent, tags, created_at, updated_at';

    // Dominio de la URL sin protocolo, ruta ni puerto.
    const HOST = "SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX(a.url, '/', 3), '/', -1), ':', 1)";

    public function handle()
    {
        $salida = $this->option('salida') ?: storage_path('app/auditoria-historial');
        if (!is_dir($salida) && !mkdir($salida, 0750, true)) {
            $this->error("No se pudo crear la carpeta {$salida}");
            return 1;
        }

        $central = DB::connection('system');
        $central->statement('SET SESSION TRANSACTION READ ONLY');
        $bdCentral = $central->getDatabaseName();
        $q = function ($v) use ($central) {
            return $central->getPdo()->quote($v);
        };

        $sql = [
            '-- Copia de la auditoría de la BD central (' . $bdCentral . ') a la BD de cada empresa · generado ' . date('Y-m-d H:i:s'),
            '-- Requisitos: backup previo y config/audit.php (conexión tenant) ya desplegado.',
            '-- No borra nada de la BD central. Es idempotente: no copia dos veces el mismo registro.',
            'SET NAMES utf8mb4;',
            'START TRANSACTION;',
            '',
        ];
        $resumen = [];
        $dominiosAsignados = [];

        foreach (Website::with('hostnames')->get() as $website) {
            $dominios = $website->hostnames->pluck('fqdn')->filter()->values()->all();
            if (empty($dominios)) {
                continue;
            }
            $dominiosAsignados = array_merge($dominiosAsignados, $dominios);
            $lista = implode(', ', array_map($q, $dominios));
            $total = $central->selectOne('SELECT COUNT(*) n FROM audits a WHERE ' . self::HOST . " IN ({$lista})")->n;
            $resumen[] = "{$website->uuid} (" . implode(', ', $dominios) . "): {$total}";

            $bd = '`' . str_replace('`', '', $website->uuid) . '`';
            $sql[] = "-- {$website->uuid}: {$total} registros en la BD central";
            $sql[] = "INSERT INTO {$bd}.audits (" . self::COLUMNAS . ')'
                . ' SELECT ' . self::COLUMNAS . " FROM `{$bdCentral}`.audits a"
                . ' WHERE ' . self::HOST . " IN ({$lista})"
                . " AND NOT EXISTS (SELECT 1 FROM {$bd}.audits t WHERE t.auditable_type = a.auditable_type"
                . ' AND t.auditable_id = a.auditable_id AND t.event = a.event'
                . ' AND t.created_at <=> a.created_at AND t.user_id <=> a.user_id);';
            $sql[] = '';
        }

        $sinEmpresa = $central->selectOne('SELECT COUNT(*) n FROM audits a WHERE a.url IS NULL OR '
            . self::HOST . ' NOT IN (' . (empty($dominiosAsignados) ? "''" : implode(', ', array_map($q, $dominiosAsignados))) . ')')->n;

        $sql[] = 'COMMIT;';
        $sql[] = '';
        $sql[] = "-- Registros sin empresa reconocible (URL vacía o de otro dominio): {$sinEmpresa}. Se quedan solo en la BD central.";

        $archivo = $salida . DIRECTORY_SEPARATOR . 'auditoria-copiar-historial-' . date('Ymd-His') . '.sql';
        file_put_contents($archivo, implode("\n", $sql) . "\n");

        foreach ($resumen as $linea) {
            $this->line('  ' . $linea);
        }
        $this->line("  sin empresa reconocible: {$sinEmpresa}");
        $this->info("SQL: {$archivo}");

        return 0;
    }
}
