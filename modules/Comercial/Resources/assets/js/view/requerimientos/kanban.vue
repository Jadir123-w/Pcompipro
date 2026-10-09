<template>
  <div class="kanban-container">
    <!-- Encabezado -->
    <div class="kanban-header">
      <div class="kanban-title-section">
        <h1 class="kanban-title">Requerimientos · Kanban</h1>
        <p class="kanban-subtitle">Arrastra las tarjetas para cambiar de etapa</p>
      </div>
      <div class="kanban-actions">
        <a href="/comercial/requerimientos/listado" class="btn btn-secondary">
          <i class="el-icon-list"></i> Ver Lista
        </a>
      </div>
    </div>

    <!-- Filtros -->
    <div class="kanban-filters">
      <div class="filter-group">
        <label>Buscar:</label>
        <input
          v-model="search"
          type="text"
          class="filter-input"
          placeholder="Empresa, título..."
        />
      </div>
      <div class="filter-group">
        <label>Prioridad:</label>
        <select v-model="priority" class="filter-input">
          <option value="">Todas</option>
          <option value="Muy alto">Muy alto</option>
          <option value="Alto">Alto</option>
          <option value="Medio">Medio</option>
          <option value="Bajo">Bajo</option>
        </select>
      </div>
    </div>

    <!-- Tablero Kanban -->
    <div class="kanban-board">
      <div
        v-for="etapa in etapas"
        :key="etapa"
        class="kanban-column"
        :data-etapa="etapa"
      >
        <div class="column-header">
          <span>{{ etapa }}</span>
          <span class="column-count">
            {{ groupedByEtapa[etapa] ? groupedByEtapa[etapa].length : 0 }}
          </span>
        </div>

        <div
          class="column-body"
          @dragover.prevent
          @drop="onDrop(etapa)"
        >
          <div
            v-for="req in groupedByEtapa[etapa]"
            :key="req.id"
            class="kanban-card"
            :class="{ 'kanban-card--convertido': req.escalado_a_proyecto }"
            :draggable="!req.escalado_a_proyecto"
            @dragstart="onDragStart(req)"
            @click="openDetail(req.id)"
          >
            <div class="card-empresa">
              {{ req.empresa_razon_social || '—' }}
            </div>
            <div class="card-title">
              {{ req.titulo || '—' }}
            </div>
            <div class="card-description">
              {{ truncate(req.descripcion, 80) }}
            </div>
            <div class="card-meta">
              <span>{{ req.lead_contacto || '—' }}</span>
              <span>{{ req.fecha_requerida || '—' }}</span>
            </div>
            <div class="card-meta">
              <span>Monto:</span>
              <span class="card-monto">{{ formatMoney(req.monto_estimado) }}</span>
            </div>
            <div v-if="req.escalado_a_proyecto && req.proyecto_codigo" class="card-proyecto">
              <i class="el-icon-link"></i> Convertido a {{ req.proyecto_codigo }}
            </div>
            <div class="card-click-hint">
              Haz clic para ver detalles
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ✅ MODAL DETALLE ACTUALIZADO (3 pestañas: Datos, Interacciones, Cotizaciones) -->
    <el-dialog
      :visible.sync="detailDialog.visible"
      width="1072px"
      top="14vh"
      :close-on-click-modal="false"
      class="req-dialog"
    >
      <span slot="title" class="req-dialog-title">
        {{ detail.empresa_razon_social || 'Requerimiento' }}
        <small v-if="detail.codigo" class="req-dialog-subtitle"> · {{ detail.codigo }}</small>
      </span>

      <div class="req-dialog-body" v-loading="detailDialog.loading">
        <el-row :gutter="16" class="req-dialog-layout">
          <!-- Columna izquierda: tabs -->
          <el-col :xs="24" :md="16">
            <el-tabs v-model="detailDialog.activeTab" type="card">
              
              <!-- ✅ PESTAÑA DATOS (usa componente TabDatos) -->
              <el-tab-pane label="Datos" name="datos">
                <TabDatos
                  :requerimiento="detail"
                  :isNewRecord="false"
                  :solo-lectura="!!detail.escalado_a_proyecto"
                  @updated="onRequerimientoUpdated"
                  @show-nueva-empresa-dialog="showNuevaEmpresaDialog"
                  @show-nuevo-contacto-dialog="showNuevoContactoDialog"
                />
              </el-tab-pane>

              <!-- ✅ PESTAÑA INTERACCIONES (usa componente TabInteracciones) -->
              <el-tab-pane label="Interacciones" name="interacciones">
                <TabInteracciones
                  :requerimientoId="detail.id"
                  @interaccion-added="onInteraccionAdded"
                />
              </el-tab-pane>

              <!-- Pre-Cotización (COM-02) -->
              <el-tab-pane label="Pre-Cotización" name="pre-cotizacion">
                <TabPreCotizacion
                  v-if="detailDialog.activeTab === 'pre-cotizacion'"
                  :requerimientoId="detail.id"
                  @precotizacion-added="onCotizacionAdded"
                />
              </el-tab-pane>

              <!-- Proveedores (COM-02 E4 — IA sugiere proveedores por ítem) -->
              <el-tab-pane label="Proveedores" name="proveedores">
                <TabProveedores
                  v-if="detailDialog.activeTab === 'proveedores'"
                  :requerimientoId="detail.id"
                />
              </el-tab-pane>

              <!-- Cotización Final (COM-02 — Salida Comercial Derivada) -->
              <el-tab-pane label="Cotización Final" name="cotizacion-final">
                <TabCotizacionFinal
                  v-if="detailDialog.activeTab === 'cotizacion-final'"
                  :requerimientoId="detail.id"
                  :contacto-nombre="detail.contacto_nombre"
                  :contacto-cargo="detail.contacto_cargo"
                  :contacto-email="detail.contacto_email"
                />
              </el-tab-pane>

              <!-- ✅ PESTAÑA COTIZACIONES (usa componente TabCotizaciones) -->
              <el-tab-pane label="Cotizaciones" name="cotizaciones">
                <TabCotizaciones
                  :requerimientoId="detail.id"
                  @cotizacion-added="onCotizacionAdded"
                />
              </el-tab-pane>

            </el-tabs>
          </el-col>

          <!-- Columna derecha: Bitácora de cambios -->
          <el-col :xs="24" :md="8">
            <el-card shadow="never" class="req-sidebar-card bitacora-card">
              <div slot="header" class="bitacora-header">
                <i class="el-icon-refresh"></i>
                <span class="bitacora-title">Bitácora de cambios</span>
              </div>

              <!-- Requerimiento convertido a proyecto: solo consulta + acceso al proyecto -->
              <div class="req-sidebar-section" v-if="detail.escalado_a_proyecto">
                <el-alert
                  :title="detail.proyecto_codigo ? ('Convertido al proyecto ' + detail.proyecto_codigo) : 'Convertido a proyecto'"
                  type="success"
                  description="Este requerimiento es de solo consulta."
                  :closable="false"
                  show-icon
                  style="margin-bottom: 10px;"
                />
                <el-button type="primary" size="mini" plain style="width: 100%" @click="verProyecto">
                  <i class="el-icon-top-right"></i> Ver proyecto{{ detail.proyecto_codigo ? (' ' + detail.proyecto_codigo) : '' }}
                </el-button>
              </div>

              <!-- Botón crear proyecto (si aplica) -->
              <div class="req-sidebar-section" v-if="detail.puede_crear_proyecto">
                <el-button
                  type="success"
                  size="mini"
                  @click="onCrearProyecto"
                  plain
                  style="width: 100%"
                >
                  Crear proyecto desde requerimiento
                </el-button>
              </div>

              <!-- Lista de bitácora -->
              <div class="bitacora-panel" v-loading="logsState.loading">
                <div v-if="logs.length === 0" class="req-empty">
                  No hay cambios registrados.
                </div>

                <div
                  v-for="log in logs"
                  :key="log.id"
                  class="bitacora-item"
                >
                  <div class="bitacora-event">
                    <el-tag size="small" type="warning" effect="light">
                      {{ log.event }}
                    </el-tag>
                    <span v-if="log.tipo" class="bitacora-tipo">{{ log.tipo }}</span>
                  </div>

                  <div class="bitacora-user">
                    <i class="el-icon-user"></i> Usuario: {{ log.usuario }}
                  </div>

                  <div class="bitacora-date">
                    {{ log.created_at }}
                  </div>
                </div>
              </div>
            </el-card>
          </el-col>
        </el-row>
      </div>

      <span slot="footer" class="dialog-footer">
        <el-button size="mini" @click="detailDialog.visible = false">
          Cerrar
        </el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import axios from 'axios'
// ✅ IMPORTAR LOS 3 COMPONENTES DE PESTAÑAS
import TabDatos from './TabDatos.vue'
import TabInteracciones from './TabInteracciones.vue'
import TabCotizaciones from './TabCotizaciones.vue'
import TabPreCotizacion from './TabPreCotizacion.vue'
import TabProveedores from './TabProveedores.vue'
import TabCotizacionFinal from './TabCotizacionFinal.vue'

export default {
  name: 'TenantComercialRequerimientosKanban',

  // ✅ REGISTRAR LOS COMPONENTES
  components: {
    TabDatos,
    TabInteracciones,
    TabCotizaciones,
    TabPreCotizacion,
    TabProveedores,
    TabCotizacionFinal,
  },

  data () {
    return {
      etapas: [
        'Pendiente atender',
        'En cotización',
        'Cotización enviada',
        'En Re-cotización',
        'No cotizado',
        'Cotización caída',
        'Cotización aprobada',
        'Convertido a proyecto'
      ],
      records: [],
      search: '',
      priority: '',
      loading: false,

      // Drag & drop
      draggingId: null,

      // ==== DETALLE ====
      detailDialog: {
        visible: false,
        loading: false,
        activeTab: 'datos'  // ✅ CAMBIAR DE 'resumen' A 'datos'
      },
      selectedId: null,
      detail: {},

      // Logs de auditoría (bitácora)
      logs: [],
      logsState: {
        loading: false
      }
    }
  },

  computed: {
    filteredRecords () {
      let data = this.records.slice()

      if (this.search) {
        const term = this.search.toLowerCase()
        data = data.filter(function (r) {
          return ((r.empresa_razon_social || '').toLowerCase().indexOf(term) !== -1) ||
                 ((r.titulo || '').toLowerCase().indexOf(term) !== -1)
        })
      }

      if (this.priority) {
        const p = this.priority
        data = data.filter(function (r) {
          return r.nivel_prioridad === p
        })
      }

      return data
    },

    groupedByEtapa () {
      const groups = {}

      for (let i = 0; i < this.etapas.length; i++) {
        groups[this.etapas[i]] = []
      }

      for (let i = 0; i < this.filteredRecords.length; i++) {
        const r = this.filteredRecords[i]
        const etapa = r.etapa || this.etapas[0]
        if (!groups[etapa]) {
          groups[etapa] = []
        }
        groups[etapa].push(r)
      }

      return groups
    },

    csrfToken () {
      const meta = document.head.querySelector('meta[name="csrf-token"]')
      return meta ? meta.content : ''
    }
  },

  mounted () {
    this.loadRecords()
  },

  methods: {
    async loadRecords () {
      try {
        this.loading = true
        const res = await fetch('/comercial/requerimientos/records', {
          headers: { 'Accept': 'application/json' }
        })
        if (!res.ok) throw new Error('Error al cargar datos')
        this.records = await res.json()
      } catch (err) {
        console.error(err)
        if (this.$message) this.$message.error('Error al cargar requerimientos')
      } finally {
        this.loading = false
      }
    },

    formatMoney (value) {
      if (!value) return '—'
      const num = Number(value)
      if (isNaN(num)) return '—'
      return 'S/ ' + num.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      })
    },

    truncate (text, length) {
      if (length === undefined) length = 100
      if (!text) return '—'
      return text.length > length ? text.substring(0, length) + '...' : text
    },

    // Acceso al proyecto generado (requerimiento convertido a proyecto).
    verProyecto () {
      window.location.href = '/comercial/proyectos/listado'
    },

    // Drag & drop
    onDragStart (req) {
      // Un requerimiento convertido a proyecto es de solo lectura: no se arrastra.
      if (req.escalado_a_proyecto) { this.draggingId = null; return }
      this.draggingId = req.id
    },

    async onDrop (etapa) {
      if (!this.draggingId) return
      // "Convertido a proyecto" es una etapa automática de solo lectura: no se
      // permite soltar tarjetas ahí manualmente.
      if (etapa === 'Convertido a proyecto') { this.draggingId = null; return }
      const id = this.draggingId
      this.draggingId = null

      try {
        const res = await fetch('/comercial/requerimientos/' + id + '/cambiar-etapa', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': this.csrfToken,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({ etapa: etapa })
        })

        if (!res.ok) throw new Error('Error al cambiar etapa')

        const req = this.records.find(function (r) { return r.id === id })
        if (req) req.etapa = etapa
      } catch (err) {
        console.error(err)
        alert('Error al cambiar etapa. Intenta de nuevo.')
      }
    },

    // ==== DETALLE ====
    async openDetail (id) {
      this.selectedId = id
      this.detailDialog.visible = true
      this.detailDialog.loading = true
      this.detailDialog.activeTab = 'datos'  // ✅ Iniciar en pestaña Datos
      this.logs = []

      try {
        const { data } = await axios.get(`/comercial/requerimientos/${id}`, {
          headers: { Accept: 'application/json' }
        })

        if (!data || !data.ok) {
          this.$message.error(data.msg || 'No se pudo cargar el detalle.')
          return
        }

        this.detail = data.data || {}
        
        // Cargar logs (bitácora)
        this.loadLogs()
        
      } catch (e) {
        console.error('[REQ-KANBAN] error show', e)
        this.$message.error('Error al cargar el detalle.')
      } finally {
        this.detailDialog.loading = false
      }
    },

    // Cargar logs para bitácora
    async loadLogs () {
      if (!this.selectedId) return
      this.logsState.loading = true
      try {
        const { data } = await axios.get(
          `/comercial/requerimientos/${this.selectedId}/logs`,
          { headers: { Accept: 'application/json' } }
        )
        if (data && data.ok) {
          this.logs = data.data || []
        } else {
          this.logs = []
        }
      } catch (e) {
        console.error('[REQ-KANBAN] error loadLogs', e)
        this.logs = []
      } finally {
        this.logsState.loading = false
      }
    },

    // ✅ Eventos de los componentes Tab
    onRequerimientoUpdated () {
      // Recargar detalle
      if (this.selectedId) {
        this.openDetail(this.selectedId)
      }
      // Recargar listado kanban
      this.loadRecords()
    },

    onInteraccionAdded () {
      // Recargar logs
      this.loadLogs()
      // Recargar listado kanban
      this.loadRecords()
    },

    onCotizacionAdded () {
      // Recargar detalle
      if (this.selectedId) {
        this.openDetail(this.selectedId)
      }
      // Recargar listado kanban
      this.loadRecords()
    },

    showNuevaEmpresaDialog () {
      this.$message.info('Funcionalidad de crear empresa pendiente de implementar')
    },

    showNuevoContactoDialog (empresaId) {
      this.$message.info(`Funcionalidad de crear contacto para empresa ${empresaId} pendiente de implementar`)
    },

    // Crear proyecto
    async onCrearProyecto () {
      if (!this.detail.id) return
      
      try {
        const { data } = await axios.post(
          `/comercial/requerimientos/${this.detail.id}/crear-proyecto`,
          {},
          {
            headers: {
              'X-CSRF-TOKEN': this.csrfToken,
              'Accept': 'application/json'
            }
          }
        )

        if (data && data.ok) {
          this.$message.success(data.msg || 'Proyecto creado exitosamente')
          this.openDetail(this.detail.id)
          this.loadRecords()
        } else {
          this.$message.error(data.msg || 'Error al crear proyecto')
        }
      } catch (e) {
        console.error('[REQ-KANBAN] error crearProyecto', e)
        this.$message.error('Error al crear proyecto')
      }
    },

    priorityTagType (value) {
      const v = (value || '').toLowerCase()
      if (v.includes('muy')) return 'danger'
      if (v === 'alto') return 'warning'
      if (v === 'medio') return 'info'
      return ''
    },

    formatDate (dateStr) {
      if (!dateStr) return '—'
      return String(dateStr).substr(0, 10)
    },

    formatDateTime (dt) {
      if (!dt) return '—'
      return String(dt).replace('T', ' ').substr(0, 16)
    }
  }
}
</script>

<style scoped>
/* ====== KANBAN BOARD ====== */
.kanban-container {
  padding: 16px;
  background: #f3f4f6;
  min-height: 100vh;
}

.kanban-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.kanban-title-section {
  flex: 1;
}

.kanban-title {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
  color: #111827;
}

.kanban-subtitle {
  margin: 4px 0 0;
  font-size: 13px;
  color: #6b7280;
}

.kanban-actions {
  display: flex;
  gap: 8px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  font-size: 13px;
  font-weight: 500;
  border-radius: 6px;
  border: 1px solid transparent;
  cursor: pointer;
  text-decoration: none;
  transition: background-color 0.2s;
}

.btn-secondary {
  background: #ffffff;
  border-color: #d1d5db;
  color: #374151;
}

.btn-secondary:hover {
  background: #f9fafb;
}

/* Filtros */
.kanban-filters {
  display: flex;
  gap: 16px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  align-items: center;
  gap: 8px;
}

.filter-group label {
  font-size: 13px;
  color: #374151;
}

.filter-input {
  padding: 6px 10px;
  font-size: 13px;
  border: 1px solid #d1d5db;
  border-radius: 6px;
  min-width: 150px;
}

/* Board */
.kanban-board {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 16px;
}

.kanban-column {
  background: #ffffff;
  border-radius: 10px;
  min-width: 220px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.column-header {
  padding: 12px;
  font-size: 13px;
  font-weight: 600;
  color: #111827;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.column-count {
  background: #e5e7eb;
  color: #374151;
  padding: 2px 8px;
  border-radius: 999px;
  font-size: 12px;
}

.column-body {
  flex: 1;
  padding: 8px;
  min-height: 200px;
  max-height: 70vh;
  overflow-y: auto;
}

/* Cards */
.kanban-card {
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px;
  margin-bottom: 8px;
  cursor: grab;
  transition: box-shadow 0.2s, transform 0.2s;
}

.kanban-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

.kanban-card:active {
  cursor: grabbing;
}

.card-empresa {
  font-size: 12px;
  font-weight: 600;
  color: #2563eb;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.card-title {
  font-size: 13px;
  font-weight: 500;
  color: #111827;
  margin-bottom: 4px;
}

.card-description {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
  line-height: 1.4;
}

.card-meta {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
  color: #6b7280;
  margin-bottom: 6px;
}

.card-monto {
  font-weight: 600;
  color: #111827;
}

.card-click-hint {
  font-size: 10px;
  color: #9ca3af;
  text-align: center;
  padding-top: 6px;
  border-top: 1px solid #e5e7eb;
  margin-top: 6px;
}

.text-muted {
  color: #6b7280;
  font-size: 13px;
  text-align: center;
  padding: 20px;
}

@media (max-width: 768px) {
  .kanban-board {
    grid-template-columns: 1fr;
  }

  .kanban-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .kanban-actions {
    width: 100%;
  }
}

/* ====== ESTILOS DEL MODAL ====== */
.kanban-card--convertido {
  border-left: 3px solid #67C23A;
  cursor: default;
}
.card-proyecto {
  margin-top: 4px;
  font-size: 11px;
  font-weight: 600;
  color: #67C23A;
}

.req-dialog >>> .el-dialog__header {
  background-color: #0f172a;
  color: #f9fafb;
  border-bottom: 1px solid #0f172a;
}
.req-dialog >>> .el-dialog__title {
  color: #f9fafb;
}
.req-dialog >>> .el-dialog__headerbtn .el-dialog__close {
  color: #e5e7eb;
}
.req-dialog-title {
  font-weight: 700;
}
.req-dialog-subtitle {
  font-weight: 400;
  font-size: 12px;
  color: #e5e7eb;
}
.req-dialog-body {
  min-height: 60vh;
  max-height: 120vh;
  overflow-y: auto;
}
.req-dialog-layout {
  margin-top: 0;
}

/* Sidebar / Bitácora */
.req-sidebar-card {
  border-radius: 10px;
}
.req-sidebar-section {
  margin-bottom: 8px;
}
.req-empty {
  font-size: 12px;
  color: #6b7280;
  padding: 6px 0;
}

/* Bitácora */
.bitacora-card {
  max-height: 500px;
  overflow-y: auto;
}
.bitacora-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 600;
}
.bitacora-title {
  font-size: 14px;
}
.bitacora-panel {
  padding: 4px 0;
}
.bitacora-item {
  border-bottom: 1px solid #e5e7eb;
  padding: 8px 0;
}
.bitacora-item:last-child {
  border-bottom: none;
}
.bitacora-event {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 4px;
}
.bitacora-tipo {
  font-size: 11px;
  color: #6b7280;
}
.bitacora-user {
  font-size: 11px;
  color: #6b7280;
  margin-bottom: 2px;
}
.bitacora-date {
  font-size: 11px;
  color: #9ca3af;
}
</style>

<style>
/* Estilo GLOBAL, afecta a todos los diálogos Element */
.v-modal {
  background-color: rgba(15, 23, 42, 0.85) !important;
}
</style>
