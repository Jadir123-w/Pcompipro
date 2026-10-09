<?php

namespace Modules\Marketing\Contracts;

/**
 * ImageProviderInterface
 *
 * Contrato agnóstico para proveedores de generación de imágenes con IA
 * (DALL-E 3, Stability AI, etc.). Permite cambiar de proveedor con solo
 * modificar una variable de entorno.
 *
 * Usado por MKT-03 para generar imágenes finales de piezas publicables.
 */
interface ImageProviderInterface
{
    /**
     * Generar una imagen a partir de un prompt (text → image).
     *
     * @param  string  $prompt       Descripción de la imagen (en inglés preferido)
     * @param  array   $options      Opciones: size, quality, style, n, aspect_ratio, format, etc.
     * @return array                 ['success' => bool, 'url' => string|null, 'storage_path' => string|null, 'provider' => string, 'model' => string, 'error' => string|null]
     */
    public function generateImage($prompt, array $options = []);

    /**
     * Editar/transformar imagen(es) con un prompt (image[s] + text → image).
     *
     * Casos de uso:
     *   - Pasar 1 producto real + prompt → reescenificarlo en otro contexto
     *   - Pasar varios productos + prompt → componer una escena combinándolos
     *   - Pasar 1 imagen + máscara + prompt → editar solo la zona enmascarada (inpainting)
     *
     * Si el provider NO soporta esta capacidad (ej. DALL-E 3), debe retornar
     * { success: false, error: 'edit not supported by this provider' }.
     *
     * @param  string  $prompt              Descripción del resultado deseado
     * @param  array   $referenceImagePaths Rutas absolutas a 1+ imágenes de referencia (PNG/JPG/WEBP)
     * @param  array   $options             Opciones: size, quality, mask_path, format, etc.
     * @return array                        Mismo shape que generateImage()
     */
    public function editImage($prompt, array $referenceImagePaths, array $options = []);

    /**
     * ¿El provider soporta editImage() con imágenes de referencia?
     *
     * @return bool
     */
    public function supportsImageEdit();

    /**
     * Nombre del proveedor (para logs y trazabilidad).
     *
     * @return string
     */
    public function getProviderName();

    /**
     * Modelo en uso.
     *
     * @return string
     */
    public function getModelName();

    /**
     * ¿Está configurado el provider? (API key presente, etc.)
     *
     * @return bool
     */
    public function isConfigured();
}
