<template>
  <div class="com07-panel">
    <!-- Encabezado -->
    <div class="cp-head">
      <h2 class="cp-title">Oportunidades por atender</h2>
      <p class="cp-sub">
        Cuentas con cotización caída o sin movimiento comercial asignadas a mi cartera
      </p>
    </div>

    <!-- Filtros -->
    <div class="cp-filters">
      <div class="cp-fld">
        <label>Origen</label>
        <el-select v-model="filtros.origen" placeholder="Todos" size="small" clearable
                   @change="buscar" style="width: 170px;">
          <el-option label="Cotización caída" value="cotizacion_caida"></el-option>
          <el-option label="No cotizado" value="no_cotizado"></el-option>
          <el-option label="Sin movimiento" value="sin_movimiento"></el-option>
        </el-select>
      </div>

      <div class="cp-fld">
        <label>Antigüedad</label>
        <el-select v-model="filtros.antiguedad" placeholder="Todas" size="small" clearable
                   @change="buscar" style="width: 190px;">
          <el-option label="8 semanas a 4 meses" value="8_semanas_4_meses"></el-option>
          <el-option label="4 a 6 meses" value="4_6_meses"></el-option>
          <el-option label="Más de 6 meses" value="mas_6_meses"></el-option>
        </el-select>
      </div>

      <div class="cp-fld">
        <label>Seguimiento</label>
        <el-select v-model="filtros.seguimiento" placeholder="Todos" size="small" clearable
                   @change="buscar" style="width: 160px;">
          <el-option label="Vencido" value="vencido"></el-option>
          <el-option label="Pendiente" value="pendiente"></el-option>
          <el-option label="Sin seguimiento" value="sin_seguimiento"></el-option>
        </el-select>
      </div>

      <div class="cp-fld cp-fld--grow">
        <label>Buscar</label>
        <el-input v-model="filtros.buscar" placeholder="Empresa o contacto" size="small"
                  clearable @clear="buscar" @keyup.enter.native="buscar"></el-input>
      </div>

      <div class="cp-fld">
        <el-button type="primary" size="small" icon="el-icon-search" @click="buscar">Buscar</el-button>
      </div>
      <div class="cp-fld">
        <el-button size="small" @click="limpiar">Limpiar</el-button>
      </div>
    </div>

    <!-- Tabla -->
    <el-table :data="filas" v-loading="cargando" size="small" border stripe
              empty-text="No hay oportunidades que atender."
              :row-class-name="claseFila" style="width: 100%;">

      <el-table-column label="Empresa / Contacto" min-width="230">
        <template slot-scope="s">
          <div class="cp-empresa">{{ s.row.empresa }}</div>
          <div class="cp-contacto">
            {{ s.row.contacto }}<span v-if="s.row.cargo"> · {{ s.row.cargo }}</span>
          </div>
        </template>
      </el-table-column>

      <el-table-column label="Prioridad" width="120" align="center">
        <template slot-scope="s">
          <template v-if="s.row.prioridad_puntaje !== null">
            <el-tag size="mini" :type="tipoPrioridad(s.row.nivel_prioridad)">
              {{ s.row.prioridad_label }}
            </el-tag>
            <div class="cp-puntaje">{{ s.row.prioridad_puntaje.toFixed(1) }}</div>
          </template>
          <span v-else class="cp-vacio">—</span>
        </template>
      </el-table-column>

      <el-table-column label="Origen" width="150">
        <template slot-scope="s">
          <el-tag size="mini" effect="plain" :type="tipoOrigen(s.row.origen)">
            {{ s.row.origen_label }}
          </el-tag>
        </template>
      </el-table-column>

      <el-table-column label="Última actividad" width="130" align="center">
        <template slot-scope="s">{{ s.row.ultima_actividad_at || '—' }}</template>
      </el-table-column>

      <el-table-column label="Antigüedad" width="165">
        <template slot-scope="s">
          <el-tag size="mini" effect="plain" :type="tipoAntiguedad(s.row.rango_antiguedad)">
            {{ s.row.antiguedad_label }}
          </el-tag>
        </template>
      </el-table-column>

      <el-table-column label="Monto histórico" width="140" align="right">
        <template slot-scope="s">{{ moneda(s.row.monto_historico) }}</template>
      </el-table-column>

      <el-table-column label="Próxima atención" width="140" align="center">
        <template slot-scope="s">{{ s.row.proxima_atencion_at || '—' }}</template>
      </el-table-column>

      <el-table-column label="Seguimiento" width="130" align="center">
        <template slot-scope="s">
          <el-tooltip v-if="s.row.seguimiento === 'vencido'" placement="top"
                      :content="'Atrasado ' + s.row.dias_atraso + ' día(s)'">
            <el-tag size="mini" type="danger" effect="plain">Vencido</el-tag>
          </el-tooltip>
          <el-tag v-else-if="s.row.seguimiento === 'pendiente'" size="mini" type="warning" effect="plain">
            Pendiente
          </el-tag>
          <span v-else class="cp-vacio">—</span>
        </template>
      </el-table-column>

      <el-table-column label="Acción" width="140" align="center" fixed="right">
        <template slot-scope="s">
          <el-button type="text" size="small" @click="registrarGestion(s.row)">
            Registrar gestión
          </el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- Pie -->
    <div class="cp-foot">
      <span class="cp-nota">
        Orden: Prioridad Comercial (Potencial 40% + Urgencia 60%), con desempate por urgencia,
        fecha de atención más próxima, potencial y tiempo pendiente. Los seguimientos vencidos se resaltan.
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

    <!-- Pantalla 2 — Registro de la gestión -->
    <el-dialog :title="'Registrar gestión — ' + (modal.contexto.empresa || '')"
               :visible.sync="modal.visible" width="680px" top="4vh"
               custom-class="cg-dialog"
               :close-on-click-modal="false" append-to-body>
      <div v-loading="modal.cargando">

        <!-- Contexto de la oportunidad -->
        <div class="cg-seccion">Contexto de la oportunidad</div>
        <div class="cg-contexto">
          <div class="cg-item">
            <span class="cg-k">Contacto</span>
            <a v-if="modal.enlaces.contacto" :href="modal.enlaces.contacto" target="_blank" class="cg-v cg-link">
              {{ modal.contexto.contacto || '—' }}
            </a>
            <span v-else class="cg-v">{{ modal.contexto.contacto || '—' }}</span>
          </div>
          <div class="cg-item"><span class="cg-k">Cargo</span><span class="cg-v">{{ modal.contexto.cargo || '—' }}</span></div>
          <div class="cg-item"><span class="cg-k">Responsable comercial</span><span class="cg-v">{{ modal.contexto.responsable || '—' }}</span></div>
          <div class="cg-item"><span class="cg-k">Origen</span><span class="cg-v">{{ modal.contexto.origen_label || '—' }}</span></div>
          <div class="cg-item"><span class="cg-k">Última actividad comercial</span><span class="cg-v">{{ modal.contexto.ultima_actividad || '—' }}</span></div>
          <div class="cg-item"><span class="cg-k">Antigüedad</span><span class="cg-v">{{ modal.contexto.antiguedad_label || '—' }}</span></div>
          <div class="cg-item" v-if="modal.enlaces.empresa">
            <span class="cg-k">Empresa</span>
            <a :href="modal.enlaces.empresa" target="_blank" class="cg-v cg-link">Ver ficha</a>
          </div>
          <div class="cg-item" v-if="modal.enlaces.requerimiento">
            <span class="cg-k">Requerimiento</span>
            <a :href="modal.enlaces.requerimiento" target="_blank" class="cg-v cg-link">Ver requerimiento</a>
          </div>
        </div>

        <!-- Seguimiento vigente: permite cancelarlo conservando el motivo -->
        <div v-if="modal.seguimiento" class="cg-vigente" :class="{ 'cg-vigente--vencido': modal.seguimiento.vencido }">
          <span>
            Seguimiento vigente para el <strong>{{ modal.seguimiento.fecha }}</strong>
            <el-tag v-if="modal.seguimiento.vencido" size="mini" type="danger" effect="plain">Vencido</el-tag>
          </span>
          <el-button type="text" size="small" @click="abrirCancelacion">Cancelar seguimiento</el-button>
        </div>

        <!-- Sustento de la Prioridad Comercial -->
        <div v-if="modal.prioridad" class="cg-prioridad">
          <div class="cg-pri-head" @click="modal.verSustento = !modal.verSustento">
            <span>
              Prioridad Comercial:
              <el-tag size="mini" :type="tipoPrioridad(modal.prioridad.nivel)">{{ modal.prioridad.nivel_label }}</el-tag>
              <strong class="cg-pri-num">{{ modal.prioridad.puntaje.toFixed(1) }}</strong>
              <span class="cg-pri-detalle">
                Potencial {{ modal.prioridad.potencial_puntaje }} ·
                Urgencia {{ modal.prioridad.urgencia_puntaje !== null ? modal.prioridad.urgencia_puntaje : modal.prioridad.urgencia_label }}
              </span>
            </span>
            <span class="cg-pri-toggle">{{ modal.verSustento ? 'Ocultar sustento ▴' : 'Ver sustento ▾' }}</span>
          </div>
          <div v-if="modal.verSustento" class="cg-pri-cuerpo">
            <div class="cg-pri-col">
              <div class="cg-pri-titulo">Potencial (40%)</div>
              <div v-for="f in modal.prioridad.factores.potencial" :key="'p' + f.factor" class="cg-pri-fila">
                <span class="cg-pri-factor">{{ f.factor }}</span>
                <span class="cg-pri-valor">{{ f.valor }}</span>
                <span class="cg-pri-puntos">{{ f.puntos }} pt</span>
              </div>
            </div>
            <div class="cg-pri-col">
              <div class="cg-pri-titulo">Urgencia (60%)</div>
              <template v-if="modal.prioridad.factores.urgencia">
                <div v-for="f in modal.prioridad.factores.urgencia" :key="'u' + f.factor" class="cg-pri-fila">
                  <span class="cg-pri-factor">{{ f.factor }}</span>
                  <span class="cg-pri-valor">{{ f.valor }}</span>
                  <span class="cg-pri-puntos">{{ f.puntos }} pt</span>
                </div>
              </template>
              <div v-else class="cg-pri-na">No aplica: la oportunidad no tiene una necesidad actual.</div>
            </div>
          </div>
        </div>

        <!-- Antecedentes comerciales de la cuenta -->
        <template v-if="modal.antecedentes.length">
          <el-table :data="modal.antecedentes" size="mini" class="cg-antecedentes">
            <el-table-column label="Fecha" width="95">
              <template slot-scope="s">{{ s.row.fecha }}</template>
            </el-table-column>
            <el-table-column label="Antecedente" min-width="220">
              <template slot-scope="s">{{ s.row.antecedente }}</template>
            </el-table-column>
            <el-table-column label="Monto" width="110" align="right">
              <template slot-scope="s">{{ moneda(s.row.monto) }}</template>
            </el-table-column>
            <el-table-column label="Situación" width="160">
              <template slot-scope="s">{{ s.row.situacion }}</template>
            </el-table-column>
          </el-table>
        </template>

        <!-- Historial de gestiones previas -->
        <template v-if="modal.gestiones.length">
          <div class="cg-seccion cg-seccion--historial">
            Gestiones previas ({{ modal.gestiones.length }})
          </div>
          <div class="cg-historial">
            <div v-for="(g, i) in modal.gestiones" :key="i" class="cg-gestion">
              <div class="cg-gestion-head">
                <span><strong>{{ g.fecha }}</strong> · {{ g.resultado }}</span>
                <span class="cg-gestion-user">{{ g.usuario }}</span>
              </div>
              <div v-if="g.observacion" class="cg-gestion-obs">{{ g.observacion }}</div>
              <div v-if="g.proxima_accion" class="cg-gestion-obs">
                Próxima acción: {{ g.proxima_accion }}
              </div>
              <div v-if="g.seguimiento_label" class="cg-gestion-seg">
                Seguimiento
                <template v-if="g.seguimiento_at">del {{ g.seguimiento_at }}</template>:
                <span :class="'cg-seg--' + g.seguimiento">{{ g.seguimiento_label }}</span>
                <template v-if="g.seguimiento_at_anterior">
                  · reprogramado desde {{ g.seguimiento_at_anterior }}
                </template>
                <template v-if="g.motivo"> · {{ g.motivo }}</template>
              </div>
            </div>
          </div>
        </template>

        <!-- Formulario -->
        <el-form label-position="top" size="small" class="cg-form" @submit.native.prevent>
          <el-form-item required>
            <span slot="label">Resultado de la gestión</span>
            <el-select v-model="modal.form.resultado" placeholder="Selecciona el resultado" style="width: 100%;">
              <el-option v-for="r in modal.resultados" :key="r.value" :label="r.label" :value="r.value"></el-option>
            </el-select>
          </el-form-item>

          <el-form-item label="Próxima acción">
            <el-input v-model="modal.form.proxima_accion" :maxlength="500"
                      placeholder="Ej.: Enviar propuesta actualizada"></el-input>
          </el-form-item>

          <el-form-item label="Observación">
            <el-input type="textarea" :rows="3" v-model="modal.form.observacion" :maxlength="2000"
                      show-word-limit placeholder="Detalle de la conversación o del intento de contacto"></el-input>
          </el-form-item>

          <div class="cg-seguimiento">
            <div class="cg-switch">
              <span class="cg-k">¿Requiere seguimiento?</span>
              <el-switch v-model="modal.form.requiere_seguimiento" active-text="Sí" inactive-text="No"></el-switch>
            </div>
            <el-form-item v-if="modal.form.requiere_seguimiento" required class="cg-fecha">
              <span slot="label">Fecha del seguimiento</span>
              <el-date-picker v-model="modal.form.seguimiento_at" type="date"
                              value-format="yyyy-MM-dd" format="dd/MM/yyyy"
                              :picker-options="fechasDesdeHoy"
                              placeholder="Selecciona la fecha" style="width: 100%;">
              </el-date-picker>
            </el-form-item>
          </div>
          <p class="cg-nota" v-if="modal.form.requiere_seguimiento">
            La fecha del seguimiento pasa a ser la próxima fecha de atención de la oportunidad.
          </p>
        </el-form>
      </div>

      <div slot="footer">
        <el-button size="small" @click="modal.visible = false">Cancelar</el-button>
        <el-button type="primary" size="small" :loading="modal.guardando" @click="guardarGestion">
          Guardar gestión
        </el-button>
      </div>
    </el-dialog>

    <!-- Cancelación del seguimiento: conserva el motivo y mantiene la oportunidad -->
    <el-dialog title="Cancelar seguimiento" :visible.sync="cancelacion.visible"
               width="480px" custom-class="cg-dialog" append-to-body>
      <p class="cg-nota" style="margin-top: 0;">
        El seguimiento quedará registrado como cancelado con su motivo.
        <strong>La oportunidad y su historial se conservan</strong>, y podrás programar una nueva atención cuando corresponda.
      </p>
      <el-input type="textarea" :rows="3" v-model="cancelacion.motivo" :maxlength="255" show-word-limit
                placeholder="Motivo por el que el seguimiento deja de aplicar"></el-input>
      <div slot="footer">
        <el-button size="small" @click="cancelacion.visible = false">Volver</el-button>
        <el-button type="danger" size="small" :loading="cancelacion.guardando" @click="confirmarCancelacion">
          Cancelar seguimiento
        </el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  name: 'Com07Panel',

  data() {
    return {
      filas: [],
      cargando: false,
      total: 0,
      pagina: 1,
      porPagina: 20,
      filtros: {
        origen: '',
        antiguedad: '',
        seguimiento: '',
        buscar: '',
      },
      modal: {
        visible: false,
        cargando: false,
        guardando: false,
        oportunidadId: null,
        contexto: {},
        prioridad: null,
        verSustento: false,
        antecedentes: [],
        gestiones: [],
        seguimiento: null,
        enlaces: {},
        resultados: [],
        form: {
          resultado: '',
          proxima_accion: '',
          observacion: '',
          requiere_seguimiento: true,
          seguimiento_at: '',
        },
      },
      cancelacion: {
        visible: false,
        guardando: false,
        motivo: '',
      },
      // El seguimiento se programa hacia adelante: ayer ya no se puede atender.
      fechasDesdeHoy: {
        disabledDate(fecha) {
          const hoy = new Date()
          hoy.setHours(0, 0, 0, 0)
          return fecha < hoy
        },
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
        const params = { pagina: this.pagina, page: this.pagina }
        if (this.filtros.origen)      params.origen      = this.filtros.origen
        if (this.filtros.antiguedad)  params.antiguedad  = this.filtros.antiguedad
        if (this.filtros.seguimiento) params.seguimiento = this.filtros.seguimiento
        if (this.filtros.buscar)      params.buscar      = this.filtros.buscar.trim()

        const r = await this.$http.get('/comercial/com07/oportunidades', { params })
        this.filas     = (r.data && r.data.datos) || []
        this.total     = (r.data && r.data.total) || 0
        this.porPagina = (r.data && r.data.por_pagina) || 20
      } catch (e) {
        this.$message.error('No se pudieron cargar las oportunidades.')
      } finally {
        this.cargando = false
      }
    },

    buscar() {
      this.pagina = 1
      this.cargar()
    },

    limpiar() {
      this.filtros = { origen: '', antiguedad: '', seguimiento: '', buscar: '' }
      this.buscar()
    },

    async registrarGestion(fila) {
      this.modal.oportunidadId = fila.id
      this.modal.contexto      = { empresa: fila.empresa }
      this.modal.prioridad     = null
      this.modal.verSustento   = false
      this.modal.antecedentes  = []
      this.modal.gestiones     = []
      this.modal.seguimiento   = null
      this.modal.enlaces       = {}
      this.modal.form = {
        resultado: '',
        proxima_accion: '',
        observacion: '',
        requiere_seguimiento: true,
        seguimiento_at: '',
      }
      this.modal.visible  = true
      this.modal.cargando = true
      try {
        const r = await this.$http.get('/comercial/com07/oportunidades/' + fila.id)
        this.modal.contexto     = (r.data && r.data.contexto) || this.modal.contexto
        this.modal.prioridad    = (r.data && r.data.prioridad) || null
        this.modal.antecedentes = (r.data && r.data.antecedentes) || []
        this.modal.gestiones    = (r.data && r.data.gestiones) || []
        this.modal.seguimiento  = (r.data && r.data.seguimiento) || null
        this.modal.enlaces      = (r.data && r.data.enlaces) || {}
        this.modal.resultados   = (r.data && r.data.resultados) || []
      } catch (e) {
        this.modal.visible = false
        this.$message.error('No se pudo abrir la oportunidad.')
      } finally {
        this.modal.cargando = false
      }
    },

    async guardarGestion() {
      if (!this.modal.form.resultado) {
        this.$message.warning('Selecciona el resultado de la gestión.')
        return
      }
      if (this.modal.form.requiere_seguimiento && !this.modal.form.seguimiento_at) {
        this.$message.warning('Indica la fecha del seguimiento.')
        return
      }

      this.modal.guardando = true
      try {
        await this.$http.post('/comercial/com07/oportunidades/' + this.modal.oportunidadId + '/gestiones', {
          resultado:            this.modal.form.resultado,
          proxima_accion:       this.modal.form.proxima_accion || null,
          observacion:          this.modal.form.observacion || null,
          requiere_seguimiento: this.modal.form.requiere_seguimiento ? 1 : 0,
          seguimiento_at:       this.modal.form.requiere_seguimiento ? this.modal.form.seguimiento_at : null,
        })
        this.modal.visible = false
        this.$message.success('Gestión registrada.')
        this.cargar()
      } catch (e) {
        const msg = e.response && e.response.data && e.response.data.errors
          ? Object.values(e.response.data.errors)[0][0]
          : 'No se pudo guardar la gestión.'
        this.$message.error(msg)
      } finally {
        this.modal.guardando = false
      }
    },

    abrirCancelacion() {
      this.cancelacion.motivo  = ''
      this.cancelacion.visible = true
    },

    async confirmarCancelacion() {
      const motivo = this.cancelacion.motivo.trim()
      if (!motivo) {
        this.$message.warning('Indica el motivo por el que el seguimiento deja de aplicar.')
        return
      }

      this.cancelacion.guardando = true
      try {
        await this.$http.post(
          '/comercial/com07/oportunidades/' + this.modal.oportunidadId + '/cancelar-seguimiento',
          { motivo: motivo }
        )
        this.cancelacion.visible = false
        this.modal.visible = false
        this.$message.success('Seguimiento cancelado. La oportunidad se mantiene activa.')
        this.cargar()
      } catch (e) {
        const msg = e.response && e.response.data && e.response.data.mensaje
          ? e.response.data.mensaje
          : 'No se pudo cancelar el seguimiento.'
        this.$message.error(msg)
      } finally {
        this.cancelacion.guardando = false
      }
    },

    // Las oportunidades vencidas se resaltan: son un compromiso ya asumido
    // con el cliente que no se cumplió.
    claseFila({ row }) {
      return row.seguimiento === 'vencido' ? 'cp-row--vencida' : ''
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

    moneda(valor) {
      if (valor === null || valor === undefined || valor === '') return '—'
      const n = Number(valor)
      if (isNaN(n)) return '—'
      return 'S/ ' + n.toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    },
  },
}
</script>

<style scoped>
.com07-panel { padding: 4px 0; }

.cp-head { margin-bottom: 16px; }
.cp-title { font-size: 17px; font-weight: 600; margin: 0 0 3px; color: #303133; }
.cp-sub   { color: #909399; font-size: 12px; margin: 0; }

.cp-filters {
  display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 14px;
}
.cp-fld { display: flex; flex-direction: column; gap: 5px; }
.cp-fld--grow { flex: 1; min-width: 190px; }
.cp-fld label { font-size: 12px; color: #606266; }

.cp-empresa  { color: #303133; font-weight: 600; }
.cp-contacto { color: #909399; font-size: 12px; margin-top: 2px; }
.cp-vacio    { color: #C0C4CC; }

.cp-foot {
  display: flex; justify-content: space-between; align-items: center;
  margin-top: 12px; gap: 16px; flex-wrap: wrap;
}
.cp-nota { color: #909399; font-size: 12px; line-height: 1.5; }

/* Modal de gestión */
.cg-seccion {
  font-size: 13px; font-weight: 600; color: #303133;
  border-bottom: 1px solid #EBEEF5; padding-bottom: 6px; margin-bottom: 10px;
}
.cg-contexto {
  display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; margin-bottom: 14px;
}
.cg-item { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; }
.cg-k { color: #909399; }
.cg-v { color: #303133; font-weight: 500; text-align: right; }
.cg-antecedentes { margin-bottom: 14px; }
.cg-form { margin-top: 4px; }
.cg-seguimiento { display: flex; align-items: flex-end; gap: 24px; flex-wrap: wrap; }
.cg-switch { display: flex; align-items: center; gap: 10px; padding-bottom: 18px; font-size: 13px; }
.cg-fecha { flex: 1; min-width: 200px; margin-bottom: 0 !important; }
.cg-nota { color: #909399; font-size: 12px; margin: 6px 0 0; }

/* Sustento de la Prioridad */
.cp-puntaje { color: #909399; font-size: 11px; margin-top: 2px; }
.cg-prioridad {
  border: 1px solid #EBEEF5; border-radius: 4px; background: #FAFBFC;
  padding: 8px 12px; margin-bottom: 14px; font-size: 13px;
}
.cg-pri-head { display: flex; justify-content: space-between; align-items: center; cursor: pointer; gap: 12px; flex-wrap: wrap; }
.cg-pri-num { color: #303133; margin-left: 4px; }
.cg-pri-detalle { color: #909399; font-size: 12px; margin-left: 8px; }
.cg-pri-toggle { color: #409EFF; font-size: 12px; white-space: nowrap; }
.cg-pri-cuerpo { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; margin-top: 10px; }
.cg-pri-titulo { font-weight: 600; color: #606266; font-size: 12px; margin-bottom: 4px; }
.cg-pri-fila { display: flex; gap: 8px; font-size: 12px; padding: 2px 0; }
.cg-pri-factor { flex: 1; color: #606266; }
.cg-pri-valor { color: #909399; text-align: right; }
.cg-pri-puntos { width: 34px; text-align: right; color: #303133; font-weight: 600; }
.cg-pri-na { color: #909399; font-size: 12px; font-style: italic; }
@media (max-width: 640px) { .cg-pri-cuerpo { grid-template-columns: 1fr; } }

/* Enlaces a los registros del ERP */
.cg-dialog .cg-link { color: #409EFF; text-decoration: none; }
.cg-dialog .cg-link:hover { text-decoration: underline; }

/* Seguimiento vigente */
.cg-dialog .cg-vigente {
  display: flex; justify-content: space-between; align-items: center; gap: 12px;
  border: 1px solid #EBEEF5; border-radius: 4px; background: #FDF6EC;
  padding: 6px 12px; margin-bottom: 14px; font-size: 13px; flex-wrap: wrap;
}
.cg-dialog .cg-vigente--vencido { background: #FEF0F0; }

/* Historial de gestiones */
.cg-dialog .cg-seccion--historial { margin-top: 4px; }
.cg-dialog .cg-historial { max-height: 210px; overflow-y: auto; margin-bottom: 14px; }
.cg-dialog .cg-gestion {
  border-left: 2px solid #DCDFE6; padding: 4px 0 8px 10px; margin-bottom: 8px; font-size: 12px;
}
.cg-dialog .cg-gestion-head { display: flex; justify-content: space-between; gap: 10px; color: #303133; }
.cg-dialog .cg-gestion-user { color: #909399; }
.cg-dialog .cg-gestion-obs  { color: #606266; margin-top: 2px; }
.cg-dialog .cg-gestion-seg  { color: #909399; margin-top: 3px; }
.cg-dialog .cg-seg--vencido   { color: #F56C6C; font-weight: 600; }
.cg-dialog .cg-seg--pendiente { color: #E6A23C; font-weight: 600; }
.cg-dialog .cg-seg--atendido  { color: #67C23A; font-weight: 600; }
.cg-dialog .cg-seg--cancelado { color: #909399; font-weight: 600; }
</style>

<style>
/* Sin scope: el-table aplica la clase sobre su propia fila. */
.com07-panel .cp-row--vencida td { background: #FEF6F6 !important; }

/*
 * Sin scope: el dialog se monta con append-to-body fuera del componente.
 *
 * El tema del ERP aplica un word-break global que parte las palabras
 * ("Resulta do de la gestión", "Antigüedad de relació n"); acá se restablece
 * el corte normal por espacios, y las etiquetas del formulario se fuerzan a
 * ocupar todo el ancho — el tema les fija un ancho de columna pensado para
 * formularios con etiqueta a la izquierda, y este formulario las lleva arriba.
 */
.cg-dialog,
.cg-dialog .el-dialog__body { word-break: normal; overflow-wrap: break-word; }

/* Las celdas de el-table traen word-break: break-all propio (aparte del tema):
   sin esto, "SUCURSAL" se parte en "SU CURSAL" en la tabla del panel y en los
   antecedentes del modal. */
.com07-panel .el-table .cell,
.cg-dialog .el-table .cell { word-break: normal; }
.cg-dialog .el-form-item__label {
  float: none; display: block; width: auto !important;
  text-align: left; line-height: 1.4; white-space: normal;
  word-break: normal; padding-bottom: 4px;
}
</style>
