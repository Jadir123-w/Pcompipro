<template>
  <div class="com07-super">
    <!-- Encabezado -->
    <div class="cs-head">
      <h2 class="cs-title">Supervisión de oportunidades</h2>
      <p class="cs-sub">Consolidado de oportunidades y seguimientos por ejecutiva</p>
    </div>

    <!-- Contadores -->
    <div class="cs-cards">
      <div class="cs-card">
        <div class="cs-num">{{ resumen.activas }}</div>
        <div class="cs-lbl">Oportunidades activas</div>
      </div>
      <div class="cs-card">
        <div class="cs-num">{{ resumen.seguimientos_pendientes }}</div>
        <div class="cs-lbl">Seguimientos pendientes</div>
      </div>
      <div class="cs-card cs-card--danger">
        <div class="cs-num">{{ resumen.seguimientos_vencidos }}</div>
        <div class="cs-lbl">Seguimientos vencidos</div>
      </div>
      <div class="cs-card cs-card--warn">
        <div class="cs-num">{{ resumen.pendientes_asignacion }}</div>
        <div class="cs-lbl">Pendientes de asignación</div>
      </div>
    </div>

    <!-- Filtros -->
    <div class="cs-filters">
      <div class="cs-fld">
        <label>Ejecutiva</label>
        <el-select v-model="filtros.ejecutiva" placeholder="Todas" size="small" clearable
                   @change="buscar" style="width: 200px;">
          <el-option v-for="e in ejecutivas" :key="e.id" :label="e.nombre" :value="String(e.id)"></el-option>
          <el-option label="Sin asignar" value="sin_asignar"></el-option>
        </el-select>
      </div>

      <div class="cs-fld">
        <label>Seguimiento</label>
        <el-select v-model="filtros.seguimiento" placeholder="Todos" size="small" clearable
                   @change="buscar" style="width: 200px;">
          <el-option label="Vencido" value="vencido"></el-option>
          <el-option label="Pendiente" value="pendiente"></el-option>
          <el-option label="Sin seguimiento" value="sin_seguimiento"></el-option>
          <el-option label="Pendiente de asignación" value="pendiente_asignacion"></el-option>
        </el-select>
      </div>

      <div class="cs-fld">
        <label>Origen</label>
        <el-select v-model="filtros.origen" placeholder="Todos" size="small" clearable
                   @change="buscar" style="width: 170px;">
          <el-option label="Cotización caída" value="cotizacion_caida"></el-option>
          <el-option label="No cotizado" value="no_cotizado"></el-option>
          <el-option label="Sin movimiento" value="sin_movimiento"></el-option>
        </el-select>
      </div>

      <div class="cs-fld cs-fld--grow">
        <label>Buscar</label>
        <el-input v-model="filtros.buscar" placeholder="Empresa o contacto" size="small"
                  clearable @clear="buscar" @keyup.enter.native="buscar"></el-input>
      </div>

      <div class="cs-fld">
        <el-button type="primary" size="small" icon="el-icon-search" @click="buscar">Buscar</el-button>
      </div>
    </div>

    <!-- Tabla -->
    <el-table :data="filas" v-loading="cargando" size="small" border stripe
              empty-text="No hay oportunidades con esos criterios."
              :row-class-name="claseFila" style="width: 100%;">

      <el-table-column label="Ejecutiva" width="150">
        <template slot-scope="s">
          <span v-if="s.row.ejecutiva">{{ s.row.ejecutiva }}</span>
          <el-tag v-else size="mini" type="warning" effect="plain">Sin asignar</el-tag>
        </template>
      </el-table-column>

      <el-table-column label="Empresa / Contacto" min-width="220">
        <template slot-scope="s">
          <div class="cs-empresa">{{ s.row.empresa }}</div>
          <div class="cs-contacto">{{ s.row.contacto }}</div>
        </template>
      </el-table-column>

      <el-table-column label="Prioridad" width="115" align="center">
        <template slot-scope="s">
          <template v-if="s.row.prioridad_puntaje !== null">
            <el-tag size="mini" :type="tipoPrioridad(s.row.nivel_prioridad)">
              {{ s.row.prioridad_label }}
            </el-tag>
            <div class="cs-puntaje">{{ s.row.prioridad_puntaje.toFixed(1) }}</div>
          </template>
          <span v-else class="cs-vacio">—</span>
        </template>
      </el-table-column>

      <el-table-column label="Origen" width="145">
        <template slot-scope="s">
          <el-tag size="mini" effect="plain" :type="tipoOrigen(s.row.origen)">
            {{ s.row.origen_label }}
          </el-tag>
        </template>
      </el-table-column>

      <el-table-column label="Antigüedad" width="160">
        <template slot-scope="s">
          <el-tag size="mini" effect="plain" :type="tipoAntiguedad(s.row.rango_antiguedad)">
            {{ s.row.antiguedad_label }}
          </el-tag>
        </template>
      </el-table-column>

      <el-table-column label="Próxima atención" width="135" align="center">
        <template slot-scope="s">{{ s.row.proxima_atencion_at || '—' }}</template>
      </el-table-column>

      <el-table-column label="Seguimiento" width="170" align="center">
        <template slot-scope="s">
          <el-tag size="mini" effect="plain" :type="tipoSeguimiento(s.row.seguimiento)">
            {{ s.row.seguimiento_label }}
          </el-tag>
        </template>
      </el-table-column>

      <el-table-column label="Días de atraso" width="115" align="center">
        <template slot-scope="s">
          <span v-if="s.row.dias_atraso > 0" class="cs-atraso">{{ s.row.dias_atraso }}</span>
          <span v-else class="cs-vacio">—</span>
        </template>
      </el-table-column>

      <el-table-column label="Última gestión" min-width="200">
        <template slot-scope="s">
          <span v-if="s.row.ultima_gestion">{{ s.row.ultima_gestion }}</span>
          <span v-else class="cs-vacio">—</span>
        </template>
      </el-table-column>
    </el-table>

    <!-- Pie -->
    <div class="cs-foot">
      <span class="cs-nota">
        Las oportunidades sin responsable comercial permanecen visibles en esta vista como
        pendientes de asignación y no se incorporan al panel de ninguna ejecutiva.
      </span>
      <el-pagination
        v-if="total > porPagina"
        background
        layout="total, prev, pager, next"
        :total="total"
        :page-size="porPagina"
        :current-page.sync="pagina"
        @current-change="cargar">
      </el-pagination>
    </div>
  </div>
</template>

<script>
export default {
  name: 'Com07Supervision',

  data() {
    return {
      filas: [],
      cargando: false,
      total: 0,
      pagina: 1,
      porPagina: 20,
      resumen: {
        activas: 0,
        seguimientos_pendientes: 0,
        seguimientos_vencidos: 0,
        pendientes_asignacion: 0,
      },
      ejecutivas: [],
      filtros: {
        ejecutiva: '',
        seguimiento: '',
        origen: '',
        buscar: '',
      },
    }
  },

  created() {
    this.cargar()
  },

  methods: {
    async cargar() {
      this.cargando = true
      try {
        const params = { page: this.pagina }
        if (this.filtros.ejecutiva)   params.ejecutiva   = this.filtros.ejecutiva
        if (this.filtros.seguimiento) params.seguimiento = this.filtros.seguimiento
        if (this.filtros.origen)      params.origen      = this.filtros.origen
        if (this.filtros.buscar)      params.buscar      = this.filtros.buscar.trim()

        const r = await this.$http.get('/comercial/com07/supervision/oportunidades', { params })
        this.filas      = (r.data && r.data.datos) || []
        this.total      = (r.data && r.data.total) || 0
        this.porPagina  = (r.data && r.data.por_pagina) || 20
        this.resumen    = (r.data && r.data.resumen) || this.resumen
        this.ejecutivas = (r.data && r.data.ejecutivas) || []
      } catch (e) {
        this.$message.error('No se pudo cargar la supervisión.')
      } finally {
        this.cargando = false
      }
    },

    buscar() {
      this.pagina = 1
      this.cargar()
    },

    claseFila({ row }) {
      if (row.seguimiento === 'pendiente_asignacion') return 'cs-row--sin-asignar'
      if (row.seguimiento === 'vencido') return 'cs-row--vencida'
      return ''
    },

    tipoOrigen(origen) {
      if (origen === 'cotizacion_caida') return 'danger'
      if (origen === 'no_cotizado')      return 'warning'
      return 'info'
    },

    tipoPrioridad(nivel) {
      if (nivel === 'muy_alta') return 'danger'
      if (nivel === 'alta')     return 'warning'
      if (nivel === 'media')    return ''
      return 'info'
    },

    tipoAntiguedad(rango) {
      if (rango === 'mas_6_meses') return 'danger'
      if (rango === '4_6_meses')   return 'warning'
      return 'info'
    },

    tipoSeguimiento(seguimiento) {
      if (seguimiento === 'vencido')              return 'danger'
      if (seguimiento === 'pendiente')            return 'warning'
      if (seguimiento === 'pendiente_asignacion') return 'warning'
      return 'info'
    },
  },
}
</script>

<style scoped>
.com07-super { padding: 4px 0; }

.cs-head { margin-bottom: 16px; }
.cs-title { font-size: 17px; font-weight: 600; margin: 0 0 3px; color: #303133; }
.cs-sub   { color: #909399; font-size: 12px; margin: 0; }

.cs-cards {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px; margin-bottom: 16px;
}
.cs-card {
  border: 1px solid #EBEEF5; border-radius: 4px; padding: 12px 16px; background: #fff;
}
.cs-card--danger .cs-num { color: #F56C6C; }
.cs-card--warn   .cs-num { color: #E6A23C; }
.cs-num { font-size: 24px; font-weight: 700; color: #303133; line-height: 1.2; }
.cs-lbl { font-size: 12px; color: #909399; margin-top: 2px; }

.cs-filters {
  display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 14px;
}
.cs-fld { display: flex; flex-direction: column; gap: 5px; }
.cs-fld--grow { flex: 1; min-width: 180px; }
.cs-fld label { font-size: 12px; color: #606266; }

.cs-empresa  { color: #303133; font-weight: 600; }
.cs-contacto { color: #909399; font-size: 12px; margin-top: 2px; }
.cs-vacio    { color: #C0C4CC; }
.cs-atraso   { color: #F56C6C; font-weight: 600; }
.cs-puntaje  { color: #909399; font-size: 11px; margin-top: 2px; }

.cs-foot {
  display: flex; justify-content: space-between; align-items: center;
  margin-top: 12px; gap: 16px; flex-wrap: wrap;
}
.cs-nota { color: #909399; font-size: 12px; line-height: 1.5; max-width: 640px; }
</style>

<style>
.com07-super .cs-row--vencida td     { background: #FEF6F6 !important; }
.com07-super .cs-row--sin-asignar td { background: #FDF6EC !important; }

/* Las celdas de el-table parten palabras por su word-break: break-all propio
   ("ANONI MA"); se restablece el corte por espacios. */
.com07-super .el-table .cell { word-break: normal; }
</style>
