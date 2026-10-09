<template>
  <div v-loading="loading" class="tab-preview-editable">

    <!-- ═══════════════════════════════════════════════════════════════
         ESTADO 1: No hay precotización
         ═══════════════════════════════════════════════════════════════ -->
    <div v-if="!prequote && !loading" style="text-align:center; padding:30px 0;">
      <i class="el-icon-document" style="font-size:48px; color:#c0c4cc;"></i>
      <p style="color:#888; margin:15px 0;">
        Este requerimiento aún no tiene <strong>Pre-Cotización</strong>.
      </p>
      <p style="color:#aaa; font-size:12px;">
        Ve a la pestaña "Pre-Cotización" y genera la precotización con IA.
      </p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         ESTADO 2: Hay precotización — preview editable
         ═══════════════════════════════════════════════════════════════ -->
    <div v-if="prequote">

      <!-- Encabezado -->
      <div class="pe-toolbar">
        <div>
          <strong>Vista previa de la propuesta</strong>
          <el-tag :type="estadoTagType(prequote.estado)" size="small" style="margin-left:8px;">
            {{ prequote.estado }}
          </el-tag>
          <span class="pe-hint">
            Edita aquí los detalles del bloque. La cantidad y los costos se editan en la pestaña Pre-Cotización.
          </span>
        </div>
        <div v-if="dirtyItemIds.length > 0">
          <span class="pe-dirty-count">
            {{ dirtyItemIds.length }} ítem(s) con cambios sin guardar
          </span>
        </div>
      </div>

      <!-- Banner cuando no es editable -->
      <el-alert v-if="!isEditable" type="warning" :closable="false" show-icon style="margin-bottom:8px;">
        Esta pre-cotización está en estado "{{ prequote.estado }}" y no se puede editar.
        Los detalles solo son editables mientras está en borrador, en revisión o ajustada.
      </el-alert>

      <!-- Tabla fiel al PDF -->
      <div class="pe-table-wrap">
        <table class="preview-table">
          <thead>
            <tr>
              <th style="width:22px;">Nº</th>
              <th style="width:92px;" title="Código del artículo. Se puede escribir uno propio para esta cotización (máx. 12, en mayúsculas); si se deja vacío se usa el del catálogo de Compipro. Se guarda al salir del campo.">Cód. Artíc.</th>
              <th style="width:110px;">Artículo</th>
              <th style="width:60px;">Foto</th>
              <th style="width:36px;">Cant.</th>
              <th style="width:60px;" title="Costo unitario de venta del ítem, sin IGV.">Costo uni.<br>(S/)</th>
              <!-- Agosto 2026 (punto 9): este es el plazo que se le OFRECE AL CLIENTE
                   y el que sale en el PDF. No es el mismo que el Tiempo de Producción
                   de la pestaña Proveedores, que es lo que tarda el proveedor. -->
              <th style="width:120px;" title="Plazo que se le ofrece al cliente y sale en el PDF (texto libre). Arranca heredando el Tiempo de Producción del proveedor; al escribir uno propio, ya no cambia si se edita el de Proveedores.">Tiempo Entrega</th>
              <th>Detalles</th>
              <th style="width:70px;" title="Costo total del ítem (cantidad × costo unitario).">Costo total<br>(S/)</th>
              <th style="width:70px; color:#67C23A;" title="Margen de ganancia del ítem en soles (uso interno).">Margen<br>(S/)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, idx) in prequote.items" :key="item.id"
                :class="{ 'preview-row--dirty': isDirty(item) }">
              <td class="numero">{{ item.item_order > 0 ? item.item_order : (idx + 1) }}</td>
              <!-- COM-02 · Cód. Artíc. editable por línea de cotización.
                   Dos particularidades, ambas a propósito:
                   1) Es el ÚNICO campo del Preview que sigue habilitado cuando la
                      pre-cotización ya está aprobada (el código lleva las iniciales
                      del proveedor y el proveedor puede cambiar en cualquier momento).
                   2) NO viaja en saveAll(): se guarda por su propio endpoint. saveAll
                      está bloqueado en aprobadas y además descartaría este campo en
                      silencio devolviendo 200. -->
              <td class="codigo">
                <el-input :value="item.codigo_articulo_manual" size="mini" maxlength="12"
                          class="pe-codigo-input"
                          :placeholder="item.codigo_articulo_catalogo || '—'"
                          :disabled="savingCodigoId === item.id"
                          @input="onCodigoInput(item, $event)"
                          @change="saveCodigo(item)"></el-input>
              </td>
              <td class="articulo">
                <el-input v-model="item.product_name" size="mini" placeholder="Producto"
                          :disabled="!isEditable" @input="markDirty(item)"></el-input>
              </td>
              <td class="foto">
                <img v-if="itemImageUrl(item)" :src="itemImageUrl(item)" alt=""
                     class="pe-foto-thumb" />
                <span v-else class="pe-foto-empty">imagen referencial</span>

                <div v-if="isEditable" class="pe-foto-actions">
                  <el-button size="mini" type="text" icon="el-icon-upload2"
                             :loading="uploadingItemImageId === item.id"
                             @click="triggerItemImageUpload(item)">
                    {{ itemImageUrl(item) ? 'Cambiar' : 'Subir' }}
                  </el-button>
                  <el-button v-if="itemImageUrl(item)" size="mini" type="text" class="pe-foto-del"
                             icon="el-icon-delete"
                             @click="deleteItemImage(item)">
                    Quitar
                  </el-button>
                </div>
              </td>
              <td class="cantidad">{{ item.quantity }}</td>
              <td class="precio">{{ safeFixed(item.valor_unitario) }}</td>
              <td class="tiempo">
                <!-- Agosto 2026 (punto 9): pasa a escribir en tiempo_entrega, su
                     propio campo. Antes escribía en tiempo_estimado_dias, el mismo
                     que la pestaña Proveedores, y por eso al tocar uno se movía el
                     otro y era imposible tener 19 días de producción y 20 a 22 de
                     entrega a la vez. -->
                <el-input v-model="item.tiempo_entrega" type="textarea"
                          :autosize="{ minRows: 1, maxRows: 4 }" size="mini"
                          :disabled="!isEditable" @input="markDirty(item)"
                          placeholder="Ej. 20 a 22 días útiles"
                          title="Plazo ofrecido al cliente (texto libre)"></el-input>
              </td>
              <td class="detalles">
                <div class="detalle-editor">
                  <!-- Material -->
                  <div class="detalle-row">
                    <label>Material:</label>
                    <el-input v-model="item.material" size="mini"
                              :disabled="!isEditable" @input="markDirty(item)"></el-input>
                  </div>
                  <!-- Medidas -->
                  <div class="detalle-row">
                    <label>Medidas:</label>
                    <el-input v-model="item.medida" size="mini"
                              :disabled="!isEditable" @input="markDirty(item)"></el-input>
                  </div>
                  <!-- Técnica -->
                  <div class="detalle-row">
                    <label>Técnica:</label>
                    <el-input v-model="item.tecnica_impresion" size="mini"
                              :disabled="!isEditable" @input="markDirty(item)"></el-input>
                  </div>
                  <!-- Colores y Lados en línea -->
                  <div class="detalle-row detalle-row--inline">
                    <label>Colores:</label>
                    <el-input-number v-model="item.cantidad_colores" :min="1" :max="20" size="mini"
                                     controls-position="right" :disabled="!isEditable"
                                     @change="markDirty(item)"></el-input-number>
                    <label style="margin-left:8px;">Lados:</label>
                    <el-input-number v-model="item.cantidad_lados" :min="1" :max="4" size="mini"
                                     controls-position="right" :disabled="!isEditable"
                                     @change="markDirty(item)"></el-input-number>
                  </div>
                  <!-- Presentación -->
                  <div class="detalle-row">
                    <label>Presentación:</label>
                    <el-input v-model="item.empaque" size="mini"
                              :disabled="!isEditable" @input="markDirty(item)"></el-input>
                  </div>
                  <!-- Agosto 2026 (punto 11) — Opciones rápidas de formato sobre
                       los campos de texto libre de DETALLES. El cliente pidió
                       "opciones rápidas para aplicar negrita, cursiva y cambiar el
                       color del texto, pudiendo combinarse", no un editor completo.
                       Los botones envuelven lo que esté seleccionado con marcadores
                       que el PDF convierte a formato real. -->
                  <!-- La barra va ENCIMA del campo, no a su izquierda: es lo que
                       pidió el cliente y además le devuelve al textarea el ancho
                       que los cinco botones le quitaban. Para eso los dos van
                       juntos en una columna (.campo-cuerpo), porque .detalle-row
                       es un flex horizontal y sueltos ahí adentro quedarían en
                       fila con la etiqueta. -->
                  <!-- Personalización -->
                  <div class="detalle-row campo-con-formato">
                    <label>Personalización:</label>
                    <div class="campo-cuerpo">
                      <div v-if="isEditable" class="fmt-barra">
                        <button type="button" class="fmt-btn fmt-b" title="Negrita"
                                @click="aplicarFormato($event, item, 'personalizacion', 'negrita')">B</button>
                        <button type="button" class="fmt-btn fmt-i" title="Cursiva"
                                @click="aplicarFormato($event, item, 'personalizacion', 'cursiva')">I</button>
                        <button type="button" class="fmt-btn fmt-rojo" title="Color rojo"
                                @click="aplicarFormato($event, item, 'personalizacion', 'rojo')">A</button>
                        <button type="button" class="fmt-btn fmt-azul" title="Color azul"
                                @click="aplicarFormato($event, item, 'personalizacion', 'azul')">A</button>
                        <button type="button" class="fmt-btn fmt-verde" title="Color verde"
                                @click="aplicarFormato($event, item, 'personalizacion', 'verde')">A</button>
                      </div>
                      <el-input v-model="item.personalizacion" type="textarea"
                                :autosize="{minRows: 2, maxRows: 6}" size="mini"
                                :disabled="!isEditable" @input="markDirty(item)"></el-input>
                    </div>
                  </div>
                  <!-- Observaciones -->
                  <div class="detalle-row campo-con-formato">
                    <label>Observaciones:</label>
                    <div class="campo-cuerpo">
                      <div v-if="isEditable" class="fmt-barra">
                        <button type="button" class="fmt-btn fmt-b" title="Negrita"
                                @click="aplicarFormato($event, item, 'observaciones', 'negrita')">B</button>
                        <button type="button" class="fmt-btn fmt-i" title="Cursiva"
                                @click="aplicarFormato($event, item, 'observaciones', 'cursiva')">I</button>
                        <button type="button" class="fmt-btn fmt-rojo" title="Color rojo"
                                @click="aplicarFormato($event, item, 'observaciones', 'rojo')">A</button>
                        <button type="button" class="fmt-btn fmt-azul" title="Color azul"
                                @click="aplicarFormato($event, item, 'observaciones', 'azul')">A</button>
                        <button type="button" class="fmt-btn fmt-verde" title="Color verde"
                                @click="aplicarFormato($event, item, 'observaciones', 'verde')">A</button>
                      </div>
                      <el-input v-model="item.observaciones" type="textarea"
                                :autosize="{minRows: 2, maxRows: 6}" size="mini"
                                :disabled="!isEditable" @input="markDirty(item)"></el-input>
                    </div>
                  </div>
                  <!-- Descripción extendida -->
                  <div class="detalle-row campo-con-formato">
                    <label>Descripción:</label>
                    <div class="campo-cuerpo">
                      <div v-if="isEditable" class="fmt-barra">
                        <button type="button" class="fmt-btn fmt-b" title="Negrita"
                                @click="aplicarFormato($event, item, 'product_description', 'negrita')">B</button>
                        <button type="button" class="fmt-btn fmt-i" title="Cursiva"
                                @click="aplicarFormato($event, item, 'product_description', 'cursiva')">I</button>
                        <button type="button" class="fmt-btn fmt-rojo" title="Color rojo"
                                @click="aplicarFormato($event, item, 'product_description', 'rojo')">A</button>
                        <button type="button" class="fmt-btn fmt-azul" title="Color azul"
                                @click="aplicarFormato($event, item, 'product_description', 'azul')">A</button>
                        <button type="button" class="fmt-btn fmt-verde" title="Color verde"
                                @click="aplicarFormato($event, item, 'product_description', 'verde')">A</button>
                      </div>
                      <el-input v-model="item.product_description" type="textarea"
                                :autosize="{minRows: 2, maxRows: 6}" size="mini"
                                :disabled="!isEditable" @input="markDirty(item)"></el-input>
                    </div>
                  </div>

                  <div class="fmt-ayuda">
                    Formato: <code>**negrita**</code> · <code>_cursiva_</code> ·
                    <code>[rojo]texto[/rojo]</code>. Se combinan entre sí y se ven en el PDF.
                    Selecciona un texto y presiona el botón para aplicarlo; presiónalo de
                    nuevo para quitarlo.
                  </div>
                </div>
              </td>
              <td class="total">{{ safeFixed(item.valor_total) }}</td>
              <td class="total" style="color:#67C23A; font-weight:600;">{{ safeFixed(item.ganancia_absoluta) }}</td>
            </tr>
            <tr v-if="!prequote.items || prequote.items.length === 0">
              <td colspan="10" style="text-align:center; padding:10px; color:#999;">
                (La pre-cotización no tiene ítems)
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Input file compartido para la imagen POR ÍTEM -->
      <input ref="itemImageInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif"
             style="display:none;" @change="onItemImageSelected"/>

      <!-- Totales (replica del PDF) -->
      <div class="pe-totales">
        <div class="pe-totales-row">
          <span>Subtotal:</span>
          <strong>S/ {{ safeFixed(prequote.subtotal) }}</strong>
        </div>
        <div class="pe-totales-row">
          <span>IGV (18%):</span>
          <strong>S/ {{ safeFixed(prequote.total_igv) }}</strong>
        </div>
        <div class="pe-totales-row" style="color:#67C23A;">
          <span>Margen Ganancia:</span>
          <strong>S/ {{ safeFixed(prequote.ganancia_total) }}</strong>
        </div>
        <div class="pe-totales-row pe-totales-grand">
          <span>TOTAL:</span>
          <strong>S/ {{ safeFixed(prequote.total) }}</strong>
        </div>
      </div>

      <!-- Acciones -->
      <div class="pe-actions">
        <el-button v-if="isEditable" type="primary" size="small" icon="el-icon-check"
                   :loading="savingItems" :disabled="dirtyItemIds.length === 0"
                   @click="saveAll">
          Guardar cambios
        </el-button>
      </div>

    </div>
  </div>
</template>

<script>
/**
 * Agosto 2026 (punto 11) · Marcadores de cada formato.
 *
 * Son los mismos que entiende el generador del PDF
 * (Modules\Marketing\Support\TextoEnriquecido): si acá se escribiera otra cosa,
 * el botón dejaría de producir formato en el documento.
 */
const MARCADORES = {
  negrita: { abre: '**',      cierra: '**' },
  cursiva: { abre: '_',       cierra: '_' },
  rojo:    { abre: '[rojo]',  cierra: '[/rojo]' },
  azul:    { abre: '[azul]',  cierra: '[/azul]' },
  verde:   { abre: '[verde]', cierra: '[/verde]' },
}

/** Los colores se excluyen entre sí: aplicar uno reemplaza al anterior. */
const COLORES = ['rojo', 'azul', 'verde']

export default {
  name: 'TabPreviewEditable',
  props: {
    requerimientoId: { type: [Number, String], default: null },
    // Modo standalone: si viene prequoteId, carga directamente la prequote.
    prequoteId: { type: [Number, String], default: null }
  },
  data() {
    return {
      prequote: null,
      loading: false,
      savingItems: false,
      dirtyItemIds: [],
      // Imagen POR ÍTEM (mismo patrón que TabPreCotizacion)
      uploadingItemImageId: null, // id del ítem cuya imagen se está subiendo
      itemImageVersion: 0,        // bust cache de las miniaturas por ítem
      // COM-02 · id del ítem cuyo "Cód. Artíc." se está guardando (deshabilita ese input)
      savingCodigoId: null,
      // Agosto 2026 (punto 11) · Último formato aplicado con la barra: qué ítem,
      // qué campo, qué formato y sobre qué tramo. Es lo que le permite al botón
      // saber que un segundo clic tiene que deshacer lo del primero.
      ultimoFormato: null
    }
  },
  computed: {
    isEditable() {
      if (!this.prequote) return false
      return ['borrador', 'en_revision', 'ajustada'].indexOf(this.prequote.estado) !== -1
    }
  },
  watch: {
    requerimientoId: {
      immediate: true,
      handler(newId) {
        if (newId && !this.prequoteId) this.loadPrequote()
      }
    },
    prequoteId: {
      immediate: true,
      handler(newId) {
        if (newId) this.loadPrequoteById(newId)
      }
    }
  },
  methods: {
    // ══════════════════════════════════════════════════════════════
    // Carga del prequote (mismo patrón que TabPreCotizacion)
    // ══════════════════════════════════════════════════════════════
    async loadPrequote() {
      this.loading = true
      this.dirtyItemIds = []
      try {
        var response = await this.$http.get('/marketing/quotation/prequotes', {
          params: { requerimiento_id: this.requerimientoId }
        })
        var list = response.data.prequotes.data || response.data.prequotes || []
        if (list.length > 0) {
          var detailResponse = await this.$http.get('/marketing/quotation/prequotes/' + list[0].id)
          this.prequote = this.parseItems(detailResponse.data.prequote)
        } else {
          this.prequote = null
        }
      } catch (e) {
        this.prequote = null
      }
      this.loading = false
    },

    async loadPrequoteById(id) {
      this.loading = true
      this.dirtyItemIds = []
      try {
        var detailResponse = await this.$http.get('/marketing/quotation/prequotes/' + id)
        this.prequote = this.parseItems(detailResponse.data.prequote)
      } catch (e) {
        this.prequote = null
      }
      this.loading = false
    },

    // Normalizar tipos: números para cálculo/inputs, strings para textareas
    /**
     * Agosto 2026 (punto 11) — Aplica o quita un formato sobre el texto
     * seleccionado. El botón funciona como interruptor: si el texto ya tiene ese
     * formato, se lo saca.
     *
     * Los marcadores son los mismos que entiende TextoEnriquecido en el backend:
     * **negrita**, _cursiva_ y [color]texto[/color]. Se guardan como texto plano
     * y el PDF los convierte a formato real.
     *
     * El textarea se busca subiendo hasta el contenedor del campo en vez de usar
     * refs: dentro de un v-for los refs quedan como arreglos y hay que indexarlos
     * a mano, lo que se rompe apenas cambia el orden de los ítems (y con el botón
     * de duplicar, el orden cambia).
     *
     * Si no hay nada seleccionado se inserta la palabra "texto" ya envuelta y se
     * deja seleccionada, para que la ejecutiva escriba encima. Es lo que hacen
     * los editores conocidos y evita que el botón parezca que no hizo nada.
     */
    aplicarFormato(ev, item, campo, tipo) {
      var m = MARCADORES[tipo]
      if (!m) return

      var cont = ev.target.closest('.campo-con-formato')
      var ta = cont ? cont.querySelector('textarea') : null
      if (!ta) return

      var texto = (item[campo] || '').toString()
      var ini = ta.selectionStart
      var fin = ta.selectionEnd

      // ¿Es el mismo botón, sobre la misma selección, apretado de nuevo? En ese
      // caso se confía en que el formato lo pusimos nosotros recién y se saca sin
      // más análisis. Es lo que resuelve el caso que reportó el cliente: apretar
      // negrita dos veces seguidas ya no acumula asteriscos.
      var u = this.ultimoFormato
      var repiteElMismoBoton = !!u && u.itemId === item.id && u.campo === campo &&
                               u.tipo === tipo && u.desde === ini && u.hasta === fin

      var nuevo, desde, hasta

      var env = this.envolturaDe(texto, ini, fin, m.abre, m.cierra, !repiteElMismoBoton)
      var otro = env ? null : this.colorAplicado(texto, ini, fin, tipo)

      if (env) {
        // Ya tenía este formato → se lo quitamos.
        var adentro = texto.substring(env.a2, env.c1)
        nuevo = texto.substring(0, env.a1) + adentro + texto.substring(env.c2)
        desde = env.a1
        hasta = env.a1 + adentro.length
      } else if (otro) {
        // Estaba pintado de otro color → se cambia, no se anida. Anidando
        // ([rojo][azul]x[/azul][/rojo]) el PDF seguiría mostrando el color de
        // adentro y el botón parecería no haber hecho nada.
        var pintado = texto.substring(otro.a2, otro.c1)
        nuevo = texto.substring(0, otro.a1) + m.abre + pintado + m.cierra + texto.substring(otro.c2)
        desde = otro.a1 + m.abre.length
        hasta = desde + pintado.length
      } else {
        var sel = texto.substring(ini, fin)
        var relleno = sel === '' ? 'texto' : sel
        nuevo = texto.substring(0, ini) + m.abre + relleno + m.cierra + texto.substring(fin)
        desde = ini + m.abre.length
        hasta = desde + relleno.length
      }

      this.$set(item, campo, nuevo)
      this.markDirty(item)

      // Se recuerda qué quedó marcado y dónde, para que el siguiente clic en el
      // mismo botón sepa que tiene que deshacerlo. Si se quitó el formato no se
      // recuerda nada: el próximo clic vuelve a aplicarlo.
      this.ultimoFormato = env
        ? null
        : { itemId: item.id, campo: campo, tipo: tipo, desde: desde, hasta: hasta }

      // Devolver el foco y dejar seleccionado el contenido, para poder encadenar
      // formatos (negrita y después color sobre lo mismo).
      this.$nextTick(function () {
        ta.focus()
        ta.setSelectionRange(desde, hasta)
      })
    },

    /**
     * ¿La selección ya está envuelta por este par de marcadores?
     *
     * Devuelve las cuatro posiciones que hacen falta para sacarlos —dónde empieza
     * y termina cada marcador— o null si no está envuelta.
     *
     * @param {string}  texto
     * @param {number}  ini      inicio de la selección
     * @param {number}  fin      fin de la selección
     * @param {string}  abre
     * @param {string}  cierra
     * @param {boolean} cuidarse  exigir que el marcador no sea parte de una
     *                            tirada más larga del mismo carácter (ver abajo)
     * @return {object|null}
     */
    envolturaDe(texto, ini, fin, abre, cierra, cuidarse) {
      var e = null

      // a) Los marcadores están pegados por FUERA de la selección. Es lo que pasa
      //    al apretar dos veces seguidas: después de envolver queda seleccionado
      //    solo el texto de adentro.
      if (ini >= abre.length &&
          texto.substring(ini - abre.length, ini) === abre &&
          texto.substring(fin, fin + cierra.length) === cierra) {
        e = { a1: ini - abre.length, a2: ini, c1: fin, c2: fin + cierra.length }
      } else {
        // b) Los marcadores están DENTRO de la selección, porque el usuario los
        //    incluyó al seleccionar con el mouse.
        var sel = texto.substring(ini, fin)
        if (sel.length >= abre.length + cierra.length &&
            sel.substring(0, abre.length) === abre &&
            sel.substring(sel.length - cierra.length) === cierra) {
          e = { a1: ini, a2: ini + abre.length, c1: fin - cierra.length, c2: fin }
        }
      }

      if (!e || !cuidarse) return e

      // Los textos que ya usan hoy traen asteriscos de adorno
      // ("*********Arte y diseño*********"). Sin esta guarda, los dos últimos
      // asteriscos del adorno se leerían como una negrita y el primer clic, en
      // lugar de poner negrita, borraría parte del adorno.
      //
      // Solo aplica a los marcadores hechos de un carácter repetido (** y _);
      // [rojo] no puede confundirse con nada.
      if (!this.esCaracterRepetido(abre)) return e
      if (e.a1 > 0 && texto.charAt(e.a1 - 1) === abre.charAt(0)) return null
      if (texto.charAt(e.c2) === cierra.charAt(0)) return null

      return e
    },

    /** ¿El marcador es un mismo carácter repetido? ('**' y '_' sí; '[rojo]' no) */
    esCaracterRepetido(marcador) {
      for (var i = 1; i < marcador.length; i++) {
        if (marcador.charAt(i) !== marcador.charAt(0)) return false
      }
      return true
    },

    /**
     * Si la selección está pintada de un color DISTINTO al que se pidió, devuelve
     * la envoltura de ese color. Sirve para reemplazarlo en vez de anidarlo.
     *
     * @return {object|null}
     */
    colorAplicado(texto, ini, fin, tipo) {
      if (COLORES.indexOf(tipo) === -1) return null
      for (var i = 0; i < COLORES.length; i++) {
        if (COLORES[i] === tipo) continue
        var otro = MARCADORES[COLORES[i]]
        var env = this.envolturaDe(texto, ini, fin, otro.abre, otro.cierra, true)
        if (env) return env
      }
      return null
    },

    parseItems(pq) {
      if (pq && pq.items) {
        for (var i = 0; i < pq.items.length; i++) {
          var it = pq.items[i]
          it.quantity         = parseInt(it.quantity) || 0
          it.cantidad_colores = parseInt(it.cantidad_colores) || 1
          it.cantidad_lados   = parseInt(it.cantidad_lados) || 1
          it.valor_unitario   = parseFloat(it.valor_unitario) || 0
          it.valor_total      = parseFloat(it.valor_total) || 0
          it.ganancia_absoluta = parseFloat(it.ganancia_absoluta) || 0
          it.product_name        = it.product_name || ''
          it.material            = it.material || ''
          it.medida              = it.medida || ''
          it.tecnica_impresion   = it.tecnica_impresion || ''
          it.empaque             = it.empaque || ''
          it.personalizacion     = it.personalizacion || ''
          it.observaciones       = it.observaciones || ''
          it.product_description = it.product_description || ''
          // tiempo_estimado_dias ahora es texto libre (COM08): normalizar a string para el input.
          it.tiempo_estimado_dias = it.tiempo_estimado_dias != null ? String(it.tiempo_estimado_dias) : ''
          // Agosto 2026 (punto 9) — Plazo ofrecido al CLIENTE, distinto del de
          // producción del proveedor. Mientras el ítem no tenga uno propio se
          // muestra el de producción como valor heredado, igual que hace
          // PreQuoteItem::tiempoEntregaEfectivo() en el backend para el PDF.
          //
          // Se hereda solo cuando está vacío. Una vez que la ejecutiva escribe su
          // plazo, ese valor manda y editar el de Proveedores ya no lo pisa — que
          // era exactamente el problema que este punto vino a resolver.
          var entregaPropia = it.tiempo_entrega != null ? String(it.tiempo_entrega) : ''
          it.tiempo_entrega = entregaPropia !== '' ? entregaPropia : it.tiempo_estimado_dias
        }
      }
      return pq
    },

    // ══════════════════════════════════════════════════════════════
    // Dirty tracking
    // ══════════════════════════════════════════════════════════════
    isDirty(item) {
      return this.dirtyItemIds.indexOf(item.id) !== -1
    },

    markDirty(item) {
      if (this.dirtyItemIds.indexOf(item.id) === -1) {
        this.dirtyItemIds.push(item.id)
      }
    },

    // ══════════════════════════════════════════════════════════════
    // COM-02 · "Cód. Artíc." editable por línea
    // ══════════════════════════════════════════════════════════════
    /**
     * Pasa a mayúsculas mientras se escribe. El backend también normaliza, pero
     * así la ejecutiva ve desde el primer momento cómo va a quedar guardado.
     *
     * Se usa :value + @input (en vez de v-model) justamente para poder
     * transformar el valor antes de asignarlo.
     */
    onCodigoInput(item, valor) {
      this.$set(item, 'codigo_articulo_manual', (valor || '').toUpperCase())
    },

    /**
     * Guarda el código por su endpoint dedicado.
     *
     * ⚠️ A propósito NO pasa por saveAll(): ese endpoint está bloqueado cuando la
     * cotización está aprobada y, además, descartaría este campo en silencio
     * devolviendo 200. El endpoint de abajo sí acepta cotizaciones aprobadas y
     * NO recalcula los totales (el código es solo una etiqueta).
     *
     * Se dispara con @change, o sea al salir del campo o con Enter, y solo si el
     * valor realmente cambió.
     */
    async saveCodigo(item) {
      var valor = (item.codigo_articulo_manual || '').trim().toUpperCase()
      this.savingCodigoId = item.id
      try {
        var resp = await this.$http.put(
          '/marketing/quotation/prequotes/' + this.prequote.id + '/item/' + item.id + '/codigo',
          { codigo_articulo_manual: valor }
        )
        var guardado = resp.data && resp.data.item
        if (guardado) {
          // El backend devuelve las 3 claves ya resueltas (manual → catálogo → '').
          this.$set(item, 'codigo_articulo',          guardado.codigo_articulo)
          this.$set(item, 'codigo_articulo_manual',   guardado.codigo_articulo_manual)
          this.$set(item, 'codigo_articulo_catalogo', guardado.codigo_articulo_catalogo)
        }
        this.$message.success(valor
          ? 'Código del artículo actualizado'
          : 'Código en blanco: se vuelve a usar el del catálogo')
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message)
          || 'No se pudo guardar el código del artículo'
        this.$message.error(msg)
        // Se deja lo escrito en pantalla para que se pueda corregir y reintentar.
      } finally {
        this.savingCodigoId = null
      }
    },

    // ══════════════════════════════════════════════════════════════
    // Guardar: N PUT en paralelo, solo los 10 campos de Detalle
    // ══════════════════════════════════════════════════════════════
    async saveAll() {
      if (this.dirtyItemIds.length === 0) {
        this.$message.info('No hay cambios para guardar')
        return
      }

      var self = this
      var itemsToSave = this.prequote.items.filter(function (i) {
        return self.dirtyItemIds.indexOf(i.id) !== -1
      })

      // Validación mínima: el nombre del producto es obligatorio
      var invalid = itemsToSave.filter(function (i) {
        return !i.product_name || !i.product_name.trim()
      })
      if (invalid.length > 0) {
        this.$message.warning('El nombre del producto (Artículo) es obligatorio en los ítems editados.')
        return
      }

      this.savingItems = true

      var promises = itemsToSave.map(function (item) {
        return self.$http.put(
          '/marketing/quotation/prequotes/' + self.prequote.id + '/item/' + item.id,
          {
            product_name:        item.product_name,
            material:            item.material,
            medida:              item.medida,
            tecnica_impresion:   item.tecnica_impresion,
            cantidad_colores:    item.cantidad_colores,
            cantidad_lados:      item.cantidad_lados,
            empaque:             item.empaque,
            personalizacion:     item.personalizacion,
            // Agosto 2026 (punto 9): el Preview ya NO toca tiempo_estimado_dias.
            // Ese campo es el Tiempo de Producción y se edita en Proveedores; si
            // el Preview lo siguiera enviando, volvería a pisarlo y estaríamos en
            // el mismo problema. Acá solo viaja el plazo ofrecido al cliente.
            tiempo_entrega:      item.tiempo_entrega,
            observaciones:       item.observaciones,
            product_description: item.product_description
          }
        ).then(function (resp) {
          return { id: item.id, ok: true, resp: resp }
        }).catch(function (err) {
          return { id: item.id, ok: false, err: err }
        })
      })

      try {
        var results = await Promise.all(promises)
        var successes = results.filter(function (r) { return r.ok })
        var failures  = results.filter(function (r) { return !r.ok })

        // Quitar de "dirty" solo los ítems que se guardaron correctamente
        this.dirtyItemIds = this.dirtyItemIds.filter(function (id) {
          for (var k = 0; k < successes.length; k++) {
            if (successes[k].id === id) return false
          }
          return true
        })

        if (failures.length > 0) {
          var msgs = failures.map(function (f) {
            var e = f.err
            var status = e.response && e.response.status ? e.response.status : ''
            var apiMsg = e.response && e.response.data && e.response.data.message
              ? e.response.data.message : (e.message || 'error desconocido')
            return status ? '(' + status + ') ' + apiMsg : apiMsg
          }).join('; ')
          this.$message({
            message: failures.length + ' de ' + itemsToSave.length + ' ítem(s) fallaron al guardar: ' + msgs,
            type: 'error',
            duration: 8000
          })
          // Refrescar totales con la última respuesta OK, sin recargar
          // (para no perder las ediciones de los ítems que fallaron)
          if (successes.length > 0) {
            this.applyTotals(successes[successes.length - 1].resp.data.prequote)
          }
        } else {
          this.$message.success(itemsToSave.length + ' ítem(s) guardado(s)')
          // Todo OK: recargar limpio para reflejar el recálculo del backend
          await this.loadPrequote()
        }
      } finally {
        this.savingItems = false
      }
    },

    applyTotals(pq) {
      if (!pq || !this.prequote) return
      this.prequote.subtotal  = pq.subtotal
      this.prequote.total_igv = pq.total_igv
      this.prequote.total     = pq.total
      this.$set(this.prequote, 'ganancia_total', pq.ganancia_total)
    },

    // ══════════════════════════════════════════════════════════════
    // Imagen POR ÍTEM (multimodal) — editable, mismo patrón que TabPreCotizacion
    // Endpoints del módulo Marketing:
    //   POST   /marketing/quotation/prequote-items/{id}/reference-image  (campo "image")
    //   GET    /marketing/quotation/prequote-items/{id}/reference-image
    //   DELETE /marketing/quotation/prequote-items/{id}/reference-image
    // ══════════════════════════════════════════════════════════════

    /**
     * URL de la miniatura del ítem servida por el endpoint GET (con cache-bust).
     * Retorna '' si el ítem no tiene imagen.
     */
    itemImageUrl(item) {
      if (!item || !item.id) return ''
      if (!item.product_reference_image && !item.has_image) return ''
      return '/marketing/quotation/prequote-items/' + item.id + '/reference-image?v=' + this.itemImageVersion
    },

    triggerItemImageUpload(item) {
      if (!item || !item.id) return
      this.uploadingItemImageId = item.id
      if (this.$refs.itemImageInput) this.$refs.itemImageInput.click()
    },

    async onItemImageSelected(event) {
      var files = event.target.files
      if (!files || files.length === 0) { this.uploadingItemImageId = null; return }
      var file = files[0]

      var itemId = this.uploadingItemImageId
      if (!itemId) {
        if (this.$refs.itemImageInput) this.$refs.itemImageInput.value = ''
        return
      }

      // Validación cliente espejo del backend (mime + 5MB)
      var allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
      if (allowed.indexOf(file.type) === -1) {
        this.$message.error('Tipo de imagen no permitido. Solo JPG, PNG, WEBP o GIF.')
        this.uploadingItemImageId = null
        if (this.$refs.itemImageInput) this.$refs.itemImageInput.value = ''
        return
      }
      if (file.size > 5 * 1024 * 1024) {
        this.$message.error('La imagen excede 5 MB.')
        this.uploadingItemImageId = null
        if (this.$refs.itemImageInput) this.$refs.itemImageInput.value = ''
        return
      }

      try {
        var fd = new FormData()
        fd.append('image', file)
        var resp = await this.$http.post(
          '/marketing/quotation/prequote-items/' + itemId + '/reference-image',
          fd,
          { headers: { 'Content-Type': 'multipart/form-data' } }
        )
        if (resp.data && resp.data.ok) {
          this.$message.success('Imagen del ítem guardada')
          this.markItemHasImage(itemId, resp.data.image ? resp.data.image.path : true)
          this.itemImageVersion++
        } else {
          this.$message.error((resp.data && resp.data.message) || 'No se pudo subir la imagen')
        }
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error subiendo imagen'
        this.$message.error(msg)
      } finally {
        this.uploadingItemImageId = null
        if (this.$refs.itemImageInput) this.$refs.itemImageInput.value = ''
      }
    },

    async deleteItemImage(item) {
      if (!item || !item.id) return
      try {
        await this.$confirm('¿Quitar la imagen de este producto?', 'Confirmar',
          { confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar', type: 'warning' })
      } catch (e) { return }

      try {
        var resp = await this.$http.delete(
          '/marketing/quotation/prequote-items/' + item.id + '/reference-image'
        )
        if (resp.data && resp.data.ok) {
          this.$message.success('Imagen del ítem eliminada')
          this.markItemHasImage(item.id, null)
          this.itemImageVersion++
        } else {
          this.$message.error((resp.data && resp.data.message) || 'No se pudo eliminar la imagen')
        }
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error eliminando imagen'
        this.$message.error(msg)
      }
    },

    /**
     * Actualiza de forma reactiva el flag de imagen del ítem en prequote.items.
     */
    markItemHasImage(itemId, value) {
      if (!this.prequote || !this.prequote.items) return
      for (var i = 0; i < this.prequote.items.length; i++) {
        if (parseInt(this.prequote.items[i].id) === parseInt(itemId)) {
          this.$set(this.prequote.items[i], 'product_reference_image', value || null)
          this.$set(this.prequote.items[i], 'has_image', !!value)
          break
        }
      }
    },

    // ══════════════════════════════════════════════════════════════
    // Helpers de display (read-only)
    // ══════════════════════════════════════════════════════════════

    safeFixed(v) {
      return (parseFloat(v) || 0).toFixed(2)
    },

    estadoTagType(estado) {
      var map = {
        'borrador': 'info', 'en_revision': 'warning', 'ajustada': 'warning',
        'validada': 'primary', 'aprobada': 'success', 'rechazada': 'danger',
        'propuesta_emitida': 'primary', 'convertida_en_proyecto': 'success'
      }
      return map[estado] || 'info'
    }
  }
}
</script>

<style scoped>
.tab-preview-editable {
  padding: 4px 2px;
}

/* R4 — Campos en solo-lectura (cuando la cotización está validada/aprobada):
   texto legible en vez del gris muy claro por defecto de Element UI. */
.tab-preview-editable >>> .el-input.is-disabled .el-input__inner,
.tab-preview-editable >>> .el-textarea.is-disabled .el-textarea__inner,
.tab-preview-editable >>> .el-input-number.is-disabled .el-input__inner {
  color: #5a5e66;
  -webkit-text-fill-color: #5a5e66;
}

.pe-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 8px 10px;
  background: #f5f7fa;
  border-radius: 4px;
  margin-bottom: 10px;
  flex-wrap: wrap;
  gap: 6px;
}
.pe-hint {
  display: block;
  font-size: 12px;
  color: #888;
  margin-top: 4px;
}
.pe-dirty-count {
  font-size: 12px;
  color: #e6a23c;
  font-weight: 600;
  white-space: nowrap;
}

.pe-table-wrap {
  overflow-x: auto;
}

/* ── Tabla replicando el PDF ── */
.preview-table {
  width: 100%;
  border-collapse: collapse;
  font-family: Arial, sans-serif;
  font-size: 9px;
}
.preview-table th {
  background: #0088cc;
  color: #fff;
  padding: 5px 3px;
  border: 1px solid #444;
  text-align: center;
  font-size: 9px;
}
.preview-table th[title] { cursor: help; }
.preview-table td {
  border: 1px solid #bbb;
  padding: 4px;
  font-size: 9px;
  vertical-align: top;
}
.preview-table td.numero   { text-align: center; width: 22px; }
.preview-table td.codigo   { width: 92px; font-family: monospace; font-size: 8px; color: #888; text-align: center; }

/* COM-02 · Input del "Cód. Artíc.": monoespaciado y compacto para que entren
   los 12 caracteres sin ensanchar la columna ni romper la fidelidad con el PDF. */
.pe-codigo-input >>> .el-input__inner {
  font-family: monospace;
  font-size: 10px;
  text-align: center;
  text-transform: uppercase;
  padding: 0 4px;
  height: 24px;
  line-height: 24px;
}
.preview-table td.articulo { width: 110px; font-weight: bold; }
.preview-table td.foto     { text-align: center; color: #888; width: 60px; font-size: 8px; }

/* Foto por ítem (editable) */
.pe-foto-thumb {
  display: block; margin: 0 auto;
  max-width: 55px; max-height: 50px; object-fit: contain;
  border: 1px solid #ddd; border-radius: 3px; background: #fafafa;
}
.pe-foto-empty { display: block; font-style: italic; color: #aaa; }
.pe-foto-actions { display: flex; flex-direction: column; align-items: center; margin-top: 2px; line-height: 1; }
.pe-foto-actions >>> .el-button { padding: 1px 0; font-size: 10px; }
.pe-foto-del >>> span, .pe-foto-del { color: #f56c6c; }
.preview-table td.cantidad { text-align: center; width: 36px; font-weight: bold; }
.preview-table td.precio   { text-align: right; width: 60px; }
.preview-table td.tiempo   { width: 120px; font-size: 9px; }
.preview-table td.detalles { min-width: 240px; }
.preview-table td.total    { text-align: right; width: 70px; font-weight: bold; }

/* Fila con cambios sin guardar */
.preview-row--dirty {
  background-color: #fffbe6;
}
.preview-row--dirty td {
  border-color: #e6a23c;
}

/* ── Editor de detalles inline ── */
.detalle-row {
  display: flex;
  align-items: flex-start;
  gap: 4px;
  margin-bottom: 3px;
}
.detalle-row label {
  font-weight: 600;
  color: #303133;
  min-width: 78px;
  font-size: 9px;
  padding-top: 5px;
  white-space: nowrap;
}
.detalle-row--inline {
  align-items: center;
}

.detalle-row >>> .el-input__inner,
.detalle-row >>> .el-textarea__inner {
  font-size: 9px;
  padding: 2px 6px;
  background: #fafafa;
  line-height: 1.3;
}
.detalle-row >>> .el-input__inner:focus,
.detalle-row >>> .el-textarea__inner:focus {
  background: #fff;
}
.detalle-row--inline >>> .el-input-number--mini {
  width: 110px;
}
/* El padding genérico de arriba pisa el padding-right que el el-input-number
   necesita para sus controles a la derecha, ocultando el número. Lo restauramos. */
.detalle-row--inline >>> .el-input-number .el-input__inner {
  padding-left: 8px;
  padding-right: 34px;
  text-align: left;
}

/* ── Totales ── */
.pe-totales {
  max-width: 320px;
  margin-left: auto;
  margin-top: 8px;
}
.pe-totales-row {
  display: flex;
  justify-content: space-between;
  padding: 3px 8px;
  border-bottom: 1px dotted #ddd;
  font-size: 12px;
}
.pe-totales-grand {
  background: #0088cc;
  color: #fff;
  font-size: 14px;
  padding: 5px 8px;
  border-radius: 3px;
  margin-top: 4px;
}

.pe-actions {
  margin-top: 14px;
  padding-top: 10px;
  border-top: 1px solid #ebeef5;
  display: flex;
  justify-content: flex-end;
  gap: 6px;
}

/* ── Agosto 2026 (punto 11) · Barra de formato rápido ──
   Botones chicos y discretos: la celda de Detalles ya está cargada de campos y
   una barra de editor completo la volvería ilegible. */
.campo-con-formato { position: relative; }
/* Columna que agrupa barra + campo + vista previa. El min-width: 0 es lo que
   permite que el textarea se achique dentro de la celda: sin él, un ítem de
   flex no baja de su ancho de contenido y la tabla se desborda a lo ancho. */
.campo-cuerpo {
  flex: 1;
  min-width: 0;
}
.fmt-barra {
  display: flex;
  gap: 3px;
  margin-bottom: 2px;
}
.fmt-btn {
  width: 20px;
  height: 18px;
  line-height: 1;
  padding: 0;
  font-size: 11px;
  border: 1px solid #dcdfe6;
  border-radius: 3px;
  background: #fff;
  color: #606266;
  cursor: pointer;
}
.fmt-btn:hover { border-color: #409EFF; background: #ecf5ff; }
.fmt-b { font-weight: 700; }
.fmt-i { font-style: italic; font-family: Georgia, serif; }
/* Los tres de color muestran una "A" del color que aplican: se entiende sin
   leer el tooltip, que es de lo que se trata una opción rápida. */
.fmt-rojo  { color: #C00000; font-weight: 700; }
.fmt-azul  { color: #1F4E79; font-weight: 700; }
.fmt-verde { color: #2E7D32; font-weight: 700; }
.fmt-ayuda {
  font-size: 10px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.4;
}
.fmt-ayuda code {
  background: #f4f4f5;
  padding: 0 3px;
  border-radius: 2px;
  color: #606266;
}
</style>
