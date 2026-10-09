<template>
  <div class="email-triage-pending">

    <!-- Header -->
    <div class="etp-header">
      <div>
        <h2 class="etp-title">📬 Email Triage — Borradores pendientes</h2>
        <p class="etp-subtitle">
          La IA leyó los correos entrantes, los clasificó y redactó un borrador de respuesta.
          Acá los revisás, editás si hace falta, y aprobás antes de enviar.
          <strong v-if="pendingCount > 0" class="etp-badge">{{ pendingCount }} pendiente(s)</strong>
        </p>
      </div>
      <div class="etp-actions">
        <el-tooltip
            :content="runNowTooltip"
            placement="left">
          <el-button
              type="primary"
              size="small"
              icon="el-icon-message-solid"
              :loading="runNowInProgress"
              :disabled="runNowThrottleSecs > 0"
              @click="runNow">
            {{ runNowButtonLabel }}
          </el-button>
        </el-tooltip>
        <el-tooltip content="Auto-refresh cada 25 segundos" placement="left">
          <el-switch v-model="autoRefresh" active-text="Auto"></el-switch>
        </el-tooltip>
        <el-button size="small" icon="el-icon-refresh" @click="loadAll" :loading="loading">Actualizar</el-button>
      </div>
    </div>

    <!-- Filtros -->
    <el-row :gutter="12" style="margin-bottom: 12px;">
      <el-col :span="6">
        <el-select size="small" v-model="filterStatus" placeholder="Filtrar por estado" clearable style="width: 100%;" @change="loadAll">
          <el-option label="Pendientes (a revisar)" value="pending"></el-option>
          <el-option label="Editados (a aprobar)" value="edited"></el-option>
          <el-option label="Aprobados (a enviar)" value="approved"></el-option>
          <el-option label="Enviados" value="sent"></el-option>
          <el-option label="Rechazados" value="rejected"></el-option>
          <el-option label="Error de envío" value="send_error"></el-option>
          <el-option label="— Todos —" value="all"></el-option>
        </el-select>
      </el-col>
      <el-col :span="5">
        <el-select size="small" v-model="filterClassification" placeholder="Clasificación" clearable style="width: 100%;" @change="loadAll">
          <el-option label="RFQ (cotización)" value="rfq"></el-option>
          <el-option label="Consulta" value="consulta"></el-option>
          <el-option label="No relacionado" value="no_relacionado"></el-option>
          <el-option label="Spam" value="spam"></el-option>
          <el-option label="Otro" value="otro"></el-option>
        </el-select>
      </el-col>
      <el-col :span="6">
        <el-input size="small" v-model="filterSearch" placeholder="Buscar por remitente, asunto..." clearable @clear="loadAll" @keyup.enter.native="loadAll">
          <el-button slot="append" icon="el-icon-search" @click="loadAll"></el-button>
        </el-input>
      </el-col>
      <el-col :span="7" style="text-align: right; line-height: 32px; color: #909399; font-size: 12px;">
        Última actualización: {{ lastRefreshLabel }}
      </el-col>
    </el-row>

    <!-- Empty state -->
    <el-card v-if="!loading && rows.length === 0" class="etp-empty">
      <i class="el-icon-message" style="font-size: 48px; color: #67c23a;"></i>
      <div style="margin-top: 12px; font-size: 16px; color: #606266;">
        No hay borradores que coincidan con el filtro.
      </div>
      <div style="margin-top: 6px; color: #909399; font-size: 13px;">
        Cuando lleguen correos nuevos al buzón configurado, la IA va a redactar respuestas y aparecerán acá para tu revisión.
      </div>
    </el-card>

    <!-- Lista -->
    <div v-else class="etp-list" v-loading="loading">
      <el-card v-for="row in rows" :key="row.id" class="etp-card" :class="['etp-card--' + row.status]">

        <!-- Header de la tarjeta -->
        <div slot="header" class="etp-card-head">
          <div class="etp-card-head-left">
            <strong>{{ row.email_received ? (row.email_received.from_name || row.email_received.from_email) : '(sin remitente)' }}</strong>
            <span style="color: #909399; font-size: 12px; margin-left: 8px;" v-if="row.email_received && row.email_received.from_name">
              &lt;{{ row.email_received.from_email }}&gt;
            </span>
            <el-tag size="mini" :type="statusTag(row.status).type" effect="plain" style="margin-left: 8px;">
              {{ statusTag(row.status).label }}
            </el-tag>
            <el-tag v-if="row.email_received && row.email_received.classification"
                    size="mini"
                    :type="classificationTag(row.email_received.classification)"
                    effect="dark"
                    style="margin-left: 4px;">
              {{ classificationLabel(row.email_received.classification) }}
              <span v-if="row.email_received.classification_confidence">
                · {{ Math.round(row.email_received.classification_confidence) }}%
              </span>
            </el-tag>
            <el-tag v-if="row.email_received && row.email_received.has_attachments"
                    size="mini" type="warning" effect="plain" style="margin-left: 4px;">
              📎 con adjuntos
            </el-tag>
          </div>
          <div class="etp-card-head-right">
            {{ formatTime(row.email_received ? row.email_received.received_at : row.created_at) }}
          </div>
        </div>

        <!-- Correo original (resumen) -->
        <div class="etp-block etp-block--original">
          <div class="etp-label">
            <i class="el-icon-message"></i> Correo original recibido:
          </div>
          <div class="etp-orig-subject"><strong>Asunto:</strong> {{ row.email_received ? row.email_received.subject : '—' }}</div>
          <el-button v-if="!row.__expanded" size="mini" type="text" @click="$set(row, '__expanded', true)">
            Ver cuerpo completo del correo original
          </el-button>
          <div v-if="row.__expanded" class="etp-orig-body">
            {{ (row.email_received && row.email_received.body_plain) || '(cuerpo no disponible)' }}
            <div style="text-align: right; margin-top: 6px;">
              <el-button size="mini" type="text" @click="$set(row, '__expanded', false)">
                Ocultar
              </el-button>
            </div>
          </div>
        </div>

        <!-- Borrador IA (la respuesta propuesta) -->
        <div class="etp-block etp-block--draft">
          <div class="etp-label">
            <i class="el-icon-edit-outline"></i> Borrador propuesto por IA:
          </div>
          <div class="etp-draft-meta">
            <span><strong>Para:</strong> {{ row.to_email || '—' }}</span>
            <span v-if="row.cc_emails && row.cc_emails.length">
              <strong>CC:</strong> {{ row.cc_emails.join(', ') }}
            </span>
          </div>
          <div class="etp-draft-subject"><strong>Asunto:</strong> {{ row.draft_subject || '—' }}</div>
          <div class="etp-draft-body">{{ row.draft_body || '—' }}</div>
          <div v-if="row.data_gaps && row.data_gaps.length" class="etp-data-gaps">
            ⚠️ La IA pidió aclarar al cliente: <strong>{{ row.data_gaps.join(', ') }}</strong>
          </div>
        </div>

        <!-- Status info para estados terminales -->
        <div v-if="row.status === 'sent'" class="etp-status-info etp-status-info--ok">
          ✅ Enviado
          <span v-if="row.approver"> por <strong>{{ row.approver.name || row.approver.email }}</strong></span>
          {{ row.sent_at ? '· ' + formatTime(row.sent_at) : '' }}
          <span v-if="row.sent_message_id" style="margin-left: 6px; color: #909399; font-size: 11px;">
            (ID: {{ row.sent_message_id }})
          </span>
        </div>
        <div v-if="row.status === 'rejected'" class="etp-status-info etp-status-info--reject">
          ❌ Rechazado
          <span v-if="row.approver"> por <strong>{{ row.approver.name || row.approver.email }}</strong></span>
          <span v-if="row.rejected_reason"> · "{{ row.rejected_reason }}"</span>
        </div>
        <div v-if="row.status === 'send_error'" class="etp-status-info etp-status-info--fail">
          ⚠️ Falló el envío SMTP: <strong>{{ row.send_error_message }}</strong>
        </div>
        <div v-if="row.status === 'approved'" class="etp-status-info etp-status-info--info">
          ☑️ Aprobado por
          <strong v-if="row.approver">{{ row.approver.name || row.approver.email }}</strong>
          <span v-else>—</span>
          · pendiente de envío SMTP.
        </div>

        <!-- Acciones según estado -->
        <div v-if="row.status === 'pending' || row.status === 'edited'" class="etp-card-actions">
          <el-button type="success" size="small" icon="el-icon-check" :loading="actingId === row.id" @click="approve(row)">
            Aprobar
          </el-button>
          <el-button size="small" icon="el-icon-edit" @click="openEdit(row)">
            Editar
          </el-button>
          <el-button type="danger" size="small" icon="el-icon-close" @click="openReject(row)">
            Rechazar
          </el-button>
        </div>
        <div v-if="row.status === 'approved'" class="etp-card-actions">
          <el-button type="primary" size="small" icon="el-icon-promotion" :loading="actingId === row.id" @click="sendNow(row)">
            Enviar ahora
          </el-button>
        </div>
        <div v-if="row.status === 'send_error'" class="etp-card-actions">
          <el-button type="warning" size="small" icon="el-icon-refresh-right" :loading="actingId === row.id" @click="sendNow(row)">
            Reintentar envío
          </el-button>
        </div>

      </el-card>
    </div>

    <!-- Paginación -->
    <div v-if="pagination && pagination.last_page > 1" class="etp-pagination">
      <el-pagination
        background
        layout="prev, pager, next, total"
        :current-page="pagination.current_page"
        :page-size="pagination.per_page"
        :total="pagination.total"
        @current-change="changePage">
      </el-pagination>
    </div>

    <!-- Modal: editar borrador -->
    <el-dialog title="Editar borrador antes de aprobar"
               :visible.sync="editVisible"
               width="640px"
               custom-class="etp-edit-dialog"
               append-to-body>
      <p class="etp-tip" v-if="editTarget">
        Estos cambios se guardan en el borrador. Después podés aprobarlo y enviarlo.
      </p>

      <el-form label-position="top" size="small">
        <el-form-item label="Para (To)">
          <el-input v-model="editTo" placeholder="destinatario@correo.com"></el-input>
        </el-form-item>

        <el-form-item label="CC (separar con coma)">
          <el-input v-model="editCcRaw" placeholder="cc1@correo.com, cc2@correo.com"></el-input>
        </el-form-item>

        <el-form-item label="Asunto">
          <el-input v-model="editSubject" :maxlength="500" show-word-limit></el-input>
        </el-form-item>

        <el-form-item label="Cuerpo del correo">
          <el-input type="textarea" :rows="10" v-model="editBody"></el-input>
        </el-form-item>
      </el-form>

      <div slot="footer">
        <el-button @click="editVisible = false">Cancelar</el-button>
        <el-button type="primary" icon="el-icon-edit" :loading="actingId === (editTarget && editTarget.id)" @click="submitEdit">
          Guardar cambios
        </el-button>
      </div>
    </el-dialog>

    <!-- Modal: rechazar -->
    <el-dialog title="Rechazar borrador"
               :visible.sync="rejectVisible"
               width="440px"
               custom-class="etp-edit-dialog"
               append-to-body>
      <p class="etp-tip" v-if="rejectTarget">
        El correo original se va a marcar como descartado. NO se envía nada al cliente.
        El motivo es opcional, queda como auditoría.
      </p>
      <el-input v-model="rejectReason" placeholder="Motivo (opcional)" :maxlength="500" show-word-limit></el-input>
      <div slot="footer">
        <el-button @click="rejectVisible = false">Cancelar</el-button>
        <el-button type="danger" icon="el-icon-close" :loading="actingId === (rejectTarget && rejectTarget.id)" @click="submitReject">
          Rechazar
        </el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading: false,
      rows: [],
      pendingCount: 0,
      pagination: null,
      filterStatus: 'pending',
      filterClassification: '',
      filterSearch: '',
      page: 1,
      autoRefresh: true,
      refreshTimer: null,
      lastRefresh: null,
      actingId: null,

      // Edit modal
      editVisible: false,
      editTarget: null,
      editSubject: '',
      editBody: '',
      editTo: '',
      editCcRaw: '',

      // Reject modal
      rejectVisible: false,
      rejectTarget: null,
      rejectReason: '',

      // Botón "Revisar bandeja ahora"
      runNowInProgress: false,
      runNowThrottleSecs: 0,    // segundos restantes del throttle
      runNowThrottleTimer: null,
    }
  },
  computed: {
    lastRefreshLabel() {
      if (!this.lastRefresh) return '—'
      return this.lastRefresh.toLocaleTimeString()
    },
    // True si el usuario tiene al menos una card con el cuerpo del correo
    // original expandido. Si es true, pausamos el auto-refresh de la lista
    // para no interrumpir la lectura.
    hasExpandedRow() {
      return (this.rows || []).some(r => r.__expanded)
    },
    // Label dinámico del botón "Revisar bandeja ahora"
    runNowButtonLabel() {
      if (this.runNowInProgress) return 'Revisando…'
      if (this.runNowThrottleSecs > 0) return 'Espera ' + this.runNowThrottleSecs + 's'
      return 'Revisar bandeja ahora'
    },
    runNowTooltip() {
      if (this.runNowInProgress) {
        return 'Conectando al buzón IMAP y procesando con IA…'
      }
      if (this.runNowThrottleSecs > 0) {
        return 'Throttle anti-abuso: máximo 1 revisión cada 60s. Reintenta en ' + this.runNowThrottleSecs + 's.'
      }
      return 'Fuerza al sistema a leer la bandeja IMAP ahora mismo, sin esperar al cron de 5 min.'
    },
  },
  watch: {
    autoRefresh(val) {
      if (val) this.startAutoRefresh()
      else this.stopAutoRefresh()
    },
  },
  created() {
    this.loadAll()
    this.startAutoRefresh()
  },
  beforeDestroy() {
    this.stopAutoRefresh()
    if (this.runNowThrottleTimer) clearInterval(this.runNowThrottleTimer)
  },
  methods: {
    startAutoRefresh() {
      this.stopAutoRefresh()
      // Intervalo de 25s — suficiente para enterarse de drafts nuevos sin
      // ser molesto cuando estás leyendo un correo expandido.
      this.refreshTimer = setInterval(() => {
        if (document.visibilityState !== 'visible') return

        // Siempre actualizamos el badge (es barato y no altera la lista visible)
        this.loadCount()

        // Pero el refresh de la lista solo lo hacemos si:
        //  - el usuario NO está leyendo un correo expandido (no interrumpir)
        //  - el usuario NO está editando o rechazando un draft (modales abiertos)
        //  - estamos en filtro pending (donde sí interesa ver nuevos)
        if (this.hasExpandedRow || this.editVisible || this.rejectVisible) {
          return
        }
        if (this.filterStatus === 'pending' || !this.filterStatus) {
          this.loadList({ silent: true })
        }
      }, 25000)
    },
    stopAutoRefresh() {
      if (this.refreshTimer) {
        clearInterval(this.refreshTimer)
        this.refreshTimer = null
      }
    },

    async loadAll() {
      await Promise.all([this.loadList(), this.loadCount()])
      this.lastRefresh = new Date()
    },

    /**
     * @param {Object} opts
     * @param {boolean} opts.silent  Si true, no muestra el spinner de loading
     *                                (auto-refresh debe ser invisible).
     */
    async loadList(opts) {
      const silent = opts && opts.silent
      if (!silent) this.loading = true
      try {
        const params = { page: this.page, per_page: 20 }
        if (this.filterStatus) params.status = this.filterStatus
        if (this.filterClassification) params.classification = this.filterClassification
        if (this.filterSearch && this.filterSearch.trim()) params.search = this.filterSearch.trim()

        // Guardar qué cards estaban expandidas ANTES de reemplazar la lista,
        // para restaurar el estado tras el refresh y no interrumpir al usuario.
        const expandedIds = new Set(
          (this.rows || []).filter(r => r.__expanded).map(r => r.id)
        )

        const r = await this.$http.get('/marketing/email-triage/drafts', { params })
        if (r.data && r.data.ok) {
          const drafts = r.data.drafts || {}
          const newRows = drafts.data || []
          // Restaurar el flag __expanded en los rows que estaban abiertos
          if (expandedIds.size > 0) {
            newRows.forEach(row => {
              if (expandedIds.has(row.id)) {
                this.$set(row, '__expanded', true)
              }
            })
          }
          this.rows = newRows
          this.pagination = {
            current_page: drafts.current_page || 1,
            last_page:    drafts.last_page    || 1,
            per_page:     drafts.per_page     || 20,
            total:        drafts.total        || 0,
          }
        }
      } catch (e) {
        // silenciar
      } finally {
        if (!silent) this.loading = false
      }
    },

    async loadCount() {
      try {
        const r = await this.$http.get('/marketing/email-triage/drafts/count-pending')
        this.pendingCount = (r.data && r.data.count) || 0
      } catch (e) { /* silenciar */ }
    },

    changePage(p) {
      this.page = p
      this.loadList()
    },

    statusTag(status) {
      const map = {
        pending:    { label: 'Pendiente',    type: 'warning' },
        edited:     { label: 'Editado',      type: 'warning' },
        approved:   { label: 'Aprobado',     type: 'success' },
        rejected:   { label: 'Rechazado',    type: 'info' },
        sent:       { label: 'Enviado',      type: 'success' },
        send_error: { label: 'Error envío',  type: 'danger' },
      }
      return map[status] || { label: status, type: 'info' }
    },

    classificationLabel(c) {
      const map = {
        rfq:           'RFQ',
        consulta:      'Consulta',
        no_relacionado:'No relacionado',
        spam:          'Spam',
        otro:          'Otro',
      }
      return map[c] || c
    },

    classificationTag(c) {
      const map = {
        rfq:            'success',
        consulta:       'primary',
        no_relacionado: 'info',
        spam:           'danger',
        otro:           'info',
      }
      return map[c] || 'info'
    },

    formatTime(iso) {
      if (!iso) return ''
      const d = new Date(iso.replace(' ', 'T'))
      if (isNaN(d.getTime())) return iso
      const today = new Date()
      const sameDay = d.toDateString() === today.toDateString()
      return sameDay ? d.toLocaleTimeString() : d.toLocaleString()
    },

    async approve(row) {
      this.actingId = row.id
      try {
        const r = await this.$http.post('/marketing/email-triage/drafts/' + row.id + '/approve')
        if (r.data && r.data.ok) {
          this.$message.success('Borrador aprobado. Ahora podés enviarlo desde el botón "Enviar ahora".')
        } else {
          this.$message.error((r.data && r.data.message) || 'No se pudo aprobar')
        }
        await this.loadAll()
      } catch (e) {
        const msg = (e.response && e.response.data && e.response.data.message) || ''
        this.$message.error('Error al aprobar' + (msg ? ': ' + msg : ''))
      } finally {
        this.actingId = null
      }
    },

    openEdit(row) {
      this.editTarget = row
      this.editSubject = row.draft_subject || ''
      this.editBody = row.draft_body || ''
      this.editTo = row.to_email || ''
      this.editCcRaw = (row.cc_emails || []).join(', ')
      this.editVisible = true
    },

    async submitEdit() {
      if (!this.editSubject || !this.editSubject.trim()) {
        this.$message.error('El asunto no puede estar vacío')
        return
      }
      if (!this.editBody || !this.editBody.trim()) {
        this.$message.error('El cuerpo no puede estar vacío')
        return
      }
      if (!this.editTo || !this.editTo.trim()) {
        this.$message.error('Falta el destinatario')
        return
      }

      const payload = {
        draft_subject: this.editSubject.trim(),
        draft_body:    this.editBody,
        to_email:      this.editTo.trim(),
        cc_emails:     this.editCcRaw
          ? this.editCcRaw.split(',').map(s => s.trim()).filter(Boolean)
          : [],
      }

      this.actingId = this.editTarget.id
      try {
        const r = await this.$http.put('/marketing/email-triage/drafts/' + this.editTarget.id, payload)
        if (r.data && r.data.ok) {
          this.$message.success('Cambios guardados')
          this.editVisible = false
        } else {
          this.$message.error((r.data && r.data.message) || 'No se pudieron guardar los cambios')
        }
        await this.loadAll()
      } catch (e) {
        const errs = (e.response && e.response.data && e.response.data.errors) || null
        if (errs) {
          const firstKey = Object.keys(errs)[0]
          this.$message.error(errs[firstKey][0])
        } else {
          this.$message.error('Error al guardar')
        }
      } finally {
        this.actingId = null
      }
    },

    openReject(row) {
      this.rejectTarget = row
      this.rejectReason = ''
      this.rejectVisible = true
    },

    async submitReject() {
      this.actingId = this.rejectTarget.id
      try {
        const r = await this.$http.post('/marketing/email-triage/drafts/' + this.rejectTarget.id + '/reject', {
          reason: this.rejectReason || null,
        })
        if (r.data && r.data.ok) {
          this.$message.info('Borrador rechazado, nada se envió al cliente')
          this.rejectVisible = false
        } else {
          this.$message.error((r.data && r.data.message) || 'No se pudo rechazar')
        }
        await this.loadAll()
      } catch (e) {
        this.$message.error('Error al rechazar')
      } finally {
        this.actingId = null
      }
    },

    async sendNow(row) {
      this.actingId = row.id
      try {
        const r = await this.$http.post('/marketing/email-triage/drafts/' + row.id + '/send')
        if (r.data && r.data.ok) {
          this.$message.success('Correo enviado exitosamente.')
        } else {
          this.$message.error((r.data && r.data.message) || 'No se pudo enviar')
        }
        await this.loadAll()
      } catch (e) {
        const msg = (e.response && e.response.data && e.response.data.message) || ''
        this.$message.error('Error al enviar' + (msg ? ': ' + msg : ''))
      } finally {
        this.actingId = null
      }
    },

    /**
     * Dispara el reader IMAP + clasificador IA ad-hoc, sin esperar al cron.
     * El backend tiene throttle: máximo 1 corrida cada 60s por tenant.
     */
    async runNow() {
      this.runNowInProgress = true
      try {
        const r = await this.$http.post('/marketing/email-triage/run-now', {})
        if (r.data && r.data.ok) {
          this.$notify({
            title: 'Bandeja revisada',
            message: r.data.message || 'Revisión completada.',
            type: 'success',
            duration: 6000,
          })
        } else {
          this.$message.warning((r.data && r.data.message) || 'No se pudo revisar la bandeja.')
        }
        await this.loadAll()
        // Arrancamos la cuenta regresiva del throttle (60s)
        this.startRunNowThrottle(60)
      } catch (e) {
        const status = e.response && e.response.status
        const data   = e.response && e.response.data
        if (status === 429 && data) {
          // Throttle del backend — arrancamos cuenta regresiva con segundos restantes
          this.startRunNowThrottle(data.seconds_left || 60)
          this.$message.warning(data.message || 'Throttle activo, esperá unos segundos.')
        } else {
          const msg = (data && data.message) || ''
          this.$message.error('Error al revisar bandeja' + (msg ? ': ' + msg : ''))
        }
      } finally {
        this.runNowInProgress = false
      }
    },

    /**
     * Arrancar cuenta regresiva del throttle del botón "Revisar bandeja ahora".
     */
    startRunNowThrottle(seconds) {
      if (this.runNowThrottleTimer) clearInterval(this.runNowThrottleTimer)
      this.runNowThrottleSecs = seconds
      this.runNowThrottleTimer = setInterval(() => {
        if (this.runNowThrottleSecs > 0) {
          this.runNowThrottleSecs -= 1
        } else {
          clearInterval(this.runNowThrottleTimer)
          this.runNowThrottleTimer = null
        }
      }, 1000)
    },
  },
}
</script>

<style scoped>
.email-triage-pending { padding: 16px; }

.etp-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  margin-bottom: 16px;
}
.etp-title { margin: 0 0 4px 0; font-size: 22px; font-weight: 600; }
.etp-subtitle { margin: 0; color: #909399; font-size: 13px; max-width: 760px; }
.etp-badge {
  display: inline-block; margin-left: 8px; padding: 2px 8px;
  background: #f56c6c; color: #fff; border-radius: 10px; font-size: 11px;
}
.etp-actions { display: flex; gap: 12px; align-items: center; }

.etp-empty { text-align: center; padding: 40px 16px; }
.etp-list { display: flex; flex-direction: column; gap: 12px; }

.etp-card { transition: box-shadow 0.2s; }
.etp-card--pending    { border-left: 4px solid #e6a23c; }
.etp-card--edited     { border-left: 4px solid #e6a23c; }
.etp-card--approved   { border-left: 4px solid #409eff; }
.etp-card--sent       { border-left: 4px solid #67c23a; opacity: 0.85; }
.etp-card--rejected   { border-left: 4px solid #909399; opacity: 0.7; }
.etp-card--send_error { border-left: 4px solid #f56c6c; }

.etp-card-head {
  display: flex; justify-content: space-between; align-items: center;
}
.etp-card-head-left { display: flex; align-items: center; flex-wrap: wrap; }
.etp-card-head-right { color: #909399; font-size: 12px; }

.etp-block { margin: 10px 0; padding: 10px 12px; border-radius: 6px; }
.etp-block--original { background: #f4f4f5; }
.etp-block--draft    { background: #ecf5ff; border-left: 3px solid #409eff; }
.etp-label {
  font-size: 11px; color: #909399; margin-bottom: 6px;
  text-transform: uppercase; letter-spacing: 0.5px;
}

.etp-orig-subject { font-size: 13px; color: #303133; margin-bottom: 4px; }
.etp-orig-body {
  margin-top: 8px; padding: 8px 10px; background: #fff;
  border-radius: 4px; font-size: 13px; color: #606266;
  white-space: pre-wrap; word-wrap: break-word;
  max-height: 240px; overflow-y: auto;
}

.etp-draft-meta {
  font-size: 12px; color: #606266; margin-bottom: 4px;
  display: flex; gap: 16px; flex-wrap: wrap;
}
.etp-draft-subject { font-size: 14px; color: #303133; margin-bottom: 6px; }
.etp-draft-body {
  font-size: 14px; line-height: 1.5; color: #303133;
  white-space: pre-wrap; word-wrap: break-word;
}
.etp-data-gaps {
  margin-top: 8px; padding: 6px 10px; background: #fdf6ec; color: #b88230;
  border-left: 3px solid #e6a23c; border-radius: 0 4px 4px 0; font-size: 12px;
}

.etp-status-info {
  margin-top: 10px; padding: 8px 12px; border-radius: 6px;
  font-size: 13px;
}
.etp-status-info--ok     { background: #f0f9eb; color: #67c23a; }
.etp-status-info--reject { background: #f4f4f5; color: #909399; }
.etp-status-info--fail   { background: #fef0f0; color: #f56c6c; }
.etp-status-info--info   { background: #ecf5ff; color: #409eff; }

.etp-card-actions {
  margin-top: 14px; padding-top: 12px; border-top: 1px solid #ebeef5;
  display: flex; gap: 8px; flex-wrap: wrap;
}

.etp-tip {
  background: #f4f8fb; border-left: 3px solid #409EFF; color: #606266;
  padding: 10px 14px; font-size: 13px; margin: 0 0 14px 0; border-radius: 0 4px 4px 0;
}

.etp-pagination {
  margin-top: 16px; text-align: center;
}
</style>
