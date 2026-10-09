<?php

namespace Modules\Marketing\Support;

use Illuminate\Support\Facades\Storage;

/**
 * PublicUrl — URL pública de un archivo guardado en un disco de Laravel.
 *
 * Existe por un problema concreto: el disco 'public' define su URL como
 * `env('APP_URL').'/storage'` (config/filesystems.php). Si el .env del servidor
 * trae APP_URL mal armado, TODA URL que salga de `Storage::disk()->url()` queda
 * inservible y el navegador no puede abrir el archivo.
 *
 * Ya pasó en el servidor de Pruebas: el .env interpola `${app_url_base}` en
 * minúsculas mientras la variable declarada es APP_URL_BASE. Como dotenv
 * distingue mayúsculas, el literal nunca se resuelve y las URLs salían así:
 *
 *     ${app_url_base}/storage/marketing/proposals/MAP...pdf   → NXDOMAIN
 *
 * El criterio de este helper es intervenir SOLO si la URL salió mal formada:
 *
 *   - Disco con URL válida (s3, o un APP_URL bien armado) → se respeta tal cual.
 *   - URL inválida, o disco sin soporte de URL → se rearma con `url()`, que toma
 *     el host del REQUEST en curso y por lo tanto no depende de APP_URL.
 *
 * Así no se rompe ningún disco remoto y de paso queda más correcto en
 * multi-tenant, donde cada tenant entra por su propio subdominio mientras
 * APP_URL es un valor único para todos.
 *
 * NOTA: esto blinda el síntoma, no la causa. El .env mal armado conviene
 * corregirlo igual en el servidor.
 */
class PublicUrl
{
    /**
     * Resolver la URL pública de un archivo.
     *
     * @param  string|null  $disk  Nombre del disco. Si viene vacío, se usa 'public'.
     * @param  string|null  $path  Ruta del archivo dentro del disco.
     * @return string|null  URL utilizable, o null si no hay ruta.
     */
    public static function forDisk($disk, $path)
    {
        $path = ltrim((string) $path, '/');

        if ($path === '') {
            return null;
        }

        $disk = ($disk !== null && $disk !== '') ? $disk : 'public';

        $url = null;
        try {
            $url = Storage::disk($disk)->url($path);
        } catch (\Exception $e) {
            // Disco sin soporte de URL (un 'local' fuera de storage/app/public,
            // por ejemplo). Se resuelve abajo.
            $url = null;
        }

        // El disco devolvió algo utilizable: respetarlo (cubre s3 y cualquier
        // disco con URL propia bien configurada).
        if ($url !== null && filter_var($url, FILTER_VALIDATE_URL) !== false) {
            return $url;
        }

        // Rearmar desde el host del request.
        return url('storage/' . $path);
    }
}
