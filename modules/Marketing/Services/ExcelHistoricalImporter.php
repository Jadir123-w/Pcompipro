<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Models\HistoricalGrid;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * ExcelHistoricalImporter — Parser de planners editoriales históricos.
 *
 * Los planners 2022/2023/2024-2025 de COMPIPRO tienen estructura TRANSPUESTA:
 *   - Filas = campos (FECHA, TEMATICA, TITULO, COPY, HASHTAGS, etc.)
 *   - Columnas = días del mes, agrupados por mes
 *   - Sábados/domingos pueden tener "x" = sin post
 *
 * Este importador:
 *   1. Detecta cada hoja → mapea a red social (facebook, instagram, linkedin, tiktok)
 *   2. Localiza la fila con FECHAS reales (suele estar en las primeras 5 filas)
 *   3. Por cada columna con fecha válida, recolecta los valores de los campos
 *   4. Clasifica campos como editoriales o operativos
 *   5. Normaliza y guarda en HistoricalGrid::contenido_normalizado (JSON)
 */
class ExcelHistoricalImporter
{
    /**
     * Campos editoriales esenciales (alimentan lógica del agente).
     */
    const EDITORIAL_FIELDS = [
        'tematica'              => ['TEMATICA', 'TEMÁTICA', 'TEMA'],
        'campania'              => ['CAMPAÑA', 'CAMPANA', 'CAMPAÑAS'],
        'temporada'             => ['TEMPORADA', 'ESTACION'],
        'evento'                => ['EVENTO', 'FECHA CONMEMORATIVA'],
        'objetivo'              => ['OBJETIVO'],
        'linea_comercial'       => ['LINEA', 'LÍNEA', 'LINEA COMERCIAL', 'PRODUCTO'],
        'tipo_contenido'        => ['TIPO DE CONTENIDO', 'TIPOS DE CONTENIDO', 'TIPOS DE CONTENIDO 1 Y 2', 'TIPO DE CONTENIDO 1 Y 2'],
        'titulo'                => ['TITULO', 'TÍTULO', 'TITULOS', 'TÍTULOS', 'TITULOS 1 Y 2', 'TÍTULOS 1 Y 2'],
        // 'CONTENIDO' aparece en planners de TikTok 2023 como sinónimo de COPY
        'copy'                  => ['COPY', 'COPYS', 'COPY 1 Y 2', 'CONTENIDO'],
        'hashtag_1'             => ['HASHTAG DE POST 1', 'HASHTAGS POST 1', 'HASHTAG POST 1', 'HASHTAG 1', 'HASHTAG'],
        'hashtag_2'             => ['HASHTAG DE POST 2', 'HASHTAGS POST 2', 'HASHTAG POST 2', 'HASHTAG 2'],
        'hashtags_recomendados' => ['#RECOMENDADOS', 'HASHTAGS RECOMENDADOS', 'RECOMENDADOS'],
        'texto_en_imagen_video' => ['TEXTO EN LA IMAGEN O VIDEO', 'TEXTO EN LA IMAGEN', 'TEXTO EN IMAGEN', 'TEXTO IMAGEN'],
        'plataforma'            => ['PLATAFORMA', 'CANAL', 'DIFUSION', 'DIFUSIÓN'],
        // Campos extra que aparecen en TikTok 2023 — los conservamos para no perder señal
        'formato'               => ['FORMATO'],
        'fondo_musical'         => ['FONDO MUSICAL', 'MUSICA', 'MÚSICA'],
    ];

    /**
     * Campos operativos (metadata humana que NO condiciona lógica editorial).
     */
    const OPERATIONAL_FIELDS = [
        'hora_publicacion'    => ['HORA DE PUBLICACION', 'HORA DE PUBLICACIÓN', 'HORA'],
        'publicado'           => ['PUBLICADO'],
        'disenador_grafico'   => ['NOMBRE DEL DISEÑADOR GRAFICO', 'NOMBRE DEL DISEÑADOR GRÁFICO', 'DISEÑADOR GRAFICO', 'DISEÑADOR GRÁFICO', 'NOMBRE DEL DISEÑADOR'],
        'disenador_contenido' => ['NOMBRE DEL DISEÑADOR DE CONTENIDO', 'NOMBRE DISEÑADOR DE CONTENIDO', 'DISEÑADOR DE CONTENIDO'],
    ];

    /**
     * Mapeo de nombres de hoja a red social.
     * Se evalúa como substring case-insensitive del nombre de la hoja.
     */
    const SHEET_NETWORK_MAP = [
        'FB IG'    => ['facebook', 'instagram'], // hasta 2023 compartido
        'FACEBOOK' => ['facebook'],
        'INSTAGRAM' => ['instagram'],
        'LINKEDIN' => ['linkedin'],
        'TIK TOK'  => ['tiktok'],
        'TIKTOK'   => ['tiktok'],
    ];

    /**
     * Importar un planner histórico.
     *
     * @param  HistoricalGrid  $hg
     * @return array                ['success' => bool, 'total_items' => int, 'redes' => array, 'error' => string|null]
     */
    public function import(HistoricalGrid $hg)
    {
        if (empty($hg->archivo_storage_path)) {
            return $this->errorResult($hg, 'HistoricalGrid sin archivo_storage_path');
        }

        $disk = config('marketing.chatbot.mkt03.image_storage_disk', 'public');
        
        try {
            $fullPath = Storage::disk($disk)->path($hg->archivo_storage_path);
        } catch (\Exception $e) {
            return $this->errorResult($hg, 'Archivo no accesible: ' . $e->getMessage());
        }

        if (empty($fullPath)) {
            return $this->errorResult($hg, 'La ruta del archivo está vacía');
        }

        if (!file_exists($fullPath)) {
            return $this->errorResult($hg, 'Archivo no existe en: ' . $fullPath);
        }

        $previousLimit = ini_get('memory_limit');
        ini_set('memory_limit', '2G');

        try {
            $reader = new Xlsx();
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            $sheetNames = $reader->listWorksheetNames($fullPath);

            $allItems = [];
            $redesDetectadas = [];
            $sheetsParsed = [];

            foreach ($sheetNames as $sheetName) {
                $redesSheet = $this->detectNetworkFromSheetName($sheetName);
                if (empty($redesSheet)) {
                    Log::info('ExcelHistoricalImporter: hoja ignorada (red no reconocida)', [
                        'sheet' => $sheetName,
                    ]);
                    continue;
                }

                $reader->setLoadSheetsOnly([$sheetName]);
                $spreadsheet = $reader->load($fullPath);
                $sheet = $spreadsheet->getActiveSheet();

                $items = $this->parseSheet($sheet, $redesSheet, $hg->anio);
                $allItems = array_merge($allItems, $items);
                $redesDetectadas = array_unique(array_merge($redesDetectadas, $redesSheet));
                $sheetsParsed[$sheetName] = count($items);

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }

            $estructura = [
                'hojas_procesadas' => $sheetsParsed,
                'campos_editoriales_conocidos'  => array_keys(self::EDITORIAL_FIELDS),
                'campos_operativos_conocidos'   => array_keys(self::OPERATIONAL_FIELDS),
            ];

            $hg->update([
                'estructura_detectada'  => $estructura,
                'contenido_normalizado' => $allItems,
                'total_items'           => count($allItems),
                'redes_detectadas'      => array_values($redesDetectadas),
                'estado'                => HistoricalGrid::ESTADO_IMPORTADO,
                'error_message'         => null,
            ]);

            ini_set('memory_limit', $previousLimit);

            Log::info('ExcelHistoricalImporter: import completado', [
                'historical_grid_id' => $hg->id,
                'total_items'        => count($allItems),
                'redes_detectadas'   => $redesDetectadas,
                'sheets_parsed'      => $sheetsParsed,
            ]);

            return [
                'success'     => true,
                'total_items' => count($allItems),
                'redes'       => array_values($redesDetectadas),
                'sheets'      => $sheetsParsed,
                'error'       => null,
            ];

        } catch (\Exception $e) {
            ini_set('memory_limit', $previousLimit);
            return $this->errorResult($hg, 'Error parseando Excel: ' . $e->getMessage());
        }
    }

    /**
     * Detectar la red social asociada al nombre de la hoja.
     *
     * @param  string  $sheetName
     * @return array              Array de redes (puede ser múltiples: ej. ["facebook", "instagram"])
     */
    private function detectNetworkFromSheetName($sheetName)
    {
        $upper = strtoupper(trim($sheetName));

        // Eliminar marcas comunes
        $upper = str_replace(['-', '—', ' COMPIPRO', 'COMPIPRO'], ' ', $upper);
        $upper = preg_replace('/\s+/', ' ', $upper);
        $upper = trim($upper);

        foreach (self::SHEET_NETWORK_MAP as $key => $redes) {
            if (strpos($upper, $key) !== false) {
                return $redes;
            }
        }

        return [];
    }

    /**
     * Parsear una hoja y extraer ítems.
     *
     * @param  \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet  $sheet
     * @param  array  $redes  Redes asociadas a esta hoja
     * @param  int    $anio   Año por defecto si las fechas vienen sin año
     * @return array          Lista de ítems normalizados
     */
    private function parseSheet($sheet, array $redes, $anio)
    {
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        // 1. Localizar la fila que contiene las fechas.
        //    Modo A: fila con label "FECHA" en col A (planners FB/IG/LinkedIn 2022-2024)
        //    Modo B (fallback): fila 1 directamente con valores tipo "Lunes: 02/01"
        //                       (planners TikTok 2023 — sin label explícita)
        $fechaRow = null;
        for ($row = 1; $row <= min($highestRow, 10); $row++) {
            $labelRaw = $sheet->getCell('A' . $row)->getValue();
            if ($labelRaw === null) continue;
            $label = $this->normalizeLabel($labelRaw);
            if (strpos($label, 'FECHA') !== false) {
                $fechaRow = $row;
                break;
            }
        }

        // Fallback: si no hay label FECHA, intentar fila 1 si tiene >= 5 fechas parseables
        if ($fechaRow === null) {
            $candidatePorFila = [];
            for ($row = 1; $row <= min($highestRow, 3); $row++) {
                $count = 0;
                for ($col = 2; $col <= min($highestColIndex, 60); $col++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $cellValue = $sheet->getCell($colLetter . $row)->getValue();
                    if ($this->parseFecha($cellValue, $anio)) {
                        $count++;
                    }
                }
                $candidatePorFila[$row] = $count;
            }
            if (!empty($candidatePorFila)) {
                arsort($candidatePorFila);
                $bestRow   = key($candidatePorFila);
                $bestCount = current($candidatePorFila);
                if ($bestCount >= 5) {
                    $fechaRow = $bestRow;
                    Log::info('ExcelHistoricalImporter: fila de fechas detectada por fallback', [
                        'hoja' => $sheet->getTitle(),
                        'fila' => $bestRow,
                        'fechas_detectadas' => $bestCount,
                    ]);
                }
            }
        }

        if ($fechaRow === null) {
            Log::warning('ExcelHistoricalImporter: no se encontró fila de fechas en la hoja', [
                'hoja' => $sheet->getTitle(),
            ]);
            return [];
        }

        // 2. Recolectar las fechas por columna
        $fechasPorColumna = [];
        for ($col = 2; $col <= $highestColIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $cellValue = $sheet->getCell($colLetter . $fechaRow)->getValue();
            if (empty(trim((string) $cellValue))) continue;
            if (strtolower(trim((string) $cellValue)) === 'x') continue;

            $fecha = $this->parseFecha($cellValue, $anio);
            if ($fecha) {
                $fechasPorColumna[$colLetter] = $fecha;
            }
        }

        if (empty($fechasPorColumna)) {
            Log::warning('ExcelHistoricalImporter: no se encontraron fechas válidas en la hoja', [
                'hoja' => $sheet->getTitle(),
            ]);
            return [];
        }

        // 3. Construir mapeo de fila → campo, recorriendo todas las filas con label en col A
        $rowToField = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            if ($row === $fechaRow) continue;
            $labelRaw = $sheet->getCell('A' . $row)->getValue();
            if ($labelRaw === null) continue;
            $label = $this->normalizeLabel($labelRaw);
            $mapped = $this->mapLabelToField($label);
            if ($mapped) {
                $rowToField[$row] = $mapped;
            }
        }

        // 4. Para cada columna con fecha, recolectar todos los campos
        $items = [];
        $order = 1;
        foreach ($fechasPorColumna as $colLetter => $fecha) {
            $item = [
                'fecha'              => $fecha,
                'red_social'         => count($redes) === 1 ? $redes[0] : null,
                'redes'              => $redes,
                'item_order'         => $order,
                'editorial'          => [],
                'operacional'        => [],
            ];

            foreach ($rowToField as $row => $fieldInfo) {
                $cellValue = $sheet->getCell($colLetter . $row)->getValue();
                if ($cellValue === null) continue;
                $strVal = trim((string) $cellValue);
                if ($strVal === '' || strtolower($strVal) === 'x') continue;

                $type  = $fieldInfo['type'];  // 'editorial' | 'operacional'
                $key   = $fieldInfo['key'];   // clave normalizada

                $item[$type][$key] = $strVal;
            }

            // Omitir ítem si todo está vacío (solo tiene fecha)
            if (empty($item['editorial']) && empty($item['operacional'])) {
                continue;
            }

            // Promover algunos campos editoriales al top-level para facilitar análisis
            $editorial = $item['editorial'];
            $topLevelItem = [
                'fecha'           => $item['fecha'],
                'red_social'      => $item['red_social'],
                'redes'           => $item['redes'],
                'tematica'        => $editorial['tematica']        ?? null,
                'campania'        => $editorial['campania']        ?? null,
                'temporada'       => $editorial['temporada']       ?? null,
                'evento'          => $editorial['evento']          ?? null,
                'objetivo'        => $editorial['objetivo']        ?? null,
                'linea_comercial' => $editorial['linea_comercial'] ?? null,
                'tipo_contenido'  => $editorial['tipo_contenido']  ?? null,
                'titulo'          => $editorial['titulo']          ?? null,
                'copy'            => $editorial['copy']            ?? null,
                'hashtag_1'       => $editorial['hashtag_1']       ?? null,
                'hashtag_2'       => $editorial['hashtag_2']       ?? null,
                'texto_en_imagen_video' => $editorial['texto_en_imagen_video'] ?? null,
                'item_order'      => $order,
                'editorial'       => $editorial,
                'operacional'     => $item['operacional'],
            ];

            $items[] = $topLevelItem;
            $order++;
        }

        return $items;
    }

    /**
     * Normalizar un label (etiqueta de fila en columna A) para comparación.
     *
     * @param  string  $raw
     * @return string
     */
    private function normalizeLabel($raw)
    {
        $label = (string) $raw;
        $label = strtoupper(trim($label));
        // Reemplazar acentos
        $replacements = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'Ñ' => 'Ñ', // preservamos Ñ
        ];
        $label = strtr($label, $replacements);
        // Quitar saltos de línea y espacios múltiples
        $label = preg_replace('/\s+/', ' ', $label);
        return trim($label);
    }

    /**
     * Mapear label normalizado a un campo conocido.
     *
     * @param  string  $normalizedLabel
     * @return array|null  ['type' => 'editorial|operacional', 'key' => '...']
     */
    private function mapLabelToField($normalizedLabel)
    {
        foreach (self::EDITORIAL_FIELDS as $key => $variants) {
            foreach ($variants as $v) {
                if ($normalizedLabel === $this->normalizeLabel($v)) {
                    return ['type' => 'editorial', 'key' => $key];
                }
            }
        }
        foreach (self::OPERATIONAL_FIELDS as $key => $variants) {
            foreach ($variants as $v) {
                if ($normalizedLabel === $this->normalizeLabel($v)) {
                    return ['type' => 'operacional', 'key' => $key];
                }
            }
        }
        return null;
    }

    /**
     * Parsear una celda de fecha (puede venir como texto "1 DE ENERO" o número de Excel).
     *
     * @param  mixed  $cellValue
     * @param  int    $anio
     * @return string|null  Fecha en formato YYYY-MM-DD
     */
    private function parseFecha($cellValue, $anio)
    {
        if ($cellValue === null || $cellValue === '') return null;

        // Si es numérico, es un serial de Excel
        if (is_numeric($cellValue)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellValue);
                return $dt->format('Y-m-d');
            } catch (\Exception $e) {
                // seguir con parseo textual
            }
        }

        $txt = strtolower(trim((string) $cellValue));

        // Intentar match "DD de MES" o "DD DE MES" o "DD MES"
        $meses = [
            'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
            'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
            'setiembre' => '09', 'septiembre' => '09',
            'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12',
        ];

        // Patrón "DD de MES" o "DD MES" (ignorar "de")
        if (preg_match('/(\d{1,2})\s*(?:de)?\s*([a-záéíóú]+)/u', $txt, $m)) {
            $dia = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mesNombre = strtolower(trim($m[2]));
            // normalizar acentos
            $mesNombre = strtr($mesNombre, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            ]);
            if (isset($meses[$mesNombre])) {
                return sprintf('%04d-%s-%s', $anio, $meses[$mesNombre], $dia);
            }
        }

        // Patrón "DD/MM" o "DD-MM" o cualquier "Lunes: 02/01" o similar
        // (extrae la primera ocurrencia de DD/MM o DD-MM dentro del texto)
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})/', $txt, $m)) {
            return sprintf('%04d-%02d-%02d', $anio, (int) $m[2], (int) $m[1]);
        }

        // Patrón "YYYY-MM-DD"
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $txt)) {
            return $txt;
        }

        return null;
    }

    /**
     * Registrar error y persistirlo en el HistoricalGrid.
     *
     * @param  HistoricalGrid  $hg
     * @param  string          $msg
     * @return array
     */
    private function errorResult(HistoricalGrid $hg, $msg)
    {
        Log::error('ExcelHistoricalImporter: ' . $msg, [
            'historical_grid_id' => $hg->id,
        ]);
        $hg->update([
            'estado'        => HistoricalGrid::ESTADO_ERROR,
            'error_message' => $msg,
        ]);
        return [
            'success'     => false,
            'total_items' => 0,
            'redes'       => [],
            'error'       => $msg,
        ];
    }
}
