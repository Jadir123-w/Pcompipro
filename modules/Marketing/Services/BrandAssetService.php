<?php

namespace Modules\Marketing\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Models\BrandAsset;

/**
 * BrandAssetService — Lógica de negocio del Brand Asset Library (BAL).
 *
 * Centraliza:
 *   - Subida y validación de archivos (logo, product_photo, icon, font binario)
 *   - Detección de mime/dimensions de imágenes
 *   - Limpieza de archivos al eliminar (no orphans)
 *   - Generación de paquetes de assets para cada agente
 */
class BrandAssetService
{
    const STORAGE_DISK = 'public';
    const STORAGE_BASE = 'marketing/brand-assets';

    /**
     * MIME types aceptados por categoría.
     */
    public static $ALLOWED_MIMES_IMAGE = [
        'image/png',
        'image/jpeg',
        'image/webp',
        'image/svg+xml',
    ];

    public static $ALLOWED_MIMES_FONT = [
        'font/ttf',
        'font/woff',
        'font/woff2',
        'application/font-woff',
        'application/font-woff2',
        'application/x-font-ttf',
        'application/octet-stream', // ttf a veces llega así
    ];

    const MAX_FILE_SIZE = 10485760; // 10 MB

    /**
     * Crear un BrandAsset nuevo.
     *
     * @param  array              $data    Campos del modelo (sin storage_path; eso lo resuelve el service)
     * @param  UploadedFile|null  $file    Archivo (requerido si type es file-based)
     * @param  int|null           $userId  ID del usuario creador
     * @return BrandAsset
     * @throws \InvalidArgumentException
     */
    public function create(array $data, $file = null, $userId = null)
    {
        $type = isset($data['type']) ? $data['type'] : null;
        if (!in_array($type, BrandAsset::$TYPES)) {
            throw new \InvalidArgumentException("Tipo inválido: {$type}");
        }

        $payload = [
            'type'          => $type,
            'name'          => isset($data['name']) ? trim($data['name']) : '',
            'description'   => isset($data['description']) ? $data['description'] : null,
            'tags'          => $this->normalizeTags(isset($data['tags']) ? $data['tags'] : []),
            'usage_context' => isset($data['usage_context']) ? $data['usage_context'] : null,
            'use_in_mkt01'  => $this->boolify(isset($data['use_in_mkt01']) ? $data['use_in_mkt01'] : false),
            'use_in_com02'  => $this->boolify(isset($data['use_in_com02']) ? $data['use_in_com02'] : false),
            'use_in_mkt03'  => $this->boolify(isset($data['use_in_mkt03']) ? $data['use_in_mkt03'] : false),
            'active'        => $this->boolify(isset($data['active']) ? $data['active'] : true),
            'sort_order'    => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
            'created_by'    => $userId,
        ];

        // Validar campos obligatorios según tipo
        if (in_array($type, BrandAsset::$TYPES_FILE)) {
            if (!$file instanceof UploadedFile) {
                throw new \InvalidArgumentException("Asset tipo {$type} requiere archivo");
            }
            $fileMeta = $this->storeFile($file, $type);
            $payload = array_merge($payload, $fileMeta);
        } elseif ($type === BrandAsset::TYPE_COLOR) {
            $hex = isset($data['hex_color']) ? $this->normalizeHex($data['hex_color']) : null;
            if (!$hex) {
                throw new \InvalidArgumentException('Color requiere hex_color en formato #RRGGBB');
            }
            $payload['hex_color'] = $hex;
        } elseif (in_array($type, BrandAsset::$TYPES_TEXT)) {
            $text = isset($data['text_value']) ? trim($data['text_value']) : '';
            if ($text === '') {
                throw new \InvalidArgumentException("Asset tipo {$type} requiere text_value");
            }
            $payload['text_value'] = $text;
        }

        return BrandAsset::create($payload);
    }

    /**
     * Actualizar un BrandAsset existente.
     *
     * @param  BrandAsset         $asset
     * @param  array              $data
     * @param  UploadedFile|null  $file  Si se pasa, reemplaza el archivo previo
     * @return BrandAsset
     */
    public function update(BrandAsset $asset, array $data, $file = null)
    {
        $updates = [];

        $simpleFields = [
            'name', 'description', 'usage_context',
            'use_in_mkt01', 'use_in_com02', 'use_in_mkt03',
            'active', 'sort_order',
        ];
        foreach ($simpleFields as $f) {
            if (array_key_exists($f, $data)) {
                if (in_array($f, ['use_in_mkt01', 'use_in_com02', 'use_in_mkt03', 'active'])) {
                    $updates[$f] = $this->boolify($data[$f]);
                } elseif ($f === 'sort_order') {
                    $updates[$f] = (int) $data[$f];
                } else {
                    $updates[$f] = $data[$f];
                }
            }
        }
        if (array_key_exists('tags', $data)) {
            $updates['tags'] = $this->normalizeTags($data['tags']);
        }

        // Reemplazo de archivo
        if ($file instanceof UploadedFile && $asset->isFileBased()) {
            $oldPath = $asset->storage_path;
            $oldDisk = $asset->storage_disk;

            $fileMeta = $this->storeFile($file, $asset->type);
            $updates = array_merge($updates, $fileMeta);

            // Eliminar archivo previo
            if ($oldPath) {
                try {
                    Storage::disk($oldDisk ?: self::STORAGE_DISK)->delete($oldPath);
                } catch (\Exception $e) {
                    Log::warning('BrandAssetService: no se pudo eliminar archivo anterior', [
                        'path'  => $oldPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Color
        if ($asset->isColor() && array_key_exists('hex_color', $data)) {
            $hex = $this->normalizeHex($data['hex_color']);
            if ($hex) {
                $updates['hex_color'] = $hex;
            }
        }

        // Texto
        if (in_array($asset->type, BrandAsset::$TYPES_TEXT) && array_key_exists('text_value', $data)) {
            $updates['text_value'] = trim($data['text_value']);
        }

        $asset->update($updates);
        return $asset->fresh();
    }

    /**
     * Eliminar un asset (soft delete) y su archivo asociado.
     *
     * @param  BrandAsset  $asset
     * @return void
     */
    public function delete(BrandAsset $asset)
    {
        if ($asset->isFileBased() && $asset->storage_path) {
            try {
                Storage::disk($asset->storage_disk ?: self::STORAGE_DISK)
                    ->delete($asset->storage_path);
            } catch (\Exception $e) {
                Log::warning('BrandAssetService: no se pudo eliminar archivo al borrar asset', [
                    'asset_id' => $asset->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
        $asset->delete();
    }

    /**
     * Subir archivo, validar mime/size, detectar dimensions si imagen.
     *
     * @param  UploadedFile  $file
     * @param  string        $type
     * @return array         metadata para el modelo
     */
    private function storeFile(UploadedFile $file, $type)
    {
        // Validar tamaño
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException(
                'Archivo excede ' . round(self::MAX_FILE_SIZE / 1024 / 1024) . ' MB'
            );
        }

        $mime = $file->getMimeType();

        // Validar mime según tipo
        if (in_array($type, [BrandAsset::TYPE_LOGO, BrandAsset::TYPE_PRODUCT_PHOTO, BrandAsset::TYPE_ICON])) {
            if (!in_array($mime, self::$ALLOWED_MIMES_IMAGE)) {
                throw new \InvalidArgumentException(
                    "Tipo de archivo no soportado: {$mime}. Acepta: " .
                    implode(', ', self::$ALLOWED_MIMES_IMAGE)
                );
            }
        } elseif ($type === BrandAsset::TYPE_FONT) {
            if (!in_array($mime, self::$ALLOWED_MIMES_FONT)) {
                throw new \InvalidArgumentException(
                    "Tipo de fuente no soportado: {$mime}"
                );
            }
        }

        // Generar nombre único
        $ext = $file->getClientOriginalExtension() ?: $this->mimeToExt($mime);
        $filename = $type . '_' . date('Ymd_His') . '_' . uniqid() . '.' . $ext;
        $folder = self::STORAGE_BASE . '/' . $type;

        $storagePath = $file->storeAs($folder, $filename, self::STORAGE_DISK);

        $meta = [
            'storage_disk' => self::STORAGE_DISK,
            'storage_path' => $storagePath,
            'mime_type'    => $mime,
            'file_size'    => $file->getSize(),
            'width'        => null,
            'height'       => null,
        ];

        // Intentar leer dimensiones si es imagen raster
        if (in_array($mime, ['image/png', 'image/jpeg', 'image/webp'])) {
            try {
                $absPath = Storage::disk(self::STORAGE_DISK)->path($storagePath);
                $info = @getimagesize($absPath);
                if ($info !== false) {
                    $meta['width']  = (int) $info[0];
                    $meta['height'] = (int) $info[1];
                }
            } catch (\Exception $e) {
                // ignoramos — el dato es informativo
            }
        }

        return $meta;
    }

    /**
     * Normalizar hex color: #abc → #aabbcc, asegurar #.
     *
     * @param  string  $hex
     * @return string|null
     */
    private function normalizeHex($hex)
    {
        if (!is_string($hex)) return null;
        $hex = trim($hex);
        if ($hex === '') return null;
        if ($hex[0] !== '#') $hex = '#' . $hex;

        // #abc → #aabbcc
        if (preg_match('/^#([0-9a-fA-F]{3})$/', $hex, $m)) {
            $hex = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }

        if (preg_match('/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $hex)) {
            return strtoupper($hex);
        }

        return null;
    }

    /**
     * Normalizar tags: array de strings limpios, sin duplicados, sin vacíos.
     *
     * @param  mixed  $tags
     * @return array
     */
    private function normalizeTags($tags)
    {
        if (is_string($tags)) {
            $tags = preg_split('/[,\s]+/', $tags);
        }
        if (!is_array($tags)) {
            return [];
        }
        $out = [];
        foreach ($tags as $t) {
            $t = is_string($t) ? trim($t) : '';
            if ($t !== '' && !in_array($t, $out)) {
                $out[] = $t;
            }
        }
        return $out;
    }

    /**
     * @param  mixed  $val
     * @return bool
     */
    private function boolify($val)
    {
        if (is_bool($val)) return $val;
        if (is_string($val)) return in_array(strtolower($val), ['1', 'true', 'yes', 'on']);
        return (bool) $val;
    }

    /**
     * @param  string  $mime
     * @return string
     */
    private function mimeToExt($mime)
    {
        $map = [
            'image/png'      => 'png',
            'image/jpeg'     => 'jpg',
            'image/webp'     => 'webp',
            'image/svg+xml'  => 'svg',
            'font/ttf'       => 'ttf',
            'font/woff'      => 'woff',
            'font/woff2'     => 'woff2',
        ];
        return isset($map[$mime]) ? $map[$mime] : 'bin';
    }
}
