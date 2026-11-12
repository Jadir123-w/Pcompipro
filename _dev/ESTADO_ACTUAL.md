# ESTADO ACTUAL — Facturador PRO7 (erp_compipro)

> Documento de traspaso. Si la conversación se recorta por límite de tokens,
> lee este archivo + `CAMBIOS_2026-10-14.txt` (bitácora) + `AGENTS.md`
> (reglas y mapa de edición). No hace falta re-preguntar nada.

**Última actualización:** 2026-11-09
**Rama de trabajo:** `dev-jadir` (working copy local = `E:\laragon\www\erp_compipro`)

---

## 1. En qué estamos

**Módulo: Comercial → Requerimientos → pestaña "Pre-Cotización"** (`TabPreCotizacion.vue`).

Se implementó una **comparativa de proveedores por ítem** con la tabla nueva
`quotation_items_supplier` (temporal; reemplaza a `quotation_prequote_item_suppliers`,
que queda intacta pero sin usar). Flujo: el usuario edita la comparativa en pantalla
y los cambios se guardan al presionar **"Guardar detalles"** del ítem (buffer local).

**Todo el trabajo de esta sesión está verificado con smoke tests** (6 endpoints +
motor de cálculo). Pendiente: validación visual en navegador por parte del jefe y
`/repo` para el push.

**Corrección 2026-10-22:** el bloqueo de Validar/Aprobar en pre-cotización ahora
consulta el proveedor de la fila elegida en `quotation_items_supplier`, no el
`supplier_id` heredado del ítem. El conteo del proyecto ya usaba esa misma fuente.
El aviso indica la comparativa por producto, no la pestaña oculta "Proveedores".

**Corrección adicional 2026-10-22:** `Proyecto::generarCodigo()` calcula el siguiente
correlativo máximo entre todos los códigos `PRY-*` y considera también los proyectos
eliminados lógicamente, cuyos códigos siguen ocupados por el índice único.

**Ajuste 2026-10-22:** se diferenciaron los detalles expandidos de Producto,
Comisión y Servicio. Comisión permite editar Costo Base en la fila y lo persiste
en el ítem; su acordeón muestra solo Descripción. Servicio muestra Descripción,
Observaciones y Comparativa, sin paneles de costeo ni detalles técnicos. Producto
mantiene el renderizado existente.

**Ajuste 2026-10-22:** el esquema de kits se administra como SQL manual en
`sql/quotation_proposal_kit_tables.sql`; se retiró su archivo PHP de
`database/migrations/tenant/`. Ejecutar el SQL manualmente en cada tenant cuando
corresponda.

**Ajuste 2026-10-22:** se agregó `sql/quotation_prequote_items_tipo_linea.sql`
para instalar manualmente la columna `tipo_linea` en cada tenant. Las filas
existentes reciben el valor por defecto `product`.

**MKT-03 (2026-10-29):** se añadió Nano Banana como proveedor de imagen con el
modelo `gemini-3.1-flash-image`, incluida generación y edición con referencias.
El catálogo y las opciones de configuración se instalan ejecutando
`sql/marketing_ai_provider_nano_banana.sql` manualmente en cada tenant; ese SQL
queda pendiente de ejecución.

**MKT-03 grillas (2026-11-09):** `GridEditor.vue` ahora presenta una tabla
paginada de ítems desde `GET /marketing/mkt03/grid-items`, con filtros por red,
año y rango de fechas. Los ítems sin pieza permiten producir o editar; al
producirlos, pasan a modo consulta con la acción **Ver**. El modal muestra ID,
fecha y origen como referencias y permite editar temática, red social, tipo,
línea y objetivo únicamente antes de producir. El estado de la pieza se muestra
en la tabla/modal como solo lectura.

La sección **Administrar grillas** permanece comentada en el componente (no
borrada) y no se consulta al cargar esta vista. La recarga preserva la tabla si
el servidor devuelve una página vacía inesperadamente; guardar ediciones cambia
la fila localmente, sin volver a cargar la lista completa.

Se corrigió además una llave sobrante en
`modules/Marketing/Services/GridGeneratorEngine.php` que cerraba la clase antes
de `insertExtraordinarioDespuesDeGrilla()`. El parse error se manifestaba al
intentar producir una pieza porque Laravel cargaba esa clase al resolver el
orquestador.

En `PieceProducer.vue`, el modal de detalle permite ahora cambiar el estado de
la pieza junto con sus campos de contenido. `PUT /marketing/mkt03/pieces/{id}`
valida el estado contra `Piece::ESTADOS` y mantiene `published_at` al pasar a
`publicada` y `approver_id` al pasar a `aprobado`.

---

## 2. Modelo de la comparativa (lo más importante que hay que recordar)

```
┌──────────┬────────────┬─────────┬──────────────────┬────────────┬───────────────┬──────────────┬────────┐
│ Ganador  │ Proveedor  │ Origen  │ Producto/ref.    │ Costo Base │ T. estimado   │ Observaciones│ Acción │
├──────────┼────────────┼─────────┼──────────────────┼────────────┼───────────────┼──────────────┼────────┤
│ ○ radio  │ select     │ Manual  │ solo lectura     │ number     │ input         │ input        │ 🗑     │
│ (elige)  │ (filtro    │         │ (nombre del ítem)│ (editable) │ (editable)    │ (editable)   │        │
│          │  local)    │         │                  │            │               │              │        │
└──────────┴────────────┴─────────┴──────────────────┴────────────┴───────────────┴──────────────┴────────┘
```

- **Una sola fuente de verdad: `quotation_items_supplier`.**
  `quotation_prequote_items` **NO** guarda `supplier_id`, `supplier_name` ni
  `costo_base` (ya no son editables desde el ítem). El motor de cálculo los
  resuelve con `QuotationCalculationEngine::resolveCostoBase()`.
- **Exactamente una fila con `seleccionado = 1`** por ítem (la elegida). El radio
  de la columna "Ganador" la define; las demás quedan como **"Respaldo"**.
- La fila **elegida** refleja el **nombre actual** del producto en su
  `product_reference` (se sincroniza al cargar, al renombrar y al guardar).
- **En la fila del ítem** (tabla de items), el **Costo Base y el V. Unit. son
  solo lectura** y muestran el del proveedor seleccionado.
- **Toda la fila de la tabla de items es EN VIVO** (2026-10-21 (7)): "Costo Base",
  "V. Unit.", "Margen (S/)" y "Total" se calculan con el proveedor seleccionado
  aunque no se haya guardado. "Total" = con IGV (igual que `total_item` del motor).
  Los totales del **encabezado** (Subtotal / IGV / Total) siguen siendo los
  persistidos: se actualizan al guardar.
- `user_id` de las filas = **usuario de apoyo** del requerimiento
  (`requerimiento.usuario_apoyo_cotizacion`), no el usuario de sesión.

### Estructura de la tabla (2026-10-21)

`es_ganador` → **`seleccionado`** (0/1) · **NO existe `orden`** ·
`tiempo_entrega` → **`tiempo_estimado_dias`**.

---

## 3. Bugs ya corregidos (no reintroducirlos)

1. **Campos no editables** (solo "Costo Base" respondía): `:value` + `@change` en
   vez de `v-model`; y `saveSupplierQuote` **reemplazaba** el objeto de la fila.
   → Todo con `v-model` + mutación **in-place** (`this.$set`).
2. **Nombre de proveedor viejo** en el dropdown → `v-model="qscope.row.supplier_id"`.
3. **Columna "Ganador" vacía**: el `<input type="radio">` nativo lo oculta el CSS
   global del tema (`left:-9999px; opacity:0`) → ahora usa `<el-radio>` (+ `@input`).
4. **Referencia con nombre viejo** → la fila elegida se sincroniza con el nombre actual.
5. **Ítems sin proveedor** (todas las filas con `es_ganador = 0`) → invariante
   "siempre hay exactamente una elegida".
6. **`flushSupplierQuotes()` tomaba el último id** de la respuesta como si fuera
   el recién creado → ahora busca el id que no estaba antes.
7. **`item._originalQuotes` se perdía** al re-renderizar → `supplierQuoteOriginals[itemId]`.
8. **Combo listaba solo algunos proveedores**: estaba en modo `remote` (Element no
   filtra en local y el backend exige 2+ caracteres) → ahora precarga todos y filtra local.
9. **`user_id` NULL** en la fila de proveedor automática → ahora es el usuario de apoyo.
10. **Kit duplicado** por cadena `v-if`/`v-else-if` rota (se renderizaban input +
     span) → cadena única.
11. **"Total" de la fila desactualizado**: pintaba el `total_item` PERSISTIDO, así
    que al cambiar el proveedor ganador sin guardar seguía mostrando el total
    del ganador anterior → ahora es EN VIVO (`costeoBreakdown().totalConIgv`).
12. **"Margen (S/)" con el costo equivocado**: `ganancia_absoluta` se calculaba
    con la columna vieja `quotation_prequote_items.costo_base` (residuo) →
    ahora usa el costo del proveedor SELECCIONADO (`PreQuoteItem::getGananciaAbsolutaAttribute`).
13. **"V. Unit." de la comparativa no comparable**: mostraba `costo × (1+margin%)`
    (sin costos fijos ni SCTR) mientras la tarjeta mostraba el unitario final →
    ahora cada proveedor muestra el unitario final si fuera el ganador
    (`costeoBreakdown(item, costoDeEseProveedor).unit`), igual que el mockup V24.
14. **"V. unit." con dos significados**: la fila del ítem mostraba `costo×(1+margin%)`
    (9.10) y la tarjeta el unitario final (9.25). Ahora es **un solo concepto**
    (Total sin IGV ÷ Q) en la fila del ítem, en la del proveedor ganador y en la
    tarjeta → la fila cierra `V. unit. × Q = Total sin IGV` y `Total = × (1+IGV)`.

---

## 4. Gotchas de Vue 2 + Element UI (mismo problema, otras pantallas)

- `el-input` / `el-select` **nunca** con `:value` + `@change` para editar → `v-model`.
  Con `remote`, Element **no** filtra en local y el `filter-method` **no filtra solo**
  (su retorno se ignora): hay que mutar la lista propia.
- `el-radio` fuera de un grupo: usar `:value`/`:label` y **`@input`**.
- En tablas, mutar la fila en el sitio (`this.$set(row, k, v)`); si se reemplaza
  el objeto, el `v-model` sigue apuntando al viejo y se pierde la edición.
- `v-if`/`v-else-if`: una sola cadena (un `v-if` nuevo deja huérfano al siguiente).
- Radio nativo invisible por CSS global → `el-radio`.

---

## 5. Lo que está oculto (código intacto, se reactiva con `v-if="false"`)

- **Pestaña "Proveedores"** (IA de proveedores) en `index.vue` y `kanban.vue`.
- **Kits** en pre-cotización: SOLO queda oculto el colapso "Agrupación de
  kits" (`el-col` con `v-if="false"`). El check de la columna "Kit" fue
  rehabilitado el 2026-11-10 (condición original del commit 11854a5:
  `!scope.row._isKit && !isComision(scope.row)`); los flags `mostrarKits` y
  `mostrarItemManual` están en `true`. Los kits **ya creados** se siguen viendo
  (con su botón "Disolver"). OJO: sin el colapso, los checks marcados no
  tienen barra de creación de kit todavía.

---

## 6. Archivos involucrados

> **Mapa con líneas y funciones exactas:** `AGENTS.md` §7 "Mapa de puntos de
> edición". Ahí está anclado cada método (p.ej. `flushSupplierQuotes` ~3654,
> `markItemQuoteRow` ~3496, `saveSupplierQuote` ~3561, `updateItem` ~244,
> `supplierQuotes` ~2050, `resolveCostoBase` en el motor).

| Archivo | Qué contiene |
|---|---|
| `modules/Comercial/Resources/assets/js/view/requerimientos/TabPreCotizacion.vue` | Toda la UI de pre-cotización (2 copias de la comparativa: ítem normal y componente de kit) |
| `modules/Marketing/Http/Controllers/Api/QuotationPreQuoteController.php` | 6 endpoints de comparativa + altas de ítem (IA y manual) + `updateItem` |
| `modules/Marketing/Services/QuotationCalculationEngine.php` | `resolveCostoBase()`: el costo sale de la comparativa |
| `modules/Marketing/Models/QuotationItemSupplier.php` | Modelo de la tabla nueva (fillable/casts + `scopeSeleccionado()`) |
| `modules/Marketing/Models/PreQuoteItem.php` | Relaciones `itemSuppliers()` / `winningItemSupplier()` |
| `sql/quotation_items_supplier.sql` | CREATE TABLE (SQL manual, ya ejecutado en `tenancy_prueba`) |
| `modules/Comercial/.../index.vue`, `kanban.vue` | Pestaña "Proveedores" oculta con `v-if="false"` |

---

## 7. Cómo verificar rápido

```powershell
# Lint PHP
& "E:\laragon\bin\php\php-7.4.33-nts-Win32-vc15-x86\php.exe" -l <archivo.php>

# Compilar front
$env:PATH = "E:\note;" + $env:PATH; & "E:\note\npm.cmd" run development

# Ver la tabla nueva (puerto 3308)
& "E:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe" -u root tenancy_prueba -e "SELECT * FROM quotation_items_supplier;"
```

URL de prueba: `http://erp_compipro.test:8084` → Comercial → Requerimientos → Pre-Cotización.

**Ojo**: para los smoke tests en PHP hay que usar el **PHP 7.4** (el 8.3 rompe el
bootstrap de Laravel 8) y forzar la conexión tenant a `tenancy_prueba`.

---

## 8. Pendiente / próximos pasos

1. **Validación visual en navegador** por parte del jefe (radio visible, edición de
   los 3 campos, costo base solo lectura en items, columna "Respaldo", kits ocultos).
2. **`/repo`** → push a `dev-jadir` + sección fechada en `CAMBIOS_2026-10-14.txt`.
   Los cambios del 2026-10-21 **NO** se han commiteado todavía.
   - Ojo: el working copy local tiene **muchos archivos modificados sin commitear**
     (módulos `Account`, `ProposalKit*` como *untracked*, la comparativa, etc.) y el
     clone de push está en `11854a5`. El `/repo` copia el working copy al clone y
     commitea; **revisar `git status` antes de pushear**.
3. Para **otros tenants** hay que correr `sql/quotation_items_supplier.sql` a mano
   (es idempotente: `CREATE TABLE IF NOT EXISTS`).
4. La columna `costo_base` de `quotation_prequote_items` **sigue existiendo** (no se
   borró) y queda como valor muerto/respaldo para ítems antiguos sin comparativa.
   Si se quiere limpiar o migrar, se decide aparte.
5. Prueba pendiente del flujo de **conversión a proyecto** con la comparativa (el
   proveedor elegido debe seguir alimentando `proyecto_productos`).
6. **El motor no tiene caso especial para `service`** (solo para `comision`): un
   servicio se guarda CON SCTR e IGV, aunque el changelog del 2026-10-16 dice que
   debe guardarse "sin IGV, sin SCTR". El front ya previsualiza lo que el motor
   guarda (2026-10-21 (7)); decidir si se agrega el caso `service` al motor
   (cambia los totales ya guardados de los servicios existentes).

## 9. Dashboard MKT-03 y proveedor de texto (2026-10-29)

- Se difirio la resolucion de `MKT03Orchestrator` en los controladores de grillas,
  piezas, ferias y contenido extraordinario; tambien se difirio `GridPatternAnalyzer`
  en el controlador de configuracion/historial. Asi, las consultas del dashboard
  no construyen Claude ni fallan si `CHATBOT_CLAUDE_API_KEY` no esta configurada.
- La generacion de grillas/contenido sigue necesitando un proveedor de texto
  configurado; Nano Banana es el proveedor de imagen, no reemplaza a Claude para
  texto.
- Verificado con PHP 7.4: lint en los cinco controladores y resolucion de los
  controladores tras el bootstrap de Laravel sin clave de Claude.
- La traza de las 12:47:59 correspondia al endpoint de importacion de Excel
  historico (`historical/import`), no a la consulta del dashboard. Se agrego
  validacion de la subida y se guarda el stream desde el pathname temporal
  (sin depender de `getRealPath()`); el formulario deja que Axios configure multipart.
- El boton `Analizar` de historicos debe usar el proveedor de texto seleccionado
  en la configuracion de MKT-03 (Claude, OpenAI o Gemini), no el proveedor global
  que por defecto resolvia a Claude. El binding de `GridPatternAnalyzer` se
  actualizo a `AgentConfigService::resolveTextProvider('mkt03')`. Nano Banana
  sigue siendo solo para imagenes.
- Ultimo error de analisis (13:05:57): Gemini respondio HTTP 503. Se agrego retry
  acotado (2 intentos adicionales para 502/503/504) y se evito incluir URL/clave
  API en los mensajes de excepcion. La clave API que se haya compartido en logs
  debe revocarse y reemplazarse.
- Si el analizador retorna fallback, el endpoint ya no marca el historico como
  exitoso: conserva estado error y devuelve el mensaje con HTTP 502.
- `GridGeneratorEngine` tenia la misma dependencia global; su binding ahora
  tambien resuelve el proveedor de texto de MKT-03, para que "Generar grilla"
  respete la configuracion del agente.
- El log de las 13:21 confirma que Gemini respondio, pero el reintento omitio
  `max_tokens` y se corto con `MAX_TOKENS`; el orquestador guardo una grilla vacia.
  Se corrigio el limite/prompt del reintento y ahora los resultados fallback
  revierten la transaccion en vez de dejar grillas vacias exitosas. La transaccion
  ahora usa la conexion tenant del modelo `Grid` (antes se usaba la conexion default
  de sistema, por lo que el rollback no deshacia el registro).
