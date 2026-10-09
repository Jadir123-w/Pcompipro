<template>
  <div v-loading="loading" class="tab-cotizacion-final">

    <!-- ═══════════════════════════════════════════════════════════════
         ESTADO 1: No hay precotización aún
         ═══════════════════════════════════════════════════════════════ -->
    <div v-if="!prequote && !loading" style="text-align:center; padding:30px 0;">
      <i class="el-icon-document-copy" style="font-size:48px; color:#c0c4cc;"></i>
      <p style="color:#888; margin:15px 0;">
        Para generar la <strong>Cotización Final</strong> primero debes crear la Pre-Cotización.
      </p>
      <p style="color:#aaa; font-size:12px;">
        Ve a la pestaña "Pre-Cotización" y genera la precotización con IA.
      </p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         ESTADO 2: Hay precotización pero aún no hay proposal (CTA)
         ═══════════════════════════════════════════════════════════════ -->
    <div v-if="prequote && !proposal && !loading" style="text-align:center; padding:30px 0;">
      <p style="color:#555; margin-bottom:15px;">
        Pre-Cotización detectada. Genera la <strong>Cotización Final</strong> con IA.
      </p>
      <p style="color:#888; font-size:12px; margin-bottom:20px;">
        La IA pre-llenará cabecera, destinatario, condiciones y recomendará
        si incluir datos bancarios basándose en el histórico del cliente.
      </p>
      <el-button type="primary" icon="el-icon-magic-stick"
                 :loading="generating" @click="loadOrCreateProposal">
        Generar Cotización Final con IA
      </el-button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         ESTADO 3: Proposal cargada - Editor con 5 secciones
         ═══════════════════════════════════════════════════════════════ -->
    <div v-if="proposal">

      <!-- Barra superior: estado + acciones globales -->
      <div class="cf-toolbar">
        <div>
          <strong>N° Cotización:</strong>
          <span class="cf-num">{{ proposal.numero_cotizacion }}</span>
          <el-tag :type="estadoTagType(proposal.estado)" size="small" style="margin-left:8px;">
            {{ proposal.estado }}
          </el-tag>
        </div>
        <div>
          <el-button v-if="isEditable" size="mini" icon="el-icon-refresh"
                     :loading="regenerating" @click="regenerateAll">
            Regenerar todo con IA
          </el-button>
          <el-button v-if="pdfUrl" size="mini" type="success" icon="el-icon-view"
                     @click="openPdf">
            Ver último PDF
          </el-button>
        </div>
      </div>

      <!-- ────────────────────────────────────────────────────────────
           COLLAPSE: 5 secciones plegables
           ──────────────────────────────────────────────────────────── -->
      <el-collapse v-model="activeSections">

        <!-- ─────────── 1. Cabecera ─────────── -->
        <el-collapse-item title="1. Cabecera" name="cabecera">
          <el-row :gutter="10">
            <el-col :span="6">
              <label class="cf-label" title="Prefijo de la numeración de la cotización (ej. COT).">Prefijo <i class="el-icon-info cf-tip"></i></label>
              <el-input v-model="proposal.prefijo_numeracion" size="small" :disabled="!isEditable"></el-input>
            </el-col>
            <el-col :span="6">
              <label class="cf-label" title="Fecha de emisión de la cotización.">Fecha emisión <i class="el-icon-info cf-tip"></i></label>
              <el-date-picker v-model="proposal.fecha_emision" size="small" type="date"
                              value-format="yyyy-MM-dd" style="width:100%;" :disabled="!isEditable">
              </el-date-picker>
            </el-col>
            <el-col :span="6">
              <label class="cf-label" title="Número correlativo de la cotización dentro del día.">Secuencial día <i class="el-icon-info cf-tip"></i></label>
              <el-input-number v-model="proposal.secuencial_dia" size="small" :min="1" :max="99"
                               controls-position="right" :disabled="!isEditable"
                               style="width:100%;"></el-input-number>
            </el-col>
            <el-col :span="6">
              <label class="cf-label" title="Número de versión de esta cotización enviada al cliente.">Versión externa <i class="el-icon-info cf-tip"></i></label>
              <el-input-number v-model="proposal.version_externa" size="small" :min="1" :max="99"
                               controls-position="right" :disabled="!isEditable"
                               style="width:100%;"></el-input-number>
            </el-col>
          </el-row>

          <el-divider></el-divider>

          <label class="cf-label" title="Persona o empresa a quien se dirige la cotización.">Destinatario <i class="el-icon-info cf-tip"></i></label>
          <el-row :gutter="10">
            <el-col :span="12">
              <el-input v-model="proposal.destinatario_nombre" size="small" placeholder="Nombre"
                        :disabled="!isEditable"></el-input>
            </el-col>
            <el-col :span="12">
              <el-input v-model="proposal.destinatario_cargo" size="small" placeholder="Cargo"
                        :disabled="!isEditable"></el-input>
            </el-col>
          </el-row>
          <el-row :gutter="10" style="margin-top:6px;">
            <el-col :span="12">
              <el-input v-model="proposal.destinatario_sede" size="small" placeholder="Sede / Ubicación"
                        :disabled="!isEditable"></el-input>
            </el-col>
            <el-col :span="12">
              <el-input v-model="proposal.destinatario_empresa" size="small" placeholder="Empresa cliente"
                        :disabled="!isEditable"></el-input>
            </el-col>
          </el-row>

          <el-divider></el-divider>

          <div style="display:flex; justify-content:space-between; align-items:center;">
            <label class="cf-label" style="margin:0;">Título descriptivo</label>
            <el-button v-if="isEditable" size="mini" type="text" icon="el-icon-magic-stick"
                       @click="regenerateField('titulo_descriptivo')">
              Regenerar con IA
            </el-button>
          </div>
          <el-input v-model="proposal.titulo_descriptivo" type="textarea" :autosize="{minRows: 2, maxRows: 6}" size="small"
                    :disabled="!isEditable"></el-input>

          <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
            <label class="cf-label" style="margin:0;">Notas encabezado (3 líneas visibles en el PDF)</label>
            <el-button v-if="isEditable" size="mini" type="text" icon="el-icon-magic-stick"
                       @click="regenerateField('notas_encabezado')">
              Regenerar con IA
            </el-button>
          </div>
          <div v-for="(nota, idx) in notasEncabezado" :key="'nota-' + idx" style="margin-bottom:4px;">
            <el-input v-model="notasEncabezado[idx]" size="small" :disabled="!isEditable"></el-input>
          </div>
        </el-collapse-item>

        <!-- ─────────── 2. Productos (solo lectura) ─────────── -->
        <el-collapse-item title="2. Productos" name="productos">
          <el-alert title="Los productos se editan en la pestaña Pre-Cotización" type="info"
                    :closable="false" show-icon style="margin-bottom:8px;">
          </el-alert>
          <el-table v-if="prequote && prequote.items" :data="prequote.items" stripe border size="mini">
            <el-table-column prop="item_order" label="#" width="40" align="center"></el-table-column>
            <el-table-column label="Foto" width="60" align="center">
              <template slot-scope="scope">
                <img v-if="itemImageUrl(scope.row)" :src="itemImageUrl(scope.row)" alt="foto"
                     class="cf-foto-thumb"/>
                <span v-else class="cf-foto-empty"><i class="el-icon-picture-outline"></i></span>
              </template>
            </el-table-column>
            <el-table-column prop="product_name" label="Producto" min-width="160"></el-table-column>
            <el-table-column prop="quantity" label="Cant." width="60" align="center"></el-table-column>
            <el-table-column label="V. Unit" width="80" align="right">
              <template slot-scope="scope">
                {{ moneySign }} {{ parseFloat(scope.row.valor_unitario).toFixed(2) }}
              </template>
            </el-table-column>
            <el-table-column label="Total" width="100" align="right">
              <template slot-scope="scope">
                <strong>{{ moneySign }} {{ parseFloat(scope.row.valor_total).toFixed(2) }}</strong>
              </template>
            </el-table-column>
          </el-table>
        </el-collapse-item>

        <!-- ─────────── 3. Totales ─────────── -->
        <el-collapse-item title="3. Totales" name="totales">
          <el-row :gutter="10" align="middle" type="flex">
            <el-col :span="8">
              <label class="cf-label" title="Moneda de la cotización (Soles / Dólares).">Moneda <i class="el-icon-info cf-tip"></i></label>
              <el-select v-model="proposal.moneda" size="small" :disabled="!isEditable" style="width:100%;">
                <el-option label="PEN — Nuevos Soles" value="PEN"></el-option>
                <el-option label="USD — Dólares (futuro)" value="USD" disabled></el-option>
              </el-select>
            </el-col>
            <el-col :span="16">
              <label class="cf-label" title="Define si la cotización lleva IGV (18%). Afecta el total: Con IGV suma el 18%; Sin IGV el total es igual al subtotal.">Tratamiento IGV <i class="el-icon-info cf-tip"></i></label>
              <el-switch v-model="proposal.aplica_igv" :disabled="!isEditable"
                         active-text="Con IGV"
                         inactive-text="Sin IGV">
              </el-switch>
            </el-col>
          </el-row>

          <!-- Agosto 2026 (puntos 13 y 14) — Dos opciones que cambian solo lo que
               SE MUESTRA en el PDF; los montos guardados no se tocan. -->
          <el-row :gutter="10" align="middle" type="flex" style="margin-top:12px;">
            <el-col :span="12">
              <label class="cf-label" title="Cómo se muestran los costos unitario y total de cada producto en el PDF. El rótulo de la columna cambia solo: 'Costo SIN IGV' o 'Costo CON IGV'.">Costos en el PDF <i class="el-icon-info cf-tip"></i></label>
              <el-switch v-model="proposal.precios_incluyen_igv" :disabled="!isEditable"
                         active-text="Con IGV"
                         inactive-text="Sin IGV">
              </el-switch>
            </el-col>
            <el-col :span="12">
              <label class="cf-label" title="Oculta el subtotal y el total consolidado del PDF. Útil en cotizaciones por escala, donde sumar las alternativas no tiene sentido porque el cliente elige una sola.">Mostrar totales <i class="el-icon-info cf-tip"></i></label>
              <el-switch v-model="proposal.incluye_totales" :disabled="!isEditable"
                         active-text="Sí"
                         inactive-text="No">
              </el-switch>
            </el-col>
          </el-row>

          <el-divider></el-divider>

          <div class="cf-totales">
            <div class="cf-totales-row">
              <span>Subtotal:</span>
              <strong>{{ moneySign }} {{ safeParse(prequote.subtotal).toFixed(2) }}</strong>
            </div>
            <div v-if="proposal.aplica_igv" class="cf-totales-row">
              <span>IGV (18%):</span>
              <strong>{{ moneySign }} {{ safeParse(prequote.total_igv).toFixed(2) }}</strong>
            </div>
            <div class="cf-totales-row cf-totales-grand">
              <span>{{ proposal.aplica_igv ? 'TOTAL FACTURADO:' : 'TOTAL (Sin IGV):' }}</span>
              <strong>{{ moneySign }} {{ safeParse(proposal.aplica_igv ? prequote.total : prequote.subtotal).toFixed(2) }}</strong>
            </div>
          </div>
        </el-collapse-item>

        <!-- ─────────── 4. Condiciones Generales ─────────── -->
        <el-collapse-item title="4. Condiciones Generales" name="condiciones">
          <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:8px;">
            <small style="color:#909399; line-height:1.4;">
              Formato: <strong>una línea = una viñeta</strong> · <code>&gt;</code> al inicio de la línea = párrafo (sin viñeta) · <code>**negrita**</code>.
              Dejá una sección <strong>vacía</strong> para que NO aparezca en el PDF.
            </small>
            <el-button v-if="isEditable" size="mini" type="text" icon="el-icon-magic-stick" style="white-space:nowrap;"
                       @click="regenerateField('condiciones')">
              Regenerar (qué secciones incluir) con IA
            </el-button>
          </div>

          <div v-for="sec in condicionesSecciones" :key="sec.key" style="margin-bottom:10px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
              <label class="cf-label" style="margin:0;">{{ sec.label }}</label>
              <el-button v-if="isEditable && condicionesDefaults[sec.key]" size="mini" type="text"
                         icon="el-icon-refresh-left" style="padding:0;"
                         @click="restoreCondicionDefault(sec.key)">Restaurar por defecto</el-button>
            </div>
            <el-input v-model="condiciones[sec.key]" type="textarea" :autosize="{minRows: 2, maxRows: 8}" size="small"
                      :disabled="!isEditable"
                      placeholder="(Vacío = esta sección no aparece en el PDF)"></el-input>
          </div>
        </el-collapse-item>

        <!-- ─────────── 5. Footer + bloques opcionales ─────────── -->
        <el-collapse-item title="5. Footer (datos empresa, banco, firma)" name="footer">
          <el-row :gutter="10">
            <el-col :span="12">
              <el-switch v-model="proposal.incluye_datos_empresa" :disabled="!isEditable"
                         active-text="Incluir bloque DATOS DE LA EMPRESA">
              </el-switch>
              <div style="color:#888; font-size:12px; margin-top:4px;">
                (Razón social, RUC, dirección, teléfono — desde configuración del cotizador)
              </div>
            </el-col>
            <el-col :span="12">
              <el-switch v-model="proposal.incluye_datos_bancarios" :disabled="!isEditable"
                         active-text="Incluir bloque CUENTA BANCARIA">
              </el-switch>
              <div style="color:#888; font-size:12px; margin-top:4px;">
                (Entidad, número de cuenta, CCI, titular)
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="10" style="margin-top:12px;" v-if="proposal.incluye_datos_bancarios">
            <el-col :span="24">
              <label class="cf-label" title="Cuenta bancaria que se mostrará al cliente para el pago.">Cuenta bancaria a mostrar <i class="el-icon-info cf-tip"></i></label>
              <el-select v-model="proposal.bank_account_id" size="small" :disabled="!isEditable"
                         style="width:100%;" placeholder="Elegir cuenta">
                <el-option v-for="b in bankAccounts" :key="b.id"
                           :label="b.description + ' — ' + b.number" :value="b.id">
                </el-option>
              </el-select>
            </el-col>
          </el-row>

          <!-- COM-02 Req#3: toggle por cotización del bloque de sostenibilidad del PDF.
               Default true (backend) → nada cambia hasta que la ejecutiva lo apague. -->
          <el-row :gutter="10" style="margin-top:12px;">
            <el-col :span="12">
              <el-switch v-model="proposal.incluye_sostenibilidad" :disabled="!isEditable"
                         active-text="Incluir mensaje de SOSTENIBILIDAD">
              </el-switch>
              <div style="color:#888; font-size:12px; margin-top:4px;">
                (Bloque verde: comunidad Shipibo-Conibo / árboles / UNESCO. Apágalo si esta cotización no lo debe llevar.)
              </div>
            </el-col>
          </el-row>

          <el-divider></el-divider>

          <label class="cf-label" title="Ejecutiva comercial que figura como responsable y firma la cotización.">Ejecutiva que firma <i class="el-icon-info cf-tip"></i></label>
          <el-select v-model="proposal.ejecutiva_id" size="small" :disabled="!isEditable"
                     style="width:100%;" placeholder="Seleccionar usuario" filterable clearable>
            <el-option v-for="u in users" :key="u.id"
                       :label="userLabel(u)" :value="u.id">
            </el-option>
          </el-select>
          <div style="color:#888; font-size:12px; margin-top:4px;">
            (El PDF usará su nombre, cargo, email y celular como bloque de firma.)
          </div>
        </el-collapse-item>

      </el-collapse>

      <!-- ────────────────────────────────────────────────────────────
           ACCIONES FINALES
           ──────────────────────────────────────────────────────────── -->
      <div class="cf-actions">
        <el-button v-if="isEditable" type="primary" size="small" icon="el-icon-check"
                   :loading="saving" @click="saveChanges">
          Guardar cambios
        </el-button>
        <el-button v-if="proposal" size="small" icon="el-icon-document"
                   :loading="generatingPdf" @click="generatePdf">
          Previsualizar PDF
        </el-button>
        <el-button v-if="proposal" size="small" icon="el-icon-message"
                   :loading="sendingTest" @click="sendTestEmail">
          Enviar correo de prueba
        </el-button>
        <el-button v-if="isEditable" type="success" size="small" icon="el-icon-s-promotion"
                   :loading="emitting" @click="emitToClient">
          Emitir al cliente
        </el-button>
        <el-button v-if="proposal.estado === 'emitida'" type="warning" size="small"
                   icon="el-icon-message" @click="changeStatus('enviada_cliente')">
          Marcar como "Enviada al cliente"
        </el-button>
        <el-button v-if="canApprove" type="success" size="small" icon="el-icon-circle-check"
                   @click="changeStatus('aprobada_cliente')">
          Cliente aprobó
        </el-button>
        <el-button v-if="canReject" type="danger" size="small" icon="el-icon-circle-close"
                   @click="changeStatus('rechazada_cliente')" plain>
          Cliente rechazó
        </el-button>
      </div>

    </div>
  </div>
</template>

<script>
export default {
  name: 'TabCotizacionFinal',
  props: {
    requerimientoId: { type: [Number, String], default: null },
    // Modo standalone: si viene prequoteId, carga directamente la prequote
    prequoteId: { type: [Number, String], default: null },
    // R5: contacto del requerimiento, para pre-llenar el Destinatario por defecto
    contactoNombre: { type: String, default: '' },
    contactoCargo: { type: String, default: '' },
    // R9: correo del contacto, para pre-llenar el modal del correo de prueba
    contactoEmail: { type: String, default: '' }
  },
  data() {
    return {
      loading: false,
      generating: false,
      regenerating: false,
      saving: false,
      generatingPdf: false,
      emitting: false,
      sendingTest: false,

      prequote: null,
      proposal: null,
      pdfUrl: '',

      bankAccounts: [],
      users: [],

      activeSections: ['cabecera', 'productos', 'totales', 'condiciones', 'footer'],

      // Parseos locales para los JSON
      notasEncabezado: ['', '', ''],
      // COM-02 · Condiciones Generales = 11 secciones (markdown-lite: 1 línea = 1 viñeta, `> ` = párrafo, **negrita**). Vacío = oculta.
      condicionesSecciones: [
        { key: 'costo_servicio', label: 'Costo del Servicio' },
        { key: 'forma_pago', label: 'Forma de Pago' },
        { key: 'tiempo_ejecucion', label: 'Tiempo de ejecución' },
        { key: 'consideraciones_produccion', label: 'Consideraciones de producción' },
        { key: 'moneda_texto', label: 'Moneda' },
        { key: 'validez_oferta', label: 'Validez de la oferta' },
        { key: 'consideraciones_entrega', label: 'Consideraciones para Entrega de Producto' },
        { key: 'terminos_instalacion', label: 'Términos para servicio instalación' },
        { key: 'garantia_servicio', label: 'Garantía del Servicio' },
        { key: 'servicio_complementario', label: 'Servicio complementario' },
        { key: 'especificacion_upcycling', label: 'Especificación del servicio UPCYCLING' }
      ],
      condiciones: {
        costo_servicio: '', forma_pago: '', tiempo_ejecucion: '',
        consideraciones_produccion: '', moneda_texto: '', validez_oferta: '',
        consideraciones_entrega: '', terminos_instalacion: '', garantia_servicio: '',
        servicio_complementario: '', especificacion_upcycling: ''
      },
      condicionesDefaults: {}
    }
  },
  computed: {
    isEditable() {
      return this.proposal && this.proposal.estado === 'borrador'
    },
    moneySign() {
      return this.proposal && this.proposal.moneda === 'USD' ? '$' : 'S/'
    },
    canApprove() {
      return this.proposal && ['emitida', 'enviada_cliente'].indexOf(this.proposal.estado) !== -1
    },
    canReject() {
      return this.proposal && ['emitida', 'enviada_cliente'].indexOf(this.proposal.estado) !== -1
    }
  },
  watch: {
    requerimientoId: {
      immediate: true,
      handler(newId) {
        if (newId && !this.prequoteId) this.loadInitialData()
      }
    },
    prequoteId: {
      immediate: true,
      handler(newId) {
        if (newId) this.loadInitialDataByPrequote(newId)
      }
    }
  },
  methods: {
    // ══════════════════════════════════════════════════════════════
    // Carga inicial: busca prequote del requerimiento → si hay, busca proposal
    // ══════════════════════════════════════════════════════════════
    async loadInitialData() {
      this.loading = true
      this.resetState()

      try {
        // 1) Buscar prequote del requerimiento
        var response = await this.$http.get('/marketing/quotation/prequotes', {
          params: { requerimiento_id: this.requerimientoId }
        })
        var list = response.data.prequotes.data || response.data.prequotes || []

        if (list.length === 0) {
          this.prequote = null
          this.proposal = null
          this.loading = false
          return
        }

        // 2) Cargar detalle completo de la prequote más reciente
        var pqDetail = await this.$http.get('/marketing/quotation/prequotes/' + list[0].id)
        this.prequote = this.parsePrequote(pqDetail.data.prequote)

        // 3) Intentar cargar proposal existente (sin crearla aún)
        //    El endpoint también embebe bank_accounts y users en la respuesta
        await this.tryLoadExistingProposal()

      } catch (e) {
        this.prequote = null
        this.proposal = null
      }
      this.loading = false
    },

    // Modo standalone: cargar directamente por prequote_id
    async loadInitialDataByPrequote(id) {
      this.loading = true
      this.resetState()
      try {
        var pqDetail = await this.$http.get('/marketing/quotation/prequotes/' + id)
        this.prequote = this.parsePrequote(pqDetail.data.prequote)
        await this.tryLoadExistingProposal()
      } catch (e) {
        this.prequote = null
        this.proposal = null
      }
      this.loading = false
    },

    // GET /prequotes/{id}/proposal — si no existe, el endpoint la crea con IA
    async loadOrCreateProposal() {
      if (!this.prequote) return
      this.generating = true
      try {
        var response = await this.$http.get(
          '/marketing/quotation/prequotes/' + this.prequote.id + '/proposal'
        )
        this.applyShowResponse(response.data)
        this.$message.success('Cotización Final generada con IA')
      } catch (e) {
        var msg = e.response && e.response.data && e.response.data.message
          ? e.response.data.message : 'Error generando la Cotización Final'
        this.$message.error(msg)
      }
      this.generating = false
    },

    // Intento silencioso: si existe la devuelve, si no la crea también (el endpoint lo hace)
    async tryLoadExistingProposal() {
      try {
        var response = await this.$http.get(
          '/marketing/quotation/prequotes/' + this.prequote.id + '/proposal'
        )
        if (response.data && response.data.proposal) {
          this.applyShowResponse(response.data)
        }
      } catch (e) {
        // Silencioso: si falla, queda proposal = null y el usuario ve el CTA
      }
    },

    // Aplicar respuesta completa del show: proposal + bank_accounts + users
    applyShowResponse(data) {
      this.proposal = data.proposal
      // Agosto 2026 (puntos 13 y 14) — Normalizar los dos switches nuevos.
      //
      // `incluye_totales` cae a true si viene null: es el default de la columna, y
      // durante el despliegue hay unos segundos en que la migración de tenant
      // todavía no corrió y el campo no existe. Sin esto, el switch arrancaría
      // apagado y al guardar ocultaría los totales sin que nadie lo pidiera.
      //
      // `precios_incluyen_igv` cae a false, que es el comportamiento que tuvo el
      // PDF desde siempre: costos sin IGV.
      if (this.proposal) {
        if (this.proposal.incluye_totales === null || this.proposal.incluye_totales === undefined) {
          this.$set(this.proposal, 'incluye_totales', true)
        }
        if (this.proposal.precios_incluyen_igv === null || this.proposal.precios_incluyen_igv === undefined) {
          this.$set(this.proposal, 'precios_incluyen_igv', false)
        }
      }
      this.bankAccounts = data.bank_accounts || []
      this.users = data.users || []
      this.parseProposalJson()
    },

    // ══════════════════════════════════════════════════════════════
    // Guardar cambios
    // ══════════════════════════════════════════════════════════════
    async saveChanges() {
      if (!this.proposal) return
      this.saving = true

      // Consolidar JSON desde los estados locales
      // Las 11 secciones ya son strings en this.condiciones (una vacía = oculta en el PDF).
      var condicionesPayload = Object.assign({}, this.condiciones)

      var payload = {
        prefijo_numeracion:      this.proposal.prefijo_numeracion,
        fecha_emision:           this.proposal.fecha_emision,
        secuencial_dia:          this.proposal.secuencial_dia,
        version_externa:         this.proposal.version_externa,
        destinatario_nombre:     this.proposal.destinatario_nombre,
        destinatario_cargo:      this.proposal.destinatario_cargo,
        destinatario_sede:       this.proposal.destinatario_sede,
        destinatario_empresa:    this.proposal.destinatario_empresa,
        titulo_descriptivo:      this.proposal.titulo_descriptivo,
        notas_encabezado:        this.notasEncabezado,
        aplica_igv:              this.proposal.aplica_igv,
        // Agosto 2026 (punto 14): cómo se muestran los costos por ítem en el PDF.
        // El campo ya existía en el modelo pero nunca se enviaba desde acá.
        precios_incluyen_igv:    this.proposal.precios_incluyen_igv,
        moneda:                  this.proposal.moneda,
        condiciones:             condicionesPayload,
        incluye_datos_empresa:   this.proposal.incluye_datos_empresa,
        incluye_datos_bancarios: this.proposal.incluye_datos_bancarios,
        incluye_sostenibilidad:  this.proposal.incluye_sostenibilidad,
        // Agosto 2026 (punto 13): mostrar u ocultar el bloque de totales del PDF.
        incluye_totales:         this.proposal.incluye_totales,
        bank_account_id:         this.proposal.bank_account_id,
        ejecutiva_id:            this.proposal.ejecutiva_id
      }

      try {
        var response = await this.$http.put(
          '/marketing/quotation/proposals/' + this.proposal.id, payload
        )
        this.proposal = response.data.proposal
        this.parseProposalJson()
        this.$message.success('Cambios guardados')
      } catch (e) {
        var msg = e.response && e.response.data && e.response.data.message
          ? e.response.data.message : 'Error guardando cambios'
        this.$message.error(msg)
      }
      this.saving = false
    },

    // ══════════════════════════════════════════════════════════════
    // Regenerar con IA (todos los campos o uno específico)
    // ══════════════════════════════════════════════════════════════
    async regenerateAll() {
      await this.regenerateWithField(null)
    },

    async regenerateField(field) {
      await this.regenerateWithField(field)
    },

    async regenerateWithField(field) {
      if (!this.proposal) return
      this.regenerating = true
      try {
        var payload = field ? { field: field } : {}
        var response = await this.$http.post(
          '/marketing/quotation/proposals/' + this.proposal.id + '/regenerate-with-ai',
          payload
        )
        this.proposal = response.data.proposal
        this.parseProposalJson()
        var msg = field
          ? ('Campo "' + field + '" regenerado con IA')
          : 'Cotización regenerada con IA'
        if (response.data.ai_fallback) {
          this.$message.warning(msg + ' (modo fallback — IA no disponible)')
        } else {
          this.$message.success(msg)
        }
      } catch (e) {
        this.$message.error('Error regenerando con IA')
      }
      this.regenerating = false
    },

    // ══════════════════════════════════════════════════════════════
    // PDF
    // ══════════════════════════════════════════════════════════════
    async generatePdf() {
      if (!this.proposal) return
      this.generatingPdf = true
      try {
        // Primero guardar cambios locales (si es editable)
        if (this.isEditable) await this.saveChanges()

        var response = await this.$http.post(
          '/marketing/quotation/proposals/' + this.proposal.id + '/pdf'
        )
        this.pdfUrl = response.data.pdf_url
        this.$message.success('PDF generado')
        window.open(this.pdfUrl, '_blank')
      } catch (e) {
        this.$message.error('Error generando PDF')
      }
      this.generatingPdf = false
    },

    openPdf() {
      if (this.pdfUrl) window.open(this.pdfUrl, '_blank')
    },

    // ══════════════════════════════════════════════════════════════
    // Enviar correo de prueba (NO cambia estado). Se pide el correo destino
    // en un modal; si se deja vacío, el backend usa el correo interno
    // configurado en el cotizador (respaldo).
    // ══════════════════════════════════════════════════════════════
    async sendTestEmail() {
      if (!this.proposal) return

      var correo
      try {
        var res = await this.$prompt(
          'Correo al que se enviará la cotización de prueba. Si lo dejas vacío, se usará el correo interno configurado. Esto NO cambia el estado de la cotización.',
          'Enviar correo de prueba',
          {
            confirmButtonText: 'Enviar',
            cancelButtonText: 'Cancelar',
            inputValue: this.contactoEmail || '',
            inputPlaceholder: 'correo@ejemplo.com (opcional)',
            inputValidator: function (val) {
              if (!val) return true
              return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) ? true : 'Ingresa un correo válido'
            }
          }
        )
        correo = res && res.value ? res.value.trim() : ''
      } catch (e) { return }

      this.sendingTest = true
      try {
        // Guardar cambios previos si es editable, para que el PDF refleje lo último
        if (this.isEditable) await this.saveChanges()

        var response = await this.$http.post(
          '/marketing/quotation/proposals/' + this.proposal.id + '/send-test-email',
          { email: correo }
        )
        if (response.data && response.data.success) {
          this.$message.success(response.data.message || 'Correo de prueba enviado')
        } else {
          this.$message.warning((response.data && response.data.message) || 'No se pudo enviar el correo de prueba')
        }
      } catch (e) {
        var status = e.response && e.response.status
        var msg = e.response && e.response.data && e.response.data.message
          ? e.response.data.message : 'Error enviando el correo de prueba'
        // 422 = correo de prueba no configurado: es una guía para el usuario, no un error técnico
        if (status === 422) {
          this.$message.warning(msg)
        } else {
          this.$message.error(msg)
        }
      }
      this.sendingTest = false
    },

    // ══════════════════════════════════════════════════════════════
    // Emitir al cliente (congela snapshot)
    // ══════════════════════════════════════════════════════════════
    async emitToClient() {
      try {
        await this.$confirm(
          'Se generará el PDF final y la cotización quedará CONGELADA (no podrás editarla después). ¿Continuar?',
          'Emitir Cotización al Cliente',
          { confirmButtonText: 'Sí, emitir', cancelButtonText: 'Cancelar', type: 'warning' }
        )
      } catch (e) { return }

      this.emitting = true
      try {
        // Guardar cambios previos
        if (this.isEditable) await this.saveChanges()

        var response = await this.$http.post(
          '/marketing/quotation/proposals/' + this.proposal.id + '/emit'
        )
        this.proposal = response.data.proposal
        this.parseProposalJson()
        this.pdfUrl = response.data.pdf_url
        this.$message.success('Cotización emitida. PDF listo para enviar al cliente.')
        window.open(this.pdfUrl, '_blank')
      } catch (e) {
        var msg = e.response && e.response.data && e.response.data.message
          ? e.response.data.message : 'Error emitiendo la cotización'
        this.$message.error(msg)
      }
      this.emitting = false
    },

    // Cambio de estado (enviada / aprobada / rechazada)
    async changeStatus(newStatus) {
      try {
        var response = await this.$http.put(
          '/marketing/quotation/proposals/' + this.proposal.id + '/status',
          { estado: newStatus }
        )
        this.proposal = response.data.proposal
        this.parseProposalJson()
        this.$message.success('Estado cambiado a: ' + newStatus)
      } catch (e) {
        var msg = e.response && e.response.data && e.response.data.message
          ? e.response.data.message : 'Error cambiando estado'
        this.$message.error(msg)
      }
    },

    // ══════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════
    resetState() {
      this.prequote = null
      this.proposal = null
      this.pdfUrl = ''
      this.notasEncabezado = ['', '', '']
      this.condiciones = {
        costo_servicio: '', forma_pago: '', tiempo_ejecucion: '',
        consideraciones_produccion: '', moneda_texto: '', validez_oferta: '',
        consideraciones_entrega: '', terminos_instalacion: '', garantia_servicio: '',
        servicio_complementario: '', especificacion_upcycling: ''
      }
    },

    parsePrequote(pq) {
      if (pq && pq.items) {
        for (var i = 0; i < pq.items.length; i++) {
          pq.items[i].quantity = parseInt(pq.items[i].quantity) || 0
          pq.items[i].valor_unitario = parseFloat(pq.items[i].valor_unitario) || 0
          pq.items[i].total_item = parseFloat(pq.items[i].total_item) || 0
        }
      }
      return pq
    },

    parseProposalJson() {
      // notas_encabezado
      var notas = []
      if (this.proposal.notas_encabezado) {
        if (typeof this.proposal.notas_encabezado === 'string') {
          try { notas = JSON.parse(this.proposal.notas_encabezado) } catch (e) { notas = [] }
        } else if (Array.isArray(this.proposal.notas_encabezado)) {
          notas = this.proposal.notas_encabezado
        }
      }
      while (notas.length < 3) notas.push('')
      this.notasEncabezado = notas.slice(0, 3)

      // condiciones
      var cond = {}
      if (this.proposal.condiciones) {
        if (typeof this.proposal.condiciones === 'string') {
          try { cond = JSON.parse(this.proposal.condiciones) } catch (e) { cond = {} }
        } else if (typeof this.proposal.condiciones === 'object') {
          cond = this.proposal.condiciones
        }
      }
      var nuevaCond = {}
      var ausentes = []
      for (var ci = 0; ci < this.condicionesSecciones.length; ci++) {
        var ck = this.condicionesSecciones[ci].key
        var cv = cond[ck]
        // Clave AUSENTE = la sección nunca existió en esta cotización (se creó antes de
        // agregarse). Distinto de presente-pero-vacía (el ejecutivo la ocultó a propósito).
        if (cv === undefined) ausentes.push(ck)
        // Retro-compat: entrega vieja podía venir como array de bullets → unir 1 por línea.
        if (Array.isArray(cv)) cv = cv.join('\n')
        nuevaCond[ck] = (cv === null || cv === undefined) ? '' : String(cv)
      }
      this.condiciones = nuevaCond
      this._condSeccionesAusentes = ausentes
      // Cargar los defaults (para el botón "Restaurar") y, cuando estén, auto-traer el
      // default SOLO de las secciones que nunca existieron (cotizaciones viejas).
      var vm = this
      this.loadCondicionesDefaults().then(function () { vm.fillAusentesConDefault() })

      // R5: el Destinatario toma por defecto el nombre y cargo del contacto del
      // requerimiento cuando vienen vacíos (no pisa lo que el usuario ya editó).
      if (this.proposal) {
        if (!this.proposal.destinatario_nombre && this.contactoNombre) {
          this.proposal.destinatario_nombre = this.contactoNombre
        }
        if (!this.proposal.destinatario_cargo && this.contactoCargo) {
          this.proposal.destinatario_cargo = this.contactoCargo
        }
      }
    },

    safeParse(v) {
      return parseFloat(v) || 0
    },

    /**
     * URL de la miniatura del ítem (solo lectura). Servida por el endpoint GET
     * del módulo Marketing. Retorna '' si el ítem no tiene imagen.
     */
    itemImageUrl(item) {
      if (!item || !item.id) return ''
      if (!item.product_reference_image && !item.has_image) return ''
      return '/marketing/quotation/prequote-items/' + item.id + '/reference-image'
    },

    estadoTagType(estado) {
      var map = {
        borrador:           'info',
        emitida:            'primary',
        enviada_cliente:    'warning',
        aprobada_cliente:   'success',
        rechazada_cliente:  'danger'
      }
      return map[estado] || 'info'
    },

    userLabel(u) {
      var full = (u.name || '') + ' ' + (u.paternal_lastname || '') + ' ' + (u.maternal_lastname || '')
      return full.trim() + (u.email ? ' (' + u.email + ')' : '')
    },

    // COM-02 · Carga (1 vez, con promesa cacheada) los textos por defecto de las 11
    // condiciones desde la config del cotizador, para el botón "Restaurar por defecto".
    // validez usa una clave distinta; el resto es condiciones_default_<clave>.
    // Devuelve la promesa (resuelve cuando ya cargó) para poder encadenar el auto-fill.
    loadCondicionesDefaults() {
      if (this._condDefaultsPromise) return this._condDefaultsPromise
      var vm = this
      this._condDefaultsPromise = this.$http.get('/marketing/quotation/config').then(function (resp) {
        var d = resp.data || {}
        // El endpoint devuelve { ok, configs: [{ config_key, config_value }, ...] } → aplanar.
        var flat = {}
        var list = d.configs || d.config || d.data
        if (Array.isArray(list)) {
          for (var j = 0; j < list.length; j++) {
            if (list[j] && list[j].config_key !== undefined) flat[list[j].config_key] = list[j].config_value
          }
        } else if (list && typeof list === 'object') {
          flat = list
        }
        var map = {}
        for (var i = 0; i < vm.condicionesSecciones.length; i++) {
          var key = vm.condicionesSecciones[i].key
          var cfgKey = (key === 'validez_oferta') ? 'cotizacion_validez_default' : ('condiciones_default_' + key)
          var v = flat[cfgKey]
          if (Array.isArray(v)) v = v.join('\n')
          if (v !== null && v !== undefined && String(v).trim() !== '') map[key] = String(v)
        }
        vm.condicionesDefaults = map
      }).catch(function (e) {
        // Silencioso: sin defaults, el botón "Restaurar por defecto" no aparece.
        vm._condDefaultsPromise = null  // permitir reintento
      })
      return this._condDefaultsPromise
    },

    // Auto-trae el default SOLO de las secciones que nunca existieron en la cotización
    // (clave ausente = creada antes de agregarse la sección). NO toca las presentes-vacías
    // (esas el ejecutivo las ocultó a propósito). Solo en borrador editable.
    fillAusentesConDefault() {
      if (!this._condSeccionesAusentes || !this._condSeccionesAusentes.length) return
      if (!this.isEditable) return
      for (var i = 0; i < this._condSeccionesAusentes.length; i++) {
        var k = this._condSeccionesAusentes[i]
        if (this.condicionesDefaults[k] && String(this.condiciones[k] || '').trim() === '') {
          this.$set(this.condiciones, k, this.condicionesDefaults[k])
        }
      }
    },

    restoreCondicionDefault(key) {
      if (this.condicionesDefaults[key] !== undefined) {
        this.$set(this.condiciones, key, this.condicionesDefaults[key])
      }
    }
  }
}
</script>

<style scoped>
.tab-cotizacion-final {
  padding: 4px 2px;
}
.cf-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  background: #f5f7fa;
  border-radius: 4px;
  margin-bottom: 10px;
}
.cf-num {
  margin-left: 6px;
  font-family: monospace;
  font-weight: bold;
  color: #c00;
}
.cf-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #606266;
  margin-top: 8px;
  margin-bottom: 4px;
}
.cf-label[title] {
  cursor: help;
}
.cf-label .cf-tip {
  color: #909399;
  font-size: 13px;
  margin-left: 2px;
  font-weight: normal;
  vertical-align: middle;
}
.cf-totales {
  max-width: 380px;
  margin-left: auto;
}
.cf-totales-row {
  display: flex;
  justify-content: space-between;
  padding: 4px 8px;
  border-bottom: 1px dotted #ddd;
  font-size: 13px;
}
.cf-totales-grand {
  background: #409eff;
  color: white;
  font-size: 15px;
  padding: 6px 8px;
  border-radius: 3px;
  margin-top: 4px;
}
.cf-actions {
  margin-top: 14px;
  padding-top: 10px;
  border-top: 1px solid #ebeef5;
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

/* Miniatura de foto del ítem (solo lectura) */
.cf-foto-thumb {
  width: 40px; height: 40px; object-fit: cover;
  border-radius: 4px; border: 1px solid #ddd; background: #fafafa;
  vertical-align: middle;
}
.cf-foto-empty { color: #c0c4cc; font-size: 18px; }
</style>
