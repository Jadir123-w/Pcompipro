<template>
  <div v-loading="loading" class="tab-proveedores">

    <!-- ═══════════════════════════════════════════════════════════════
         ESTADO 1: No hay precotización aún
         ═══════════════════════════════════════════════════════════════ -->
    <div v-if="!prequote && !loading" style="text-align:center; padding:30px 0;">
      <i class="el-icon-shopping-cart-2" style="font-size:48px; color:#c0c4cc;"></i>
      <p style="color:#888; margin:15px 0;">
        Para analizar proveedores primero debes tener una <strong>Pre-Cotización</strong>.
      </p>
      <p style="color:#aaa; font-size:12px;">
        Ve a la pestaña "Pre-Cotización" y genera la precotización con IA.
      </p>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         ESTADO 2: Prequote cargada — tabla de items + acciones IA
         ═══════════════════════════════════════════════════════════════ -->
    <div v-if="prequote">

      <el-alert
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom:10px;">
        <template slot="title">
          Análisis IA de Proveedores — Por cada ítem, haz click en "Sugerir proveedores" y la IA
          propondrá los 3 mejores candidatos del catálogo de COMPIPRO con costo y tiempo estimado.
        </template>
      </el-alert>

      <!-- Tabla de ítems (sin líneas de comisión: no llevan proveedor) -->
      <el-table :data="itemsCotizables" stripe border size="mini" class="tab-prov-items">
        <el-table-column prop="item_order" label="#" width="40" align="center"></el-table-column>
        <el-table-column prop="product_name" label="Producto" min-width="140"></el-table-column>
        <el-table-column label="Cant." width="60" align="center">
          <template slot-scope="scope">{{ scope.row.quantity }}</template>
        </el-table-column>
        <el-table-column label="Proveedor actual" min-width="150">
          <template slot="header">
            <span class="col-tip" title="Proveedor asignado actualmente a este ítem (si ya se eligió uno).">Proveedor actual <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <span v-if="scope.row.supplier_name" style="font-size:12px;">
              {{ scope.row.supplier_name }}
            </span>
            <el-tag v-else type="info" size="mini">No asignado</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="Costo base" width="100" align="right">
          <template slot="header">
            <span class="col-tip" title="Costo unitario del proveedor asignado al ítem.">Costo base <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            S/ {{ parseFloat(scope.row.costo_base || 0).toFixed(2) }}
          </template>
        </el-table-column>
        <!-- Agosto 2026 (punto 9): antes decía "Tiempo entrega". Es el plazo que
             tarda el PROVEEDOR en producir, que no es lo mismo que el plazo que se
             le promete al cliente. Ese segundo plazo vive en el Proyecto (punto 20)
             y arranca heredando este valor, pero se ajusta por separado. -->
        <el-table-column label="Tiempo de Producción" width="150" align="center">
          <template slot="header">
            <span class="col-tip" title="Tiempo que tarda el proveedor en producir (texto libre, p. ej. '15 días útiles' o 'Mié 02 Abril'). No es el plazo que se le promete al cliente: ese se define en el Proyecto.">Tiempo de Producción <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            {{ scope.row.tiempo_estimado_dias || '—' }}
          </template>
        </el-table-column>
        <el-table-column label="Acciones" width="280" align="center">
          <template slot-scope="scope">
            <el-button size="mini" type="primary" icon="el-icon-magic-stick" plain
                       :loading="loadingItemId === scope.row.id"
                       @click="loadSuggestions(scope.row)">
              Sugerir IA
            </el-button>
            <el-button size="mini" type="success" icon="el-icon-user" plain
                       @click="openManual(scope.row)">
              Asignar manual
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- ────────────────────────────────────────────────────────────
           DIALOG DE SUGERENCIAS
           ──────────────────────────────────────────────────────────── -->
      <el-dialog
        title="Sugerencia IA de Proveedores"
        :visible.sync="showDialog"
        width="85%"
        top="6vh"
        :close-on-click-modal="false">

        <div v-loading="suggestionsLoading">
          <!-- Ítem contexto -->
          <div v-if="currentItem" class="tab-prov-item-context">
            <strong>Ítem:</strong>
            <span style="margin-left:6px;">
              {{ currentItem.quantity }} × {{ currentItem.product_name }}
            </span>
            <span v-if="currentItem.material" style="color:#666; margin-left:10px;">
              · Material: {{ currentItem.material }}
            </span>
            <span v-if="currentItem.personalizacion" style="color:#666; margin-left:10px;">
              · {{ currentItem.personalizacion }}
            </span>
          </div>

          <!-- Summary IA -->
          <el-alert v-if="aiSummary" :title="aiSummary" type="success"
                    :closable="false" show-icon style="margin:8px 0;">
            <template slot="default" v-if="aiRationale">
              <div style="font-size:12px; color:#555; margin-top:4px;">
                <strong>Mejor candidato:</strong> {{ aiRationale }}
              </div>
            </template>
          </el-alert>

          <!-- Warning fallback -->
          <el-alert v-if="aiFallback"
                    title="IA no disponible — sugerencias básicas de respaldo"
                    type="warning" :closable="false" show-icon style="margin:8px 0;">
          </el-alert>

          <!-- Cards de candidatos -->
          <el-row :gutter="12" v-if="suggestions.length > 0">
            <el-col :span="8" v-for="(s, idx) in suggestions" :key="'sug-' + idx">
              <el-card shadow="hover" class="tab-prov-card"
                       :class="{ 'tab-prov-card-best': idx === 0 }">
                <!-- Badge best -->
                <div v-if="idx === 0" class="tab-prov-badge">⭐ Mejor match</div>

                <div class="tab-prov-rank">#{{ idx + 1 }}</div>

                <h4 class="tab-prov-name">{{ s.supplier_name }}</h4>

                <div class="tab-prov-category">
                  <el-tag size="mini" type="info">{{ s.supplier_category || 'Sin categoría' }}</el-tag>
                </div>

                <el-divider></el-divider>

                <div class="tab-prov-metric">
                  <span class="label">Costo unit:</span>
                  <strong>S/ {{ parseFloat(s.costo_unitario_estimado || 0).toFixed(2) }}</strong>
                </div>
                <div class="tab-prov-metric">
                  <!-- Agosto 2026 (punto 9): es el plazo de producción del proveedor. -->
                  <span class="label">Producción:</span>
                  <strong>{{ s.tiempo_estimado_dias || '—' }}</strong>
                </div>
                <div class="tab-prov-metric">
                  <span class="label">Total est:</span>
                  <strong>S/ {{ (parseFloat(s.costo_unitario_estimado || 0) * currentItem.quantity).toFixed(2) }}</strong>
                </div>

                <el-divider></el-divider>

                <div class="tab-prov-confianza">
                  <span class="label">Confianza IA:</span>
                  <el-progress :percentage="s.confianza" :color="confidenceColor(s.confianza)"
                               :stroke-width="8" :format="p => p + '%'">
                  </el-progress>
                </div>

                <div class="tab-prov-justif">
                  {{ s.justificacion }}
                </div>

                <!-- Alertas -->
                <div v-if="s.alertas && s.alertas.length > 0" class="tab-prov-alertas">
                  <div v-for="(a, ai) in s.alertas" :key="'al-' + ai"
                       class="tab-prov-alerta">
                    ⚠ {{ a }}
                  </div>
                </div>

                <!-- Botón elegir -->
                <el-button type="success" size="small" icon="el-icon-check"
                           style="width:100%; margin-top:10px;"
                           :loading="applying === s.supplier_id"
                           @click="applySupplier(s)">
                  Elegir este proveedor
                </el-button>
              </el-card>
            </el-col>
          </el-row>

          <!-- Empty state -->
          <div v-if="!suggestionsLoading && suggestions.length === 0"
               style="text-align:center; padding:30px 0; color:#888;">
            No se pudieron generar sugerencias. Revisa que haya proveedores activos en el catálogo.
          </div>
        </div>

        <div slot="footer">
          <el-button size="small" @click="showDialog = false">Cerrar</el-button>
          <el-button size="small" type="primary" icon="el-icon-refresh"
                     :loading="suggestionsLoading" @click="reloadSuggestions">
            Regenerar con IA
          </el-button>
        </div>
      </el-dialog>

      <!-- ────────────────────────────────────────────────────────────
           DIALOG: ASIGNACION MANUAL DE PROVEEDOR
           (cuando la IA no basta: se elige un proveedor del catalogo)
           ──────────────────────────────────────────────────────────── -->
      <el-dialog title="Asignar proveedor manualmente" :visible.sync="showManual"
                 width="520px" top="8vh" :close-on-click-modal="false" append-to-body>
        <div v-if="manualItem" class="tab-prov-item-context" style="margin-bottom:12px;">
          <strong>Ítem:</strong>
          <span style="margin-left:6px;">{{ manualItem.quantity }} × {{ manualItem.product_name }}</span>
        </div>
        <el-form label-position="top" size="small">
          <el-form-item label="Proveedor">
            <!-- Agosto 2026 (punto 8): la información va jerarquizada. El Nombre
                 Comercial destacado, porque es el que el usuario reconoce más
                 rápido; debajo, en menor tamaño, la Razón Social y el RUC para
                 confirmar que eligió al proveedor correcto. -->
            <el-select v-model="manualForm.supplier_id" filterable remote
                       :remote-method="searchManualProveedores" :loading="manualSearchLoading"
                       placeholder="Buscar por nombre comercial, razón social o RUC" style="width:100%"
                       @change="onManualProveedorChange">
              <!-- Estilos en línea a propósito: el desplegable de Element UI se
                   monta fuera del componente (popper-append-to-body), así que un
                   <style scoped> no lo alcanzaría. -->
              <el-option v-for="p in manualOptions" :key="p.id"
                         :label="etiquetaProveedor(p)" :value="p.id"
                         style="height:auto; line-height:1.3; padding-top:6px; padding-bottom:6px;">
                <div style="font-weight:600; font-size:13px; color:#303133;">{{ nombreProveedor(p) }}</div>
                <div style="font-size:11px; color:#909399;">{{ truncarRazonSocial(p.razon_social) }}</div>
                <div style="font-size:11px; color:#909399;">{{ p.ruc || 'Sin RUC' }}</div>
              </el-option>
            </el-select>
          </el-form-item>
          <el-form-item label="Costo unitario (S/)">
            <el-input-number v-model="manualForm.costo_unitario_estimado" :min="0" :precision="2"
                             :controls="false" style="width:100%" />
          </el-form-item>
          <!-- Agosto 2026 (punto 9): plazo de producción del proveedor, no el que
               se le promete al cliente. -->
          <el-form-item label="Tiempo de Producción">
            <el-input v-model="manualForm.tiempo_estimado_dias"
                      placeholder="Ej. 15 días útiles, Mié 02 Abril..." />
          </el-form-item>
        </el-form>
        <div slot="footer">
          <el-button size="small" @click="showManual = false">Cancelar</el-button>
          <el-button size="small" type="primary" :loading="manualApplying" @click="applyManual">
            Asignar proveedor
          </el-button>
        </div>
      </el-dialog>

    </div>
  </div>
</template>

<script>
export default {
  name: 'TabProveedores',
  props: {
    requerimientoId: { type: [Number, String], default: null },
    // Modo standalone: si viene prequoteId, carga directamente la prequote
    prequoteId: { type: [Number, String], default: null }
  },
  data() {
    return {
      loading: false,
      suggestionsLoading: false,
      prequote: null,

      showDialog: false,
      currentItem: null,
      loadingItemId: null,

      suggestions: [],
      aiSummary: '',
      aiRationale: '',
      aiFallback: false,

      applying: null,  // supplier_id en curso

      // Asignacion manual de proveedor (cuando la IA no basta)
      showManual: false,
      manualItem: null,
      manualForm: { supplier_id: null, supplier_name: '', costo_unitario_estimado: 0, tiempo_estimado_dias: '' },
      manualOptions: [],
      manualSearchLoading: false,
      manualApplying: false
    }
  },
  computed: {
    // La comisión no maneja proveedores: se excluye de la tabla.
    itemsCotizables() {
      if (!this.prequote || !Array.isArray(this.prequote.items)) return []
      return this.prequote.items.filter(function (it) {
        return String((it && it.tipo_linea) || '').toLowerCase() !== 'comision'
      })
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
    // ── Presentación del proveedor en el buscador (agosto 2026, punto 8) ──

    // Nombre destacado: el comercial, que es el que el usuario reconoce. Si el
    // proveedor no lo tiene cargado, se usa la razón social para no dejar la
    // línea principal vacía.
    nombreProveedor(p) {
      var comercial = (p.nombre_comercial || '').toString().trim()
      return comercial !== '' ? comercial : (p.razon_social || '').toString().trim()
    },

    // Las razones sociales largas se cortan a 45 caracteres con puntos
    // suspensivos, para que la lista no se desarme.
    truncarRazonSocial(valor) {
      var texto = (valor || '').toString().trim()
      var LIMITE = 45
      return texto.length > LIMITE ? texto.slice(0, LIMITE) + '...' : texto
    },

    // Lo que se ve una vez elegido el proveedor (el desplegable cerrado no
    // muestra el contenido de varias líneas, solo esta etiqueta).
    etiquetaProveedor(p) {
      var nombre = this.nombreProveedor(p)
      var ruc = (p.ruc || '').toString().trim()
      return ruc !== '' ? nombre + ' (' + ruc + ')' : nombre
    },

    async loadPrequote() {
      this.loading = true
      try {
        var response = await this.$http.get('/marketing/quotation/prequotes', {
          params: { requerimiento_id: this.requerimientoId }
        })
        var list = response.data.prequotes.data || response.data.prequotes || []

        if (list.length === 0) {
          this.prequote = null
          this.loading = false
          return
        }

        var pqDetail = await this.$http.get('/marketing/quotation/prequotes/' + list[0].id)
        this.prequote = this.parsePrequote(pqDetail.data.prequote)
      } catch (e) {
        this.prequote = null
      }
      this.loading = false
    },

    // Modo standalone: cargar directamente por prequote_id
    async loadPrequoteById(id) {
      this.loading = true
      try {
        var pqDetail = await this.$http.get('/marketing/quotation/prequotes/' + id)
        this.prequote = this.parsePrequote(pqDetail.data.prequote)
      } catch (e) {
        this.prequote = null
      }
      this.loading = false
    },

    async loadSuggestions(item) {
      this.currentItem = item
      this.loadingItemId = item.id
      this.suggestions = []
      this.aiSummary = ''
      this.aiRationale = ''
      this.aiFallback = false
      this.showDialog = true
      this.suggestionsLoading = true

      try {
        var response = await this.$http.get(
          '/marketing/quotation/prequotes/' + this.prequote.id +
          '/items/' + item.id + '/supplier-suggestions'
        )
        this.suggestions  = response.data.suggestions || []
        this.aiSummary    = response.data.summary || ''
        this.aiRationale  = response.data.rationale || ''
        this.aiFallback   = !!response.data.ai_fallback
      } catch (e) {
        var msg = e.response && e.response.data && e.response.data.message
          ? e.response.data.message : 'Error consultando la IA'
        this.$message.error(msg)
      }
      this.suggestionsLoading = false
      this.loadingItemId = null
    },

    async reloadSuggestions() {
      if (this.currentItem) {
        await this.loadSuggestions(this.currentItem)
      }
    },

    async applySupplier(suggestion) {
      try {
        await this.$confirm(
          'Se asignará "' + suggestion.supplier_name + '" como proveedor del ítem "' +
          this.currentItem.product_name + '" y se recalcularán los totales de la precotización. ¿Continuar?',
          'Aplicar proveedor',
          { confirmButtonText: 'Sí, aplicar', cancelButtonText: 'Cancelar', type: 'info' }
        )
      } catch (e) { return }

      this.applying = suggestion.supplier_id
      try {
        var response = await this.$http.post(
          '/marketing/quotation/prequotes/' + this.prequote.id +
          '/items/' + this.currentItem.id + '/apply-supplier',
          {
            supplier_id:             suggestion.supplier_id,
            supplier_name:           suggestion.supplier_name,
            costo_unitario_estimado: suggestion.costo_unitario_estimado,
            tiempo_estimado_dias:    suggestion.tiempo_estimado_dias
          }
        )
        this.prequote = this.parsePrequote(response.data.prequote)
        this.$message.success('Proveedor aplicado. Totales recalculados.')
        this.showDialog = false
      } catch (e) {
        var msg = e.response && e.response.data && e.response.data.message
          ? e.response.data.message : 'Error aplicando el proveedor'
        this.$message.error(msg)
      }
      this.applying = null
    },

    parsePrequote(pq) {
      if (pq && pq.items) {
        for (var i = 0; i < pq.items.length; i++) {
          pq.items[i].quantity = parseInt(pq.items[i].quantity) || 0
          pq.items[i].costo_base = parseFloat(pq.items[i].costo_base) || 0
          // tiempo_estimado_dias ahora es texto libre (COM08): se muestra tal cual, sin parseInt.
          pq.items[i].tiempo_estimado_dias = pq.items[i].tiempo_estimado_dias != null
            ? String(pq.items[i].tiempo_estimado_dias) : ''
        }
      }
      return pq
    },

    // ══════════════════════════════════════════════════════════════
    // Asignacion MANUAL de proveedor (reusa el endpoint apply-supplier)
    // ══════════════════════════════════════════════════════════════
    openManual(item) {
      this.manualItem = item
      this.manualForm = {
        supplier_id: null,
        supplier_name: '',
        costo_unitario_estimado: parseFloat(item.costo_base) || 0,
        tiempo_estimado_dias: item.tiempo_estimado_dias || ''
      }
      this.manualOptions = []
      this.showManual = true
    },
    searchManualProveedores(query) {
      var self = this
      if (!query || query.length < 2) return
      this.manualSearchLoading = true
      this.$http.get('/comercial/proyectos/search-proveedores', { params: { q: query } })
        .then(function (response) {
          var data = response.data
          self.manualOptions = (data && data.data) ? data.data : []
        })
        .catch(function () { self.manualOptions = [] })
        .finally(function () { self.manualSearchLoading = false })
    },
    onManualProveedorChange(proveedorId) {
      var p = (this.manualOptions || []).find(function (x) { return x.id === proveedorId })
      this.manualForm.supplier_name = p ? p.razon_social : ''
    },
    async applyManual() {
      if (!this.manualForm.supplier_id || !this.manualForm.supplier_name) {
        this.$message.warning('Selecciona un proveedor de la lista.')
        return
      }
      this.manualApplying = true
      try {
        var response = await this.$http.post(
          '/marketing/quotation/prequotes/' + this.prequote.id +
          '/items/' + this.manualItem.id + '/apply-supplier',
          {
            supplier_id:             this.manualForm.supplier_id,
            supplier_name:           this.manualForm.supplier_name,
            costo_unitario_estimado: this.manualForm.costo_unitario_estimado,
            tiempo_estimado_dias:    this.manualForm.tiempo_estimado_dias
          }
        )
        this.prequote = this.parsePrequote(response.data.prequote)
        this.$message.success('Proveedor asignado. Totales recalculados.')
        this.showManual = false
      } catch (e) {
        var msg = e.response && e.response.data && e.response.data.message
          ? e.response.data.message : 'Error asignando el proveedor'
        this.$message.error(msg)
      }
      this.manualApplying = false
    },

    confidenceColor(pct) {
      if (pct >= 85) return '#67c23a'  // verde
      if (pct >= 70) return '#409eff'  // azul
      if (pct >= 55) return '#e6a23c'  // ámbar
      return '#f56c6c'                 // rojo
    }
  }
}
</script>

<style scoped>
.tab-proveedores {
  padding: 4px 2px;
}
.tab-prov-items {
  margin-top: 6px;
}
.tab-prov-item-context {
  padding: 8px 12px;
  background: #f5f7fa;
  border-radius: 4px;
  margin-bottom: 8px;
  font-size: 13px;
}
.tab-prov-card {
  position: relative;
  min-height: 340px;
  padding-top: 4px;
}
.tab-prov-card-best {
  border: 2px solid #67c23a;
  box-shadow: 0 0 10px rgba(103, 194, 58, 0.3);
}
.tab-prov-badge {
  position: absolute;
  top: 6px;
  right: 6px;
  background: #67c23a;
  color: white;
  padding: 2px 8px;
  border-radius: 3px;
  font-size: 11px;
  font-weight: bold;
}
.tab-prov-rank {
  position: absolute;
  top: 8px;
  left: 10px;
  font-size: 20px;
  font-weight: bold;
  color: #909399;
}
.tab-prov-name {
  margin: 24px 0 4px 0;
  font-size: 14px;
  line-height: 1.35;
  min-height: 38px;
}
.tab-prov-category {
  margin-bottom: 4px;
}
.tab-prov-metric {
  display: flex;
  justify-content: space-between;
  padding: 3px 0;
  font-size: 13px;
}
.tab-prov-metric .label {
  color: #666;
}
.tab-prov-confianza {
  margin: 8px 0;
}
.tab-prov-confianza .label {
  font-size: 12px;
  color: #666;
  display: block;
  margin-bottom: 4px;
}
.tab-prov-justif {
  font-size: 12px;
  color: #555;
  line-height: 1.45;
  padding: 6px 0;
  min-height: 40px;
}
.tab-prov-alertas {
  margin-top: 8px;
  border-left: 3px solid #e6a23c;
  padding: 6px 8px;
  background: #fdf6ec;
}
.tab-prov-alerta {
  font-size: 11px;
  color: #c47913;
  margin-bottom: 2px;
}
/* Tooltips de ayuda en headers de tabla */
.col-tip { cursor: help; }
.col-tip .el-icon-info { color: #909399; font-size: 12px; margin-left: 2px; }
</style>
