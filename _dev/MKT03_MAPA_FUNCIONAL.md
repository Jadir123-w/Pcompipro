# MKT-03 — mapa funcional y técnico

**Revisado:** 2026-11-16  
**Alcance:** planificación editorial, producción de piezas e imágenes, persistencia, configuración, tablas y relación con cotizaciones.

## 1. Resumen

MKT-03 es el flujo de contenido para redes sociales. Organiza grillas e ítems editoriales, genera copy y prompts de imagen, y permite producir o revisar piezas. Sus datos se guardan principalmente en tablas tenant `mkt_*`.

El recorrido principal es:

```text
Históricos + patrones + reglas por red + ferias/extraordinarios aprobados
                                │
                                ▼
             Generación de grilla e ítems (IA de texto)
                                │
                                ▼
            Producción de pieza desde un ítem (IA de texto)
                 ├── copy, CTA, hashtags y prompt visual
                 └── imagen opcional (proveedor de imagen)
                                │
                                ▼
                  revisión / edición / estado de pieza
```

**No se encontró integración directa entre MKT-03 y las cotizaciones.** El campo `linea_comercial` es una etiqueta de texto en el ítem de grilla; no es una relación con un producto cotizado, una pre-cotización o una cotización formal. La palabra «cotiza» puede aparecer en el copy/CTA generado para redes, pero eso no crea ni consulta una cotización.

## 2. Pantallas y superficies

La vista [mkt03/index.vue](../modules/Marketing/Resources/assets/js/views/mkt03/index.vue) reúne estas pestañas:

| Pestaña | Componente | Función |
|---|---|---|
| Dashboard | `components/mkt03/Dashboard.vue` | Resumen de grillas, piezas aprobadas y ferias activas. |
| Grillas | `components/mkt03/GridEditor.vue` | Crear/consultar grillas, filtrar ítems, editar los no producidos y solicitar producción de pieza. |
| Piezas | `components/mkt03/PieceProducer.vue` | Revisar, editar, aprobar, eliminar y regenerar imagen de una pieza. |
| Histórico | `components/mkt03/HistoricalLibrary.vue` | Subir Excel, parsear contenido y analizar patrones. |
| Extraordinario | `components/mkt03/ExtraordinaryManager.vue` | Registrar una solicitud extraordinaria y decidir si sirve para futuras grillas. |
| Ferias | `components/mkt03/FeriasView.vue` | Registrar ferias y generar los ítems editoriales asociados. |
| Configuración | `components/mkt03/PlatformConfig.vue` | Ajustar reglas por red y revisar/aprobar patrones aprendidos. |

La interfaz no contiene una pestaña de cotizaciones ni una acción que navegue al cotizador desde MKT-03.

## 3. Flujo de producción de pieza e imagen

### 3.1 Pieza desde un ítem de grilla

1. `GridEditor.vue` envía `POST /marketing/mkt03/items/{itemId}/produce`.
2. `MKT03PieceController::produce()` impide producir de nuevo si el ítem ya tiene piezas y llama al `MKT03Orchestrator`.
3. `PieceProducerEngine` arma el contexto con los campos del `GridItem` y la configuración editorial de `mkt_platform_config`.
4. El proveedor **de texto** devuelve JSON con `pieza_producida`: red, copy, CTA, hashtags, formato, enfoque visual, texto para imagen/video, `imagen_prompt` y `aspect_ratio`.
5. Se crea la fila de `mkt_pieces`. Si se pidió imagen, `ImageGenerationService` la genera y se actualizan `imagen_final_url`, proveedor/modelo de imagen y estado.

El prompt de texto está en [mkt03-piece-producer-prompt.md](../modules/Marketing/Prompts/mkt03-piece-producer-prompt.md); las reglas consolidadas por red y de identidad COMPIPRO se agregan desde [EditorialGuidelinesService.php](../modules/Marketing/Services/EditorialGuidelinesService.php). El prompt describe una imagen estática; para TikTok también deja el brief audiovisual a producción humana, no genera el video.

### 3.2 Cuándo se genera la imagen

- En la pantalla de grillas, `producePiece()` **manda explícitamente `generate_image: false`**. Por tanto, el botón de producción crea la pieza textual y deja la generación de imagen para después.
- La pantalla de piezas permite regenerar mediante `POST /marketing/mkt03/pieces/{id}/regenerate-image`. Envía el `imagen_prompt` guardado; **no vuelve a llamar al modelo de texto** ni recalcula el prompt a partir de cambios posteriores al copy o enfoque visual.
- El endpoint de producción, si se invoca sin `generate_image`, toma el valor de `marketing.chatbot.mkt03.auto_generate_image` (fallback de configuración `.env`). La llamada de la UI lo sobreescribe con `false`; por ello, activar la variable por sí sola no cambia el comportamiento de ese botón.
- El modo de ferias puede pedir la producción completa con `produce_pieces=true`; ese flujo llama al productor con generación de imagen activada.

### 3.3 Proveedores, referencias y almacenamiento

El contrato común es [ImageProviderInterface.php](../modules/Marketing/Contracts/ImageProviderInterface.php), envuelto por [ImageGenerationService.php](../modules/Marketing/Services/ImageGenerationService.php). Los proveedores de imagen se resuelven para el agente `mkt03` desde `marketing_agent_config` y `marketing_ai_providers`; si no hay configuración de agente aplicable, hay fallback a configuración Laravel/.env.

| Proveedor | Generación | Edición con referencias |
|---|---|---|
| `dalle3` | OpenAI DALL-E 3 | No soportada. |
| `gpt-image-2` | OpenAI GPT Image | Soportada por el proveedor. |
| `nano-banana` | Gemini (`gemini-3.1-flash-image`) | Soportada por el proveedor. |
| `cloudflare` | Cloudflare Flux Schnell | No soportada. |

Los proveedores guardan el resultado en el disk configurado (por defecto `public`) y en el path `mkt03/pieces`; `mkt_pieces.imagen_final_url` conserva la URL que presenta la UI. DALL-E descarga su URL temporal antes de guardarla localmente. La subida de imagen manual tiene un endpoint `POST /marketing/mkt03/pieces/{id}/upload-image`, acepta imagen de hasta 10 MB y la guarda en el mismo path.

La interfaz actual sí expone regeneración, pero no se encontró llamada a `upload-image`. Tampoco hay endpoint web de edición con referencias: `ImageGenerationService::edit()` existe y el comando [Mkt03MaintenanceCommand.php](../modules/Marketing/Console/Mkt03MaintenanceCommand.php) lo usa para la utilidad de mantenimiento; no forma parte del flujo normal de `PieceProducer.vue`.

El repositorio incluye [BrandAsset.php](../modules/Marketing/Models/BrandAsset.php) y el Brand Asset Library (`marketing_brand_assets`), con assets marcables para MKT-03 y helpers de rutas de referencia. Sin embargo, no se encontró que `PieceProducerEngine` o `ImageGenerationService` los consulte automáticamente al producir una pieza.

### 3.4 Parámetros de imagen que conviene verificar

Hay una diferencia entre los parámetros configurados y los que reciben algunos caminos:

- `PieceProducerEngine::generateImageForPiece()` pasa `quality: standard` y `style: vivid`.
- `MKT03PieceController::regenerateImage()` también usa `standard` por defecto; la UI de regeneración manda solo el prompt.
- `gpt-image-2` documenta/calcula calidad `low`, `medium`, `high` o `auto`; por tanto, con ese proveedor, el valor explícito `standard` puede ser rechazado por la API. Aunque el constructor resuelve calidad desde configuración, una opción explícita del llamado tiene precedencia.
- La migración inicial de claves `marketing_chatbot_config` enumera DALL-E 3 y GPT Image; existen migraciones posteriores para sembrar Nano Banana y Cloudflare. La configuración realmente disponible depende de las migraciones/seed aplicadas en cada tenant; no se consultó la base de datos de cada tenant en este rastreo.

Antes de habilitar generación automática con un proveedor distinto de DALL-E, revisar calidad, formato y modelo efectivo en ese camino concreto; no asumir que la configuración del proveedor se aplica a todos los llamados.

## 4. Tablas y relaciones

Las tablas de negocio se crean/ajustan en `database/migrations/tenant/`. El modelo de tenant [ModelTenant](../app/Models/Tenant/ModelTenant.php) es la base de los modelos MKT-03.

| Tabla | Modelo | Uso y relación principal |
|---|---|---|
| `mkt_grids` | `Grid` | Cabecera de grilla por período/red; tiene ítems y puede referenciar histórico base. |
| `mkt_grid_items` | `GridItem` | Fecha, red, temática, campaña, objetivo, línea comercial, tipo, prioridad y origen; pertenece a una grilla y puede tener piezas vigentes/históricas. Puede referenciar feria o extraordinario. |
| `mkt_pieces` | `Piece` | Copy, CTA, hashtags, prompt, URL de imagen, proveedor/modelo, estado y metadatos de publicación; pertenece a ítem y/o contenido extraordinario. |
| `mkt_platform_config` | `PlatformConfig` | Reglas editoriales, límites de copy/hashtags, formatos y aspecto por red. |
| `marketing_ai_providers` | `AiProvider` | Catálogo tenant de proveedores y modelos. Los proveedores legacy resuelven la API key desde entorno/configuración; los nuevos pueden usar el conector `openai_compatible`, con API key cifrada en `api_key_encrypted`. |
| `marketing_agent_config` | `AgentConfig` | Selección por agente. La fila `agent_key=mkt03` apunta al proveedor de imagen mediante `image_provider_id` y puede fijar modelo, calidad, formato, aspecto por defecto y generación automática. |
| `marketing_chatbot_config` | `ChatbotConfig` | Configuración legacy con claves `mkt03_image_provider`, `mkt03_image_model`, `mkt03_image_quality` y `mkt03_image_format`; no confundir con el catálogo ni con el almacenamiento de API keys. |
| `mkt_historical_grids` | `HistoricalGrid` | Archivo Excel, estructura detectada y contenido normalizado; origina patrones y puede servir de base de grillas. El archivo queda en `mkt03/historical/`. |
| `mkt_learning_patterns` | `LearningPattern` | Patrones analizados desde históricos o extraordinarios; filtra activos y confianza para nuevas grillas. |
| `mkt_extraordinary_content` | `ExtraordinaryContent` | Solicitudes fuera de grilla y decisión de reutilizarlas en el futuro. |
| `mkt_ferias_received` | `FeriaReceived` | Datos de feria y redes destino; tiene ítems de grilla, normalmente en fases 2 meses, 1 mes y 15 días antes. |
| `mkt_scheduled_publications` | — | Preparación para programación/publicación futura; no es el flujo activo de producción asistida. |
| `mkt_social_credentials` | — | Esqueleto para credenciales OAuth de futura publicación automática. |

Relaciones de código más relevantes:

```text
Grid 1 ── N GridItem 1 ── N Piece
                       ├── N Piece vigente (currentPiece)
                       ├── 0..1 FeriaReceived
                       └── 0..1 ExtraordinaryContent
HistoricalGrid 1 ── N LearningPattern
HistoricalGrid 1 ── N Grid (como histórico base)
ExtraordinaryContent 1 ── N Piece / LearningPattern
FeriaReceived 1 ── N GridItem
```

Los IDs y las relaciones anteriores están declarados en modelos/Eloquent. En las migraciones consultadas se ven columnas e índices para esas referencias; no se observan restricciones SQL `FOREIGN KEY` para imponerlas en la base de datos.

### Configuración compartida relevante

- `marketing_agent_config`: selección de proveedor/modelo de texto e imagen por agente (`agent_key=mkt03`), calidad/formato/aspecto y opción de auto-generación.
- `marketing_ai_providers`: catálogo tenant de proveedores, modelos y capacidades. Las filas legacy mantienen sus variables de entorno y clases existentes; los nuevos proveedores registrados desde la interfaz usan `adapter_type=openai_compatible` y una API key cifrada en `api_key_encrypted`. El conector requiere las rutas estándar `/models` y `/chat/completions` para texto o `/images/generations` para imagen.
- La interfaz permite cargar modelos para proveedores compatibles y probar con «Hola» proveedores de texto legacy o compatibles. No genera imágenes de prueba; los proveedores de imagen se registran sin consumir créditos desde el panel.
- `marketing_chatbot_config`: migración de configuración legacy con claves `mkt03_image_provider`, `mkt03_image_model`, `mkt03_image_quality`, `mkt03_image_format` y `mkt03_auto_generate_image`.
- `mkt_pieces`: guarda en cada pieza generada el proveedor y modelo efectivamente usados (`ai_image_provider`, `ai_image_model`), además de la URL final; es historial de uso, no configuración del proveedor.
- `marketing_brand_assets`: librería de marca compartida; existe, pero no está conectada automáticamente a la generación regular según el código revisado.
- Variables de fallback visibles en [.env.example](../.env.example): `MKT03_IMAGE_PROVIDER`, `MKT03_IMAGE_MODEL`, `MKT03_IMAGE_FORMAT`, `MKT03_IMAGE_QUALITY`, `MKT03_IMAGE_STORAGE_DISK` y `MKT03_IMAGE_STORAGE_PATH`; credenciales de los proveedores también son necesarias.

**Alta de proveedores desde la interfaz:** en Configuración de agentes se agrega un proveedor compatible indicando nombre, identificador único (minúsculas, números, guion o guion bajo), capacidad, URL base HTTPS, API key y modelo por defecto. El panel puede cargar los modelos disponibles desde `/models` y, para proveedores de texto, hacer una prueba breve con «Hola»; no realiza pruebas de generación de imágenes. Al guardar, la API key se cifra en `api_key_encrypted`; luego se asigna el proveedor al agente y, si corresponde, se elige el modelo para que MKT-03 lo use. Los registros legacy conservan sus adaptadores y credenciales actuales, por lo que no es necesario volver a registrarlos. Para habilitar este catálogo dinámico deben estar aplicadas las migraciones tenant correspondientes; los proveedores de texto deben ofrecer una API compatible con `/chat/completions` y los de imagen con `/images/generations`.

Las migraciones centrales del esquema son `2026_04_16_000001` a `000008` para grillas, ítems, piezas, extraordinarios, ferias, históricos, configuración de plataformas y patrones. `000009` agrega texto en imagen/video; `2026_04_16_000012` crea las tablas de publicación/OAuth en estado de esqueleto. Las seeds de proveedores y las claves de imagen están en migraciones posteriores; `2026_11_18_000001` agrega el adaptador y el campo cifrado para proveedores nuevos sin modificar los registros legacy.

## 5. Relación con cotizaciones

En las rutas, componentes, controladores, servicios, modelos y migraciones de MKT-03 revisados:

- No se encontró uso de `PreQuote`, `PreQuoteItem`, `QuotationProposal`, `quotation_prequote_items` ni de las tablas de proveedores/cotización.
- No se encontró endpoint de MKT-03 que consulte, cree o actualice una cotización.
- No hay FK de `mkt_grid_items.linea_comercial` a catálogo, ítem comercial o pre-cotización: es un `string` editorial.
- La pieza de imagen se relaciona con `mkt_grid_items`/contenido extraordinario, no con una cotización ni con un producto cotizado.
- «Cotiza tu kit», y textos similares, son posibles CTA de marketing generados por el prompt, no un enlace a un proceso de cotización.

Por lo tanto, la relación existente es **temática/comunicacional**, no de datos ni de flujo. Para enlazar una pieza a una cotización habría que definir explícitamente qué entidad se vincula y en qué momento; no se debe inferir la relación usando el texto de `linea_comercial` o del copy.

## 6. Otros modos que alimentan la producción

- **Grillas:** [GridGeneratorEngine.php](../modules/Marketing/Services/GridGeneratorEngine.php) compone el contexto con `mkt_platform_config`, patrones activos con confianza mínima, ferias activas y extraordinarios aprobados. Produce ítems; no crea las piezas finales.
- **Históricos:** [ExcelHistoricalImporter.php](../modules/Marketing/Services/ExcelHistoricalImporter.php) normaliza el Excel en `mkt_historical_grids`; [GridPatternAnalyzer.php](../modules/Marketing/Services/GridPatternAnalyzer.php) guarda patrones en `mkt_learning_patterns`.
- **Continuidad:** `GridContinuityService` detecta grillas que requieren extensión; el controlador puede consultar candidatos y ejecutar la ampliación.
- **Ferias:** el endpoint puede crear ítems con fases 2m/1m/15d; `produce_pieces` es opcional y, si está activado, intenta producir también las piezas e imágenes.
- **Extraordinarios:** el flujo actual registra la solicitud y la decisión de reutilizarla. `createExtraordinary()` indica que la pieza puede producirse por separado desde un `GridItem`; no ejecuta por sí mismo la producción final.
- **Publicación automática:** las tablas `mkt_scheduled_publications` y `mkt_social_credentials` están documentadas como Etapa 2. El comando de mantenimiento las describe como una funcionalidad futura, no como una publicación social operativa.

Las instrucciones editoriales base están en `Prompts/mkt03-grid-generator-prompt.md`, `Prompts/mkt03-grid-analyzer-prompt.md` y `Prompts/mkt03-piece-producer-prompt.md`. [MKT03_Manual_De_Uso.docx](../modules/Marketing/MKT03_Manual_De_Uso.docx) es la guía de usuario adjunta al módulo.

## 7. Estados y cautelas de datos

Los valores canónicos actuales de `Piece::ESTADOS` son `pendiente`, `por_revisar`, `aprobado`, `publicado`, `observada` y `programado`. La UI y los endpoints de cambio de estado usan ese conjunto. El campo `aprobado` representa la pieza lista para publicar; `publicado` registra publicación. El controlador también completa `approver_id` y `published_at` al entrar en esos estados.

La bitácora `_dev/CAMBIOS_2026-11-11.txt` advierte que los registros tenant existentes podrían conservar valores anteriores (`en_produccion`, `lista_publicar`, `publicada`, `descartada`) porque el cambio de constantes no migró esos datos. Además, el comentario de estados en la migración inicial no coincide exactamente con los valores canónicos actuales. Verificar los datos por tenant antes de depender de filtros/reportes o de ejecutar una conversión de datos.

## 8. Rutas/API principales

Todas usan el prefijo `/marketing/mkt03` salvo la vista web y el Brand Asset Library.

| Área | Rutas |
|---|---|
| Grillas/ítems | `POST grids/generate`, `GET grids`, `GET grid-items`, `GET grids/{id}`, `POST grids/{id}/extend`, `GET/POST continuity/*`, CRUD de `grids/{id}/items`, cambio de estado y borrado de grilla. |
| Piezas/imágenes | `POST items/{itemId}/produce`, `GET pieces`, `GET/PUT/DELETE pieces/{id}`, `POST pieces/{id}/regenerate-image`, `POST pieces/{id}/upload-image`, `PUT pieces/{id}/status`. |
| Históricos/patrones | CRUD/consulta de históricos, importación, parseo y análisis; consulta, aprobación y borrado de patrones. |
| Extraordinarios | Consulta/alta, decisión para futuras grillas y borrado. |
| Ferias | Consulta/alta, generación de ítems, recepción idempotente desde agente externo, estado y borrado. |
| Reglas por red | `GET platform-config`, `GET/PUT platform-config/{red}`. |

La definición fuente está en [Routes/web.php](../modules/Marketing/Routes/web.php); los controladores específicos son `MKT03GridController`, `MKT03PieceController`, `MKT03ConfigController`, `MKT03ExtraordinaryController` y `MKT03FeriaController`.

## 9. Pruebas y documentación de referencia

Pruebas relevantes del repositorio:

- `tests/Feature/Marketing/Mkt03DocFuncionalCasosTest.php`
- `tests/Feature/Marketing/Mkt03ContinuityTest.php`
- `tests/Feature/Marketing/Mkt03ModoFFeriasTest.php`
- `tests/Feature/Marketing/Mkt03ConfigBdFirstTest.php`
- `tests/Unit/Marketing/` (incluye pruebas de proveedores de imagen; consultar los archivos existentes al validar cambios).

La utilidad de CRUD del módulo está en `modules/Marketing/Tests/mkt03_crud_test.php`. Los detalles de uso, decisiones y pendientes de desarrollo también aparecen en `_dev/AGENTS.md`, `_dev/ESTADO_ACTUAL.md` y `_dev/CAMBIOS_2026-11-11.txt`.
