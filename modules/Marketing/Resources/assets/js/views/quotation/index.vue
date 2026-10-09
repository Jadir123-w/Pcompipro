<template>
  <div class="cotizador-standalone">

    <!-- ═══════════════════════════════════════════════════════════════
         Header
         ═══════════════════════════════════════════════════════════════ -->
    <div class="page-header pr-0">
      <h2>
        <i class="fas fa-calculator"></i>
        Cotizador Inteligente (COM-02)
      </h2>
      <div class="right-wrapper pull-right">
        <el-button type="primary" icon="el-icon-plus" @click="showGenerateDialog = true">
          Nueva Precotización
        </el-button>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         Tabs: Bandeja + Configuración
         ═══════════════════════════════════════════════════════════════ -->
    <el-tabs v-model="activeTab" type="card">

      <!-- ═════ Bandeja ═════ -->
      <el-tab-pane label="Precotizaciones" name="prequotes">
        <div class="card mb-0">
          <div class="card-body">

            <!-- Filtros -->
            <el-row :gutter="10" class="mb-3">
              <el-col :span="5">
                <el-select v-model="filters.estado" clearable placeholder="Estado" @change="loadPrequotes">
                  <el-option v-for="e in estadoOptions" :key="e.value" :label="e.label" :value="e.value"></el-option>
                </el-select>
              </el-col>
              <el-col :span="5">
                <el-select v-model="filters.origen" clearable placeholder="Origen" @change="loadPrequotes">
                  <el-option label="Todas" value=""></el-option>
                  <el-option label="Con requerimiento" value="con_req"></el-option>
                  <el-option label="Sin requerimiento (standalone)" value="sin_req"></el-option>
                </el-select>
              </el-col>
              <el-col :span="4">
                <el-button icon="el-icon-refresh" @click="loadPrequotes">Recargar</el-button>
              </el-col>
            </el-row>

            <!-- Tabla -->
            <el-table :data="prequotesFiltradas" stripe border v-loading="loadingList"
                      empty-text="No hay precotizaciones" size="small">
              <el-table-column prop="id" label="ID" width="60"></el-table-column>
              <el-table-column prop="titulo" label="Título" min-width="200"></el-table-column>
              <el-table-column label="Origen" width="120">
                <template slot-scope="scope">
                  <el-tag v-if="scope.row.requerimiento_id" size="mini" type="info">
                    Req #{{ scope.row.requerimiento_id }}
                  </el-tag>
                  <el-tag v-else size="mini" type="warning">Standalone</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="Empresa" width="160">
                <template slot-scope="scope">
                  {{ scope.row.empresa ? scope.row.empresa.razon_social : '-' }}
                </template>
              </el-table-column>
              <el-table-column label="Items" width="60" align="center">
                <template slot-scope="scope">
                  <el-tag size="mini">{{ scope.row.items ? scope.row.items.length : 0 }}</el-tag>
                </template>
              </el-table-column>
              <el-table-column label="Total" width="110" align="right">
                <template slot-scope="scope">
                  S/ {{ parseFloat(scope.row.total).toFixed(2) }}
                </template>
              </el-table-column>
              <el-table-column label="Estado" width="130">
                <template slot-scope="scope">
                  <el-tag :type="estadoTagType(scope.row.estado)" size="small">
                    {{ scope.row.estado }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column prop="created_at" label="Fecha" width="100">
                <template slot-scope="scope">
                  {{ scope.row.created_at ? scope.row.created_at.substring(0, 10) : '' }}
                </template>
              </el-table-column>
              <el-table-column label="Acciones" width="110" align="center">
                <template slot-scope="scope">
                  <el-button size="mini" type="primary" icon="el-icon-edit" @click="openEditor(scope.row.id)">
                    Editar
                  </el-button>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </div>
      </el-tab-pane>

      <!-- ═════ Configuración ═════ -->
      <el-tab-pane label="Configuración" name="config">
        <quotation-config></quotation-config>
      </el-tab-pane>

    </el-tabs>

    <!-- ═══════════════════════════════════════════════════════════════
         Dialog: Generar nueva precotización
         ═══════════════════════════════════════════════════════════════ -->
    <el-dialog title="Generar Precotización con IA" :visible.sync="showGenerateDialog"
               width="650px" :close-on-click-modal="false">
      <el-form :model="generateForm" label-position="top">
        <el-form-item label="Texto del requerimiento" required>
          <el-input type="textarea" v-model="generateForm.text" :rows="5"
                    placeholder="Ej: Cotizar 200 libretas ecológicas A5 con logo y 150 lapiceros metálicos...">
          </el-input>
          <div style="font-size:12px; color:#888; margin-top:4px;">
            Describe lo que el cliente solicita. La IA detectará los productos, cantidades y personalización.
          </div>
        </el-form-item>

        <el-form-item label="Título (opcional)">
          <el-input v-model="generateForm.titulo"
                    placeholder="Se genera automáticamente si se deja vacío"></el-input>
        </el-form-item>

        <el-form-item label="Empresa cliente (opcional)">
          <el-input v-model="generateForm.empresa_nombre"
                    placeholder="Nombre del cliente para futura referencia. Puedes vincular después."></el-input>
          <div style="font-size:12px; color:#888; margin-top:4px;">
            Este campo solo queda como nota. Puedes asignar la empresa desde el editor.
          </div>
        </el-form-item>
      </el-form>

      <div slot="footer">
        <el-button @click="showGenerateDialog = false">Cancelar</el-button>
        <el-button type="primary" @click="generatePrequote" :loading="generating" icon="el-icon-magic-stick">
          Generar con IA
        </el-button>
      </div>
    </el-dialog>

    <!-- ═══════════════════════════════════════════════════════════════
         Dialog: Editor de precotización (con los 3 tabs reutilizados)
         ═══════════════════════════════════════════════════════════════ -->
    <el-dialog :title="editorTitle" :visible.sync="showEditor" width="95%" top="2vh"
               :close-on-click-modal="false"
               custom-class="cotizador-editor-dialog"
               @close="onEditorClose">
      <div v-if="editorPrequoteId">
        <el-tabs v-model="editorActiveTab" type="card">

          <el-tab-pane label="Pre-Cotización" name="pre-cotizacion">
            <tab-pre-cotizacion v-if="editorActiveTab === 'pre-cotizacion'"
                                :prequote-id="editorPrequoteId"
                                @precotizacion-added="onEditorUpdated">
            </tab-pre-cotizacion>
          </el-tab-pane>

          <el-tab-pane label="Proveedores" name="proveedores">
            <tab-proveedores v-if="editorActiveTab === 'proveedores'"
                             :prequote-id="editorPrequoteId">
            </tab-proveedores>
          </el-tab-pane>

          <el-tab-pane label="Cotización Final" name="cotizacion-final">
            <tab-cotizacion-final v-if="editorActiveTab === 'cotizacion-final'"
                                  :prequote-id="editorPrequoteId">
            </tab-cotizacion-final>
          </el-tab-pane>

        </el-tabs>
      </div>

      <div slot="footer">
        <el-button @click="showEditor = false">Cerrar</el-button>
      </div>
    </el-dialog>

  </div>
</template>

<script>
import QuotationConfig from '../../components/quotation/QuotationConfig.vue'
import TabPreCotizacion from '../../../../../../Comercial/Resources/assets/js/view/requerimientos/TabPreCotizacion.vue'
import TabProveedores from '../../../../../../Comercial/Resources/assets/js/view/requerimientos/TabProveedores.vue'
import TabCotizacionFinal from '../../../../../../Comercial/Resources/assets/js/view/requerimientos/TabCotizacionFinal.vue'

export default {
  components: {
    QuotationConfig,
    TabPreCotizacion,
    TabProveedores,
    TabCotizacionFinal
  },

  data() {
    return {
      activeTab: 'prequotes',

      // Bandeja
      prequotes: [],
      loadingList: false,
      filters: { estado: '', origen: '' },
      estadoOptions: [
        { value: '', label: 'Todos' },
        { value: 'borrador', label: 'Borrador' },
        { value: 'en_revision', label: 'En revisión' },
        { value: 'ajustada', label: 'Ajustada' },
        { value: 'validada', label: 'Validada' },
        { value: 'aprobada', label: 'Aprobada' },
        { value: 'propuesta_emitida', label: 'Propuesta emitida' },
        { value: 'rechazada', label: 'Rechazada' }
      ],

      // Generar
      showGenerateDialog: false,
      generating: false,
      generateForm: { text: '', titulo: '', empresa_nombre: '' },

      // Editor
      showEditor: false,
      editorPrequoteId: null,
      editorActiveTab: 'pre-cotizacion'
    }
  },

  computed: {
    editorTitle() {
      return this.editorPrequoteId
        ? 'Editor de Precotización #' + this.editorPrequoteId
        : 'Editor de Precotización'
    },
    prequotesFiltradas() {
      if (!this.filters.origen) return this.prequotes
      if (this.filters.origen === 'con_req') {
        return this.prequotes.filter(p => p.requerimiento_id)
      }
      if (this.filters.origen === 'sin_req') {
        return this.prequotes.filter(p => !p.requerimiento_id)
      }
      return this.prequotes
    }
  },

  created() {
    this.loadPrequotes()
  },

  methods: {
    async loadPrequotes() {
      this.loadingList = true
      try {
        var url = '/marketing/quotation/prequotes'
        if (this.filters.estado) url += '?estado=' + this.filters.estado
        var response = await this.$http.get(url)
        this.prequotes = response.data.prequotes.data || response.data.prequotes || []
      } catch (e) {
        this.$message.error('Error cargando precotizaciones')
      }
      this.loadingList = false
    },

    async generatePrequote() {
      if (!this.generateForm.text.trim()) {
        this.$message.warning('Ingresa el texto del requerimiento')
        return
      }
      this.generating = true
      try {
        var payload = {
          text: this.generateForm.text,
          titulo: this.generateForm.titulo || null
        }
        var response = await this.$http.post('/marketing/quotation/generate', payload)
        var newPrequote = response.data.prequote
        this.$message.success(
          'Precotización generada con ' +
          (newPrequote.items ? newPrequote.items.length : 0) + ' items'
        )
        this.showGenerateDialog = false
        this.generateForm = { text: '', titulo: '', empresa_nombre: '' }
        this.loadPrequotes()
        // Abrir editor
        this.openEditor(newPrequote.id)
      } catch (e) {
        var msg = e.response && e.response.data && e.response.data.message
          ? e.response.data.message : 'Error generando precotización'
        this.$message.error(msg)
      }
      this.generating = false
    },

    openEditor(prequoteId) {
      this.editorPrequoteId = prequoteId
      this.editorActiveTab = 'pre-cotizacion'
      this.showEditor = true
    },

    onEditorClose() {
      this.editorPrequoteId = null
      // Refrescar lista al cerrar por si hubo cambios
      this.loadPrequotes()
    },

    onEditorUpdated() {
      this.loadPrequotes()
    },

    estadoTagType(estado) {
      var map = {
        borrador: 'info',
        en_revision: 'warning',
        ajustada: 'warning',
        validada: 'primary',
        aprobada: 'success',
        rechazada: 'danger',
        propuesta_emitida: 'success',
        convertida_en_proyecto: 'success'
      }
      return map[estado] || 'info'
    }
  }
}
</script>

<style scoped>
.cotizador-standalone { padding: 0 4px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.page-header h2 { margin: 0; font-size: 20px; }
</style>

<!-- Estilos NO scoped: forzar opacity 1 del dialog wrapper del editor —
     evita un bug de transición de Element UI con jQuery que deja el wrapper
     con opacity 0 permanente. -->
<style>
.el-dialog__wrapper:has(.cotizador-editor-dialog) {
  opacity: 1 !important;
}
.cotizador-editor-dialog {
  opacity: 1 !important;
}
</style>
