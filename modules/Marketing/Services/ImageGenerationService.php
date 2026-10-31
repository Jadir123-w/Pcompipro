<?php

namespace Modules\Marketing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Marketing\Contracts\ImageProviderInterface;

/**
 * ImageGenerationService — Wrapper de generación de imágenes para MKT-03.
 *
 * Usa un ImageProviderInterface para delegar la generación (DALL-E 3, Stability, etc.).
 * Centraliza logging, retry y manejo de errores.
 */
class ImageGenerationService
{
    /** @var ImageProviderInterface */
    private $provider;

    public function __construct(ImageProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Generar una imagen a partir de un prompt.
     *
     * @param  string  $prompt
     * @param  array   $options  aspect_ratio, quality, style, etc.
     * @return array
     */
    public function generate($prompt, array $options = [])
    {
        if (empty(trim($prompt))) {
            return [
                'success' => false,
                'error'   => 'Prompt vacío',
            ];
        }

        if (!$this->provider->isConfigured()) {
            Log::warning('ImageGenerationService: provider no configurado', [
                'provider' => $this->provider->getProviderName(),
            ]);
            return [
                'success' => false,
                'error'   => 'Provider de imagen no configurado: ' . $this->provider->getProviderName(),
            ];
        }

        return $this->provider->generateImage($prompt, $options);
    }

    /**
     * Editar/transformar imágenes usando referencias visuales.
     *
     * Solo funciona con providers que soportan multimodal (gpt-image-2, Nano Banana).
     * Si el provider activo no soporta editImage(), retorna error claro.
     *
     * @param  string  $prompt
     * @param  array   $referenceImagePaths  Rutas absolutas a 1+ imágenes
     * @param  array   $options              size, quality, aspect_ratio, format, mask_path
     * @return array
     */
    public function edit($prompt, array $referenceImagePaths, array $options = [])
    {
        if (empty(trim($prompt))) {
            return [
                'success' => false,
                'error'   => 'Prompt vacío',
            ];
        }

        if (!$this->provider->isConfigured()) {
            Log::warning('ImageGenerationService.edit: provider no configurado', [
                'provider' => $this->provider->getProviderName(),
            ]);
            return [
                'success' => false,
                'error'   => 'Provider de imagen no configurado: ' . $this->provider->getProviderName(),
            ];
        }

        if (!$this->provider->supportsImageEdit()) {
            return [
                'success' => false,
                'error'   => 'El provider activo (' . $this->provider->getProviderName() . ') no soporta edición con referencias. Usa gpt-image-2 o nano-banana.',
            ];
        }

        if (empty($referenceImagePaths)) {
            return [
                'success' => false,
                'error'   => 'edit() requiere al menos 1 imagen de referencia',
            ];
        }

        return $this->provider->editImage($prompt, $referenceImagePaths, $options);
    }

    /**
     * @return bool
     */
    public function supportsImageEdit()
    {
        return $this->provider->supportsImageEdit();
    }

    /**
     * @return string
     */
    public function getProviderName()
    {
        return $this->provider->getProviderName();
    }

    /**
     * @return string
     */
    public function getModelName()
    {
        return $this->provider->getModelName();
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return $this->provider->isConfigured();
    }
}
