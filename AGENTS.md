# AGENTS.md — Facturador PRO7 (erp_compipro)

Instrucciones permanentes para agentes de IA en este repositorio. **Léelo antes de
tocar nada** y mantenlo actualizado cuando cambien las convenciones.

> Objetivo del repo: ERP multi-tenant (Laravel 8 + Vue 2 + Element UI) para
> facturación electrónica peruana (SUNAT), comercial, pre-cotizaciones, etc.

---

## 1. Entorno (RUTAS EXACTAS — no están en el PATH)

| Qué | Ruta / comando |
|---|---|
| PHP **7.4** (el que usa Apache) | `E:\laragon\bin\php\php-7.4.33-nts-Win32-vc15-x86\php.exe` |
| PHP 8.3 (NO usar: rompe el bootstrap de Laravel 8 por deprecations) | `E:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe` |
| MySQL 8.4 (puerto **3308**) | `E:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root` |
| Git | `E:\laragon\bin\git\bin\git.exe` |
| Node/npm | `E:\note\npm.cmd` (npm NO está en el PATH) |
| Servidor web | Apache en `http://erp_compipro.test:8088` → **puerto 8084** (`erp_compipro.test`) |
| BD sistema | `tenancy` |
| BD tenant de pruebas | `tenancy_prueba` (p.ej. ítems de pre-cotización) |

Compilar frontend (desde la raíz del proyecto):
```powershell
$env:PATH = "E:\note;" + $env:PATH; & "E:\note\npm.cmd" run development
```

Lint PHP (usar SIEMPRE el 7.4):
```powershell
& "E:\laragon\bin\php\php-7.4.33-nts-Win32-vc15-x86\php.exe" -l <archivo.php>
```

---

## 2. Reglas del jefe (NO negociables)

1. **No crear funciones nuevas** cuando se puede modificar una existente.
2. **No tocar el código de IA/sugerencias**: solo dejar de llamarlo u ocultarlo.
3. **No hacer migraciones Laravel** en este proyecto: el SQL va en `sql/*.sql`
   y lo ejecuta el usuario a mano en cada tenant.
   - Las migraciones de **tenant** (las que corren sobre la BD del cliente) van en
     `database/migrations/tenant/`; las de sistema en `database/migrations/`.
4. **Los cambios se guardan al presionar "Guardar detalles"**, no en cada tecla
   (patrón buffer local en el front).
- Inspecciona únicamente archivos relevantes.
- No leas dependencias ni archivos generados innecesariamente.
- No reescribas archivos completos para cambios pequeños.
- No repitas explicaciones ni muestres código que ya existe.
- Ejecuta pruebas relacionadas con el cambio.
- Devuelve un resumen de máximo 3 viñetas.
- Si necesitas más contexto, solicita únicamente lo necesario.
---

## 3. Git / deploy

- Rama de trabajo: `dev-jadir` — repo `https://github.com/EvyKuper/erp_compipro.git`.
- Clone de push: `C:\Users\Usuario\AppData\Local\Temp\opencode\erp_push`
- El comando `/repo` **pushea** el working copy a `dev-jadir` y **agrega** (nunca
  reescribe) una sección fechada a `CAMBIOS_2026-10-14.txt`.
- `CAMBIOS_2026-10-14.txt` = bitácora de cambios. **Siempre** append, con fecha,
  causa raíz, archivos tocados y verificaciones.

---

## 4. Módulos y archivos clave

| Área | Ubicación |
|---|---|
| Pre-cotización (front) | `modules/Comercial/Resources/assets/js/view/requerimientos/TabPreCotizacion.vue` |
| Pre-cotización (API) | `modules/Marketing/Http/Controllers/Api/QuotationPreQuoteController.php` |
| Modelos marketing | `modules/Marketing/Models/PreQuote.php`, `PreQuoteItem.php`, `QuotationItemSupplier.php` |
| Pestaña Proveedores (oculta) | `modules/Comercial/Resources/assets/js/view/requerimientos/TabProveedores.vue` |
| SQL manual | `sql/` (p.ej. `quotation_items_supplier.sql`) |
| Rutas API | `modules/Marketing/Routes/api.php` y `web.php` |

---

## 5. Gotchas de Vue 2 + Element UI (¡causaron bugs reales!)

1. **NUNCA usar `:value` + `@change` en `el-input`/`el-select` para editar.**
   ElementUI descarta lo tipeado porque el prop `value` no cambia.
   → Usar `v-model` **siempre**. Con `el-radio` el evento es `@input` (no `@change`).
2. **El CSS global del tema oculta los `<input type="radio">` nativos**
   (`position:absolute; left:-9999px; opacity:0`). Para radios usables hay que
   usar `<el-radio>` de Element (dibuja su propio círculo).
3. **En tablas de Element, mutar el objeto de la fila en el sitio**:
   `this.$set(row, 'campo', valor)`. Si se reemplaza el objeto, el `v-model`
   sigue apuntando al viejo y la edición se pierde.
4. **Cuidado con `v-if`/`v-else-if`**: un `v-if` nuevo abre una cadena y deja
   huérfano al `v-else-if` siguiente (duplica contenido). Mantener UNA sola cadena.
5. Element UI 2.13: `el-radio` fuera de un `el-radio-group` usa `value`/`label`
   y emite `input`.

---

## 6. Tabla `quotation_items_supplier` (comparativa de proveedores, NUEVO)

Tabla **temporal** que reemplaza a `quotation_prequote_item_suppliers` (esa queda
intacta, sin usar). Definida en `sql/quotation_items_supplier.sql` (SQL manual, sin
migración). Modelo: `modules/Marketing/Models/QuotationItemSupplier.php`.

Columnas clave: `quotation_items_id` (FK cascade), `supplier_id`, `supplier_name`,
`origen` (siempre `'manual'`), `product_reference` (texto libre), `costo_base`,
`tiempo_estimado_dias`, `observaciones`, `seleccionado`, `user_id`, timestamps.
`v_unit` NO se guarda: se calcula en backend.

**Estructura vigente (2026-10-21):** `es_ganador` → **`seleccionado`** (0/1),
**NO existe la columna `orden`**, y `tiempo_entrega` → **`tiempo_estimado_dias`**.

### Reglas del modelo de comparativa

- **Única fuente de verdad**: `quotation_items_supplier`. `quotation_prequote_items`
  **NO** guarda `supplier_id`, `supplier_name` ni `costo_base` (ya no son editables
  desde el ítem). El motor de cálculo los resuelve con
  `QuotationCalculationEngine::resolveCostoBase()` (lee la fila con
  `seleccionado = 1`; si el ítem no tiene comparativa, cae al `costo_base` del ítem).
- **Exactamente una fila con `seleccionado = 1`** por ítem (la elegida). El radio
  de la columna **"Ganador"** la define; las demás quedan como **"Respaldo"**.
- La fila elegida refleja el **nombre actual** del producto en su
  `product_reference` (se sincroniza en `GET supplierQuotes`, `updateItem` y en el
  front con `markItemQuoteRow`).
- Cambiar de proveedor en el dropdown **limpia** `costo_base`,
  `tiempo_estimado_dias` y `observaciones` de esa fila.
- `user_id` de las filas = **usuario de apoyo** del requerimiento
  (`requerimiento.usuario_apoyo_cotizacion`), no el usuario de sesión.
  Ver `resolveUsuarioApoyo()` / `crearQuoteInicialItem()`.
- Los cambios son **locales** (buffer) hasta que se presiona **"Guardar
  detalles"** del ítem → `saveItem()` → `flushSupplierQuotes()`.
  - `flushSupplierQuotes()` = borra las que ya no están, crea las nuevas
    (mapeando temp id → id real), actualiza y marca el elegido.
  - Los "originales" (para detectar borrados) viven en `supplierQuoteOriginals[itemId]`
    (NO en `item._originalQuotes`: se perdía al re-renderizar).
- 6 endpoints en `QuotationPreQuoteController`: `supplierQuotes`, `storeSupplierQuote`,
  `updateSupplierQuote`, `destroySupplierQuote`, `setWinnerSupplierQuote`,
  `toCatalogSupplierQuote` (este último ya NO se usa desde el front).
- La pestaña **"Proveedores"** (IA de proveedores) está **oculta** con
  `v-if="false"` en `index.vue` y `kanban.vue`; el código queda intacto.
  El código de IA de proveedores NO se borra.
- **Kits ocultados momentáneamente** (2026-10-21): el colapso "Agrupación de kits"
  y el check de la columna "Kit" van con `v-if="false"`. Los kits YA creados se
  siguen viendo (con su botón "Disolver"). El código queda intacto.

---

## 7. Mapa de puntos de edición (dónde se trabaja)

Líneas aprox. al 2026-10-21. **Si cambiaron, busca por el nombre de la función.**
No hace falta leer el archivo entero: con estos anclajes se llega directo.

### `modules/Comercial/.../TabPreCotizacion.vue` (front de pre-cotización)

| Línea | Ancla | Qué es / para qué se toca |
|---|---|---|
| ~2206 | `onTipoLineaChange(item)` | Cambio de Tipo (Producto/Servicio/Comisión): normaliza campos y guarda |
| ~2247 | `isComision(item)` / `isService(item)` | Detectores de tipo de línea (usados por TODOS los `v-if` del front) |
| ~2256 | `async saveItem(item)` | Botón "Guardar detalles": PUT del ítem + `flushSupplierQuotes()` al final |
| ~686-760 | **tabla comparativa (copia 1**, ítem normal) | Columnas: Ganador / Proveedor / Origen / Referencia / Costo / Tiempo / Observaciones / Acción |
| ~1266-1340 | **tabla comparativa (copia 2**, componente de kit) | Idéntica a la copia 1 — **cualquier cambio se hace en las DOS** |
| ~1419 | columna "Producto solicitado" de la tabla de ítems | Input del nombre del producto (`v-model` + `@input="onItemNameChanged"`) |
| ~1552 | columna "Costo Base" de la tabla de ítems | **Solo lectura** (`campo-calculado`): muestra el costo del proveedor **seleccionado** |
| ~1604 | columna "V. Unit." de la tabla de ítems | `campo-calculado`, calculado con el costo del proveedor seleccionado |
| ~3385 | `supplierQuotesOf(item)` | Devuelve las filas que se pintan (siempre el buffer) |
| ~3394 | `initSupplierQuoteBuffer(item)` | Crea el buffer del ítem desde el mapa (marca `_esItemRow`) |
| ~3408 | `markSupplierQuoteDirty(item)` | Marca "pendiente de guardar" (el front **NO** llama al backend) |
| ~3412 | `async loadSupplierQuotes(item, force)` | GET de la comparativa; guarda `supplierQuoteOriginals` |
| ~3448 | `applyQuotesResponse(item, data)` | Resincroniza buffer+originales tras una respuesta del backend |
| ~3473 | `onQuoteSupplierChange(item, quote, supplierId)` | Cambio de proveedor → limpia costo/tiempo/observaciones |
| ~3485 | `isItemQuoteRow(quote)` | ¿Esta fila es la ELEGIDA? (`seleccionado = 1`) |
| ~3496 | `markItemQuoteRow(quotes, item)` | Normaliza: deja 1 elegida y su referencia = nombre actual |
| ~3515 | `selectedQuoteCostValue(item)` | Costo base (número) del proveedor **seleccionado** |
| ~3533 | `selectedQuoteCost(item)` | Lo anterior formateado ("S/ 12.00") |
| ~3539 | `quoteUnitValue(item, quote)` | V. Unit. de un proveedor (costo × (1 + margen%)) |
| ~3546 | `onItemNameChanged(item, newName)` | Renombró el producto → actualiza la referencia de la fila elegida |
| ~3557 | `itemUnitValue(item)` | V. Unit. del ítem con el costo del proveedor seleccionado |
| ~3561 | `saveSupplierQuote(item, quote, patch)` | Aplica un cambio **en el sitio** (`this.$set`) — nunca reemplazar el objeto |
| ~3592 | `addSupplierQuote(item)` | "+ proveedor": crea respaldo con `seleccionado = 0` |
| ~3617 | `removeSupplierQuote(item, quote)` | Borra (con `$confirm`) y promueve otra si era la elegida |
| ~3641 | `setWinnerQuote(item, quote)` | Radio "Ganador": marca la elegida en el buffer |
| ~3654 | `async flushSupplierQuotes(item)` | **Envía al backend** lo pendiente (borra/crea/actualiza/ganador) |
| ~3765 | `supplierOptionsFor(quote)` | Opciones del dropdown de proveedor (lista global + extras) |
| ~3776 | `filterSuppliers(query)` | Filtro **local** del combo (name + razón social + RUC) |
| ~3786 | `preloadAllSuppliers()` | Precarga TODOS los proveedores (para que el dropdown no salga vacío) |
| ~3798 | `async searchSuppliers(query)` | Búsqueda remota (la usa el resto del módulo) |

### `modules/Marketing/Http/Controllers/Api/QuotationPreQuoteController.php`

| Línea | Ancla | Nota |
|---|---|---|
| ~244 | `updateItem($request, $id, $itemId)` | Actualiza el ítem. **NO** edita supplier/costo_base (viven en la comparativa) |
| ~957 | `reinterpretWithImage($request, $id)` | Re-interpreta con imagen; crea ítem **+ su fila de comparativa** |
| ~1117 | `interpretItem($request, $requerimientoId)` | Crea ítems por IA **+ su fila de comparativa** |
| ~1181 | `storeManualItem($request, $requerimientoId)` | Alta manual del ítem **+ su fila de comparativa** |
| ~1862 | `applySupplier($request, $id, $itemId)` | IA de proveedores: escribe en la fila **elegida** de la comparativa |
| ~1979 | `resolveSupplierName($supplierId)` | `persons.id` → nombre del proveedor ("Proveedor por definir" si no hay) |
| ~1982 | `syncWinningQuoteToItem(...)` | Recalcula; **NO** copia proveedor/costo al ítem (solo marca `is_inferred`) |
| ~2005 | `crearQuoteInicialItem($prequote, $item, $request)` | Crea la fila inicial de la comparativa con el usuario de apoyo |
| ~2028 | `resolveUsuarioApoyo($prequote, $request)` | `requerimiento.usuario_apoyo_cotizacion` (fallback: usuario de sesión) |
| ~2050 | `supplierQuotes($request, $id, $itemId)` | GET: siembra si no hay filas, **ordena y re-sincroniza** |
| ~2105 | `storeSupplierQuote(...)` | POST: crea respaldo (`seleccionado = 0`) o la 1ª fila (`seleccionado = 1`) |
| ~2150 | `updateSupplierQuote(...)` | PUT: actualiza referencia/costo/tiempo/observaciones |
| ~2210 | `destroySupplierQuote(...)` | DELETE: si borra la elegida, promueve la más antigua |
| ~2290 | `setWinnerSupplierQuote(...)` | POST: marca elegida + recalcula (y limpia el "Cód. Artíc." si cambió) |
| ~2350 | `toCatalogSupplierQuote(...)` | **NO se usa desde el front** (botón "Agregar catálogo" eliminado) |

### `QuotationCalculationEngine` (motor de costeo)

| Ancla | Qué hace |
|---|---|
| `resolveCostoBase(PreQuoteItem)` | Costo base = fila con `seleccionado = 1` de la comparativa (fallback: `item.costo_base`) |
| `recalculateItem(PreQuoteItem)` | Usa `resolveCostoBase()` en los 3 puntos donde antes leía `$item->costo_base` |

### Otros puntos sensibles

- `modules/Marketing/Models/QuotationItemSupplier.php` — casts (`costo_base` decimal, `seleccionado` int).
- `sql/quotation_items_supplier.sql` — CREATE TABLE idempotente (`IF NOT EXISTS`).
- `modules/Comercial/.../index.vue` y `kanban.vue` — pestaña "Proveedores" con `v-if="false"`.
- `database/migrations/tenant/` — migraciones de tenant (kits: `2026_10_15_000001_create_quotation_proposal_kit_tables.php`).

---

## 8. Pre-cotización: tipos de línea

`product` | `service` | `comision` (campo `tipo_linea` en `quotation_prequote_items`).

- **comisión**: nombre fijo "COMISIÓN", cantidad 1, margen 0, sin catálogo.
- **servicio**: sin match de catálogo, sin proveedores; editables SOLO
  Costo Base, Cantidad, Margen %, Descripción y Observaciones. El resto en 0.
  El campo "Producto solicitado" SÍ es editable.
- **producto**: flujo completo (IA, match de catálogo, comparativa, kits).

---

## 9. Checklist antes de terminar

- [ ] `php -l` en cada PHP tocado (con PHP **7.4**).
- [ ] `npm run development` compila sin errores.
- [ ] Sin registros duplicados en BD (limpiar datos de prueba).
- [ ] `CAMBIOS_2026-10-14.txt` con la sección fechada (causa raíz + verificaciones).
- [ ] `ESTADO_ACTUAL.md` actualizado si cambió el estado del trabajo.

---

## 10. Contexto de una sesión nueva

Si la conversación se recorta por límite de tokens, NO re-preguntes: lee
`ESTADO_ACTUAL.md` (estado del trabajo) y `CAMBIOS_2026-10-14.txt` (bitácora).
Ahí está todo lo necesario para continuar sin contexto previo.

