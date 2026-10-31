-- Registrar Gemini 3.1 Flash Image (Nano Banana 2) como proveedor de imagen.
-- Ejecutar manualmente en cada tenant despues de crear marketing_ai_providers.
-- Re-ejecutable: actualiza los metadatos sin cambiar el estado enabled del usuario.

INSERT INTO `marketing_ai_providers` (
    `provider_key`,
    `capability`,
    `display_name`,
    `description`,
    `default_model`,
    `supported_models`,
    `supports_image_edit`,
    `supported_qualities`,
    `supported_aspect_ratios`,
    `base_url`,
    `api_key_env_var`,
    `max_tokens_default`,
    `enabled`,
    `sort_order`,
    `pricing_notes`,
    `created_at`,
    `updated_at`
) VALUES (
    'nano-banana',
    'image',
    'Google Nano Banana 2',
    'Generacion y edicion de imagenes con Gemini 3.1 Flash Image.',
    'gemini-3.1-flash-image',
    JSON_ARRAY('gemini-3.1-flash-image'),
    1,
    NULL,
    JSON_ARRAY('1:1', '3:2', '2:3', '4:3', '3:4', '4:5', '5:4', '16:9', '9:16', '21:9'),
    'https://generativelanguage.googleapis.com/v1beta/models',
    'CHATBOT_GEMINI_API_KEY',
    NULL,
    1,
    25,
    'Consultar precios vigentes de Gemini API.',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    `display_name` = VALUES(`display_name`),
    `description` = VALUES(`description`),
    `default_model` = VALUES(`default_model`),
    `supported_models` = VALUES(`supported_models`),
    `supports_image_edit` = VALUES(`supports_image_edit`),
    `supported_qualities` = VALUES(`supported_qualities`),
    `supported_aspect_ratios` = VALUES(`supported_aspect_ratios`),
    `base_url` = VALUES(`base_url`),
    `api_key_env_var` = VALUES(`api_key_env_var`),
    `sort_order` = VALUES(`sort_order`),
    `pricing_notes` = VALUES(`pricing_notes`),
    `updated_at` = VALUES(`updated_at`);

-- Agregar el proveedor y el modelo a las opciones de la configuracion legacy de MKT-03.
UPDATE `marketing_chatbot_config`
SET `options` = JSON_ARRAY(
        JSON_OBJECT('value', 'dalle3', 'label', 'DALL-E 3'),
        JSON_OBJECT('value', 'gpt-image-2', 'label', 'GPT-Image-2'),
        JSON_OBJECT('value', 'nano-banana', 'label', 'Google Nano Banana 2 (Gemini 3.1 Flash Image)')
    ),
    `updated_at` = NOW()
WHERE `config_key` = 'mkt03_image_provider';

UPDATE `marketing_chatbot_config`
SET `options` = JSON_ARRAY(
        JSON_OBJECT('value', 'dall-e-3', 'label', 'dall-e-3 (provider dalle3)'),
        JSON_OBJECT('value', 'gpt-image-2', 'label', 'gpt-image-2 (provider gpt-image-2)'),
        JSON_OBJECT('value', 'gemini-3.1-flash-image', 'label', 'gemini-3.1-flash-image (provider nano-banana)')
    ),
    `updated_at` = NOW()
WHERE `config_key` = 'mkt03_image_model';
