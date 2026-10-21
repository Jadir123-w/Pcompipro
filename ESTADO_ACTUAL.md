# ESTADO ACTUAL — Facturador PRO7 (erp_compipro)

> Documento de traspaso. Si la conversación se recorta por límite de tokens,
> lee este archivo + `CAMBIOS_2026-10-14.txt` (bitácora) + `AGENTS.md`
> (reglas y mapa de edición). No hace falta re-preguntar nada.

**Última actualización:** 2026-10-21
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
- **Kits** en pre-cotización: el colapso "Agrupación de kits" y el check de la
  columna "Kit". Los kits **ya creados** se siguen viendo (con su botón "Disolver").

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
