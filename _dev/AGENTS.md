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
- **Ojo: el clone de push NO se sincroniza solo.** Si el remoto avanzó (por un
  push desde GitHub web o desde otra copia), el push se rechaza con
  "fetch first". Antes de pushear: `git fetch origin` y, si hay commits remotos
  nuevos, `git rebase origin/dev-jadir` (resolver a favor del working copy, que
  es la fuente de verdad) y `git push`.
- Hay **3 copias** del repo (working copy, clone de push y GitHub). Si se sube
  desde GitHub web, hay que hacer `fetch` + `rebase` en el clone antes de pushear.

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
| MKT-03 grillas e ítems (front) | `modules/Marketing/Resources/assets/js/components/mkt03/GridEditor.vue` |
| MKT-03 grillas e ítems (API) | `modules/Marketing/Http/Controllers/Api/MKT03GridController.php` |
| MKT-03 producción de piezas | `modules/Marketing/Http/Controllers/Api/MKT03PieceController.php`, `modules/Marketing/Services/MKT03Orchestrator.php`, `modules/Marketing/Services/PieceProducerEngine.php` |
| MKT-03 generación de grillas | `modules/Marketing/Services/GridGeneratorEngine.php` |

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
- **`ganancia_absoluta`** (columna "Margen (S/)") se calcula con el costo del
  proveedor **seleccionado**, no con la columna vieja `costo_base` del ítem:
  `PreQuoteItem::getGananciaAbsolutaAttribute()` usa la relación
  `winningItemSupplier` (OJO: `winningQuote()` devuelve la relación, no el modelo).
- En la **fila de la tabla de ítems** todo es EN VIVO (Costo Base, V. Unit.,
  Margen (S/) y Total). "Total" lleva IGV (igual que `total_item` del motor);
  los totales del encabezado (Subtotal/IGV/Total) son los persistidos.
- La **"V. Unit." de cada fila de la comparativa** es el unitario FINAL que
  saldría si ese proveedor fuera el ganador (= Total sin IGV ÷ Q, con muestras,
  impresión, empaque y SCTR). Sale de `costeoBreakdown(item, costoDeEseProveedor)`,
  o sea la misma cadena del motor: el número es el que se guardaría al elegirlo.
- **"V. unit." es UN SOLO concepto** (mockup V24): el unitario final de venta
  (Total sin IGV ÷ Q). Da el MISMO número en la fila del ítem
  (`itemUnitValue`), en la fila del proveedor ganador (`quoteUnitValue`) y en el
  "Costo unitario (sin IGV)" de la tarjeta. Con eso la fila cierra:
  V. unit. × Q = Total sin IGV, y Total = × (1 + IGV).
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
| ~686-760 | **tabla comparativa (copia 1**, ítem normal) | Columnas: Ganador / Proveedor / Origen / Referencia / Costo / **V. Unit.** / Tiempo / Observaciones / Acción. La "V. Unit." de cada proveedor = unitario final si ese proveedor fuera el ganador (`quoteUnitValue`) |
| ~1266-1340 | **tabla comparativa (copia 2**, componente de kit) | Idéntica a la copia 1 — **cualquier cambio se hace en las DOS** |
| ~1419 | columna "Producto solicitado" de la tabla de ítems | Input del nombre del producto (`v-model` + `@input="onItemNameChanged"`) |
| ~1552 | columna "Costo Base" de la tabla de ítems | Comisión: editable en la fila y se guarda con "Guardar detalles"; producto/servicio: muestra el costo del proveedor **seleccionado** |
| ~1600 | columna "Margen (S/)" de la tabla de ítems | `campo-calculado` **en vivo** (`itemGainValue`): Q × costo del ganador × margen% |
| ~1604 | columna "V. Unit." de la tabla de ítems | `campo-calculado` **en vivo** (`itemUnitValue`): unitario final (Total sin IGV ÷ Q) con el costo del proveedor seleccionado |
| ~1622 | columna "Total" de la tabla de ítems | `campo-calculado` **en vivo** (`itemTotalValue`): total **con IGV**, igual que `total_item` del motor |
| ~3385 | `supplierQuotesOf(item)` | Devuelve las filas que se pintan (siempre el buffer) |
| computed `productosSinProveedor()` | Bloquea Validar/Aprobar según la fila elegida persistida en `supplierQuotesMap[item.id]` con `supplier_id`; no contar el buffer sin guardar ni consultar `item.supplier_id` legado |
| ~3394 | `initSupplierQuoteBuffer(item)` | Crea el buffer del ítem desde el mapa (marca `_esItemRow`) |
| ~3408 | `markSupplierQuoteDirty(item)` | Marca "pendiente de guardar" (el front **NO** llama al backend) |
| ~3412 | `async loadSupplierQuotes(item, force)` | GET de la comparativa; guarda `supplierQuoteOriginals` |
| ~3448 | `applyQuotesResponse(item, data)` | Resincroniza buffer+originales tras una respuesta del backend |
| ~3473 | `onQuoteSupplierChange(item, quote, supplierId)` | Cambio de proveedor → limpia costo/tiempo/observaciones |
| ~3485 | `isItemQuoteRow(quote)` | ¿Esta fila es la ELEGIDA? (`seleccionado = 1`) |
| ~3496 | `markItemQuoteRow(quotes, item)` | Normaliza: deja 1 elegida y su referencia = nombre actual |
| ~2727 | `costeoBreakdown(item, costoBaseAlterno)` | Cadena de costeo de la línea (espejo del motor). El 2º parámetro, opcional, **fuerza el costo base**: lo usa la comparativa para el unitario hipotético de cada proveedor |
| ~3524 | `itemUnitValue(item)` | V. Unit. de la línea: unitario **final** (Total sin IGV ÷ Q, con muestras, impresión, empaque y SCTR). Mismo número que la "V. Unit." del proveedor ganador y que el "Costo unitario (sin IGV)" de la tarjeta |
| ~3533 | `selectedQuoteCostValue(item)` | Costo base (número) del proveedor **seleccionado** |
| ~3539 | `quoteUnitValue(item, quote)` | "V. Unit." de un proveedor: unitario FINAL si ese proveedor fuera el ganador (Total sin IGV ÷ Q, con muestras, impresión, empaque y SCTR). Usa `costeoBreakdown(item, costoDeEseProveedor)` |
| ~3561 | `itemTotalValue(item)` | Total de la línea **en vivo** (con IGV, como `total_item`) |
| ~3570 | `itemGainValue(item)` | Margen en soles de la línea **en vivo** (costo del ganador) |
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
- `modules/Marketing/Models/PreQuoteItem.php` — `getGananciaAbsolutaAttribute()`
  (ganancia con el costo del proveedor ganador) y `winningItemSupplier()`.
- `sql/quotation_items_supplier.sql` — CREATE TABLE idempotente (`IF NOT EXISTS`).
- `sql/quotation_prequote_items_tipo_linea.sql` — agrega de forma idempotente `tipo_linea` (`product`, `service`, `comision`) a `quotation_prequote_items`.
- `modules/Comercial/.../index.vue` y `kanban.vue` — pestaña "Proveedores" con `v-if="false"`.
- `sql/quotation_proposal_kit_tables.sql` — tablas tenant de kits (SQL manual; no migración Laravel).

---

## 8. Pre-cotización: tipos de línea

`product` | `service` | `comision` (campo `tipo_linea` en `quotation_prequote_items`).

- **comisión**: nombre fijo "COMISIÓN", cantidad 1, margen 0, sin catálogo ni
  comparativa. Costo Base editable en la fila y persistido en el ítem. Su acordeón
  muestra solo la descripción, sin "Mi Resultado del costeo".
- **servicio**: sin match de catálogo; conserva comparativa de proveedores.
  Su acordeón muestra solo Descripción, Observaciones y comparativa; sin detalles
  técnicos ni paneles de costeo. Cantidad y Margen % se editan en la fila.
  El campo "Producto solicitado" SÍ es editable.
- **producto**: flujo completo (IA, match de catálogo, comparativa, kits).

---

## 9. MKT-03: grillas e ítems

- La tabla principal de `GridEditor.vue` lista ítems paginados mediante
  `GET /marketing/mkt03/grid-items`; filtros de red, año y fechas se aplican
  desde ese endpoint.
- Los ítems sin piezas muestran **Producir** y **Editar**. Los que ya tienen
  una pieza muestran **Ver** y son de solo lectura desde esta pantalla. La API
  `updateItem` también rechaza cambios a ítems producidos.
- El modal conserva ID, fecha y origen como datos de referencia; permite editar
  temática, red social, tipo de contenido, línea comercial y objetivo antes de
  producir. El estado se muestra como lectura.
- En `PieceProducer.vue`, el modal de detalle permite cambiar `estado_pieza`
  desde el selector y guardar junto al contenido en `PUT /marketing/mkt03/pieces/{id}`;
  conservar la validación contra `Piece::ESTADOS` y los metadatos de publicación/
  aprobación al cambiar a `publicada` o `lista_publicar`.
- La sección "Administrar grillas" está comentada temporalmente en el template;
  mantener el código, no borrarlo, salvo pedido explícito.
- Al recargar o guardar un ítem, preservar las filas existentes cuando la
  respuesta de consulta venga vacía; al guardar, actualizar la fila en sitio
  (`this.$set`) en vez de reemplazar/cargar toda la lista.
- Al cambiar la clase de generación/producción, ejecutar `php -l` también en
  sus servicios relacionados. Un error de parseo al cargar una clase puede
  manifestarse al iniciar otro flujo que resuelva la cadena de dependencias.

---

## 10. Checklist antes de terminar

- [ ] `php -l` en cada PHP tocado (con PHP **7.4**).
- [ ] `npm run development` compila sin errores.
- [ ] Sin registros duplicados en BD (limpiar datos de prueba).
- [ ] `CAMBIOS_2026-10-14.txt` con la sección fechada (causa raíz + verificaciones).
- [ ] `ESTADO_ACTUAL.md` actualizado si cambió el estado del trabajo.

---

## 11. Contexto de una sesión nueva

Si la conversación se recorta por límite de tokens, NO re-preguntes: lee
`ESTADO_ACTUAL.md` (estado del trabajo) y `CAMBIOS_2026-10-14.txt` (bitácora).
Ahí está todo lo necesario para continuar sin contexto previo.
