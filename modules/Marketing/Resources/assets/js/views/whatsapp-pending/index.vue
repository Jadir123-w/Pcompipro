<template>
  <div class="wa-pending">
    <div class="wp-header">
      <div>
        <h2 class="wp-title">📱 WhatsApp — Aprobaciones pendientes</h2>
        <p class="wp-subtitle">
          Las respuestas del bot esperan tu aprobación antes de enviarse al cliente.
          <strong v-if="pendingCount > 0" class="wp-badge">{{ pendingCount }} pendiente(s)</strong>
        </p>
      </div>
      <div class="wp-actions">
        <el-tooltip content="Auto-refresh cada 8 segundos" placement="left">
          <el-switch v-model="autoRefresh" active-text="Auto"></el-switch>
        </el-tooltip>
        <el-button size="small" icon="el-icon-refresh" @click="loadAll" :loading="loading">Actualizar</el-button>
      </div>
    </div>

    <el-row :gutter="12" style="margin-bottom: 12px;">
      <el-col :span="6">
        <el-select size="small" v-model="filterStatus" placeholder="Todos los estados" clearable style="width: 100%;" @change="loadAll">
          <el-option label="Pendientes" value="pending"></el-option>
          <el-option label="Aprobados (en tránsito)" value="approved"></el-option>
          <el-option label="Editados (en tránsito)" value="edited"></el-option>
          <el-option label="Enviando" value="sending"></el-option>
          <el-option label="Enviados" value="sent"></el-option>
          <el-option label="Rechazados" value="rejected"></el-option>
          <el-option label="Fallidos" value="failed"></el-option>
        </el-select>
      </el-col>
      <el-col :span="6">
        <el-input size="small" v-model="filterWaId" placeholder="Filtrar por número WhatsApp" clearable @clear="loadAll" @keyup.enter.native="loadAll"></el-input>
      </el-col>
      <el-col :span="12" style="text-align: right; line-height: 32px; color: #909399; font-size: 12px;">
        Última actualización: {{ lastRefreshLabel }}
      </el-col>
    </el-row>

    <el-card v-if="!loading && rows.length === 0" class="wp-empty">
      <i class="el-icon-success" style="font-size: 48px; color: #67c23a;"></i>
      <div style="margin-top: 12px; font-size: 16px; color: #606266;">
        No hay mensajes pendientes de aprobación.
      </div>
      <div style="margin-top: 6px; color: #909399; font-size: 13px;">
        Cuando un usuario escriba al bot por WhatsApp, su respuesta aparecerá acá para que la revises.
      </div>
    </el-card>

    <div v-else class="wp-list" v-loading="loading">
      <el-card v-for="row in rows" :key="row.id" class="wp-card" :class="['wp-card--' + row.status]">
        <!-- Header de la tarjeta -->
        <div slot="header" class="wp-card-head">
          <div>
            <strong>{{ row.contact_name || row.wa_id }}</strong>
            <span style="color: #909399; font-size: 12px; margin-left: 8px;">+{{ row.wa_id }}</span>
            <el-tag size="mini" :type="statusTag(row.status).type" effect="plain" style="margin-left: 8px;">
              {{ statusTag(row.status).label }}
            </el-tag>
            <el-tag v-if="row.ai_intent" size="mini" type="info" effect="plain" style="margin-left: 4px;">
              {{ row.ai_intent }}
            </el-tag>
          </div>
          <div style="color: #909399; font-size: 12px;">
            {{ formatTime(row.created_at) }}
          </div>
        </div>

        <!-- Mensaje del usuario -->
        <div class="wp-block">
          <div class="wp-label">
            <i class="el-icon-user"></i> Cliente escribió:
          </div>
          <div class="wp-bubble wp-bubble--user">{{ row.user_message }}</div>
        </div>

        <!-- Respuesta del bot -->
        <div class="wp-block">
          <div class="wp-label">
            <i class="el-icon-chat-line-square"></i> Bot propone responder:
          </div>
          <div class="wp-bubble wp-bubble--bot">{{ row.ai_reply }}</div>
          <div v-if="row.catalog_url" class="wp-catalog-hint">
            📎 Adjunta también: catálogo {{ row.catalog_line || 'general' }}
            (<a :href="row.catalog_url" target="_blank" rel="noopener">ver PDF</a>)
          </div>
        </div>

        <!-- Si está editado, mostrar el final_reply -->
        <div v-if="row.status === 'edited' || row.status === 'sent' || row.status === 'sending'" class="wp-block">
          <div class="wp-label" v-if="row.final_reply && row.final_reply !== row.ai_reply">
            <i class="el-icon-edit-outline"></i> Texto final enviado (editado):
          </div>
          <div v-if="row.final_reply && row.final_reply !== row.ai_reply" class="wp-bubble wp-bubble--final">
            {{ row.final_reply }}
          </div>
        </div>

        <!-- Status info -->
        <div v-if="row.status === 'sent'" class="wp-status-info wp-status-info--ok">
          ✅ Enviado
          <span v-if="row.approver_name"> por <strong>{{ row.approver_name }}</strong></span>
          {{ row.sent_at ? '· ' + formatTime(row.sent_at) : '' }}
        </div>
        <div v-if="row.status === 'rejected'" class="wp-status-info wp-status-info--reject">
          ❌ Rechazado
          <span v-if="row.approver_name"> por <strong>{{ row.approver_name }}</strong></span>
          <span v-if="row.rejection_reason"> · "{{ row.rejection_reason }}"</span>
        </div>
        <div v-if="row.status === 'failed'" class="wp-status-info wp-status-info--fail">
          ⚠️ Falló el envío: <strong>{{ row.error_message }}</strong>
        </div>

        <!-- Acciones -->
        <div v-if="row.status === 'pending'" class="wp-card-actions">
          <el-button type="success" size="small" icon="el-icon-check" :loading="actingId === row.id" @click="approve(row)">Aprobar y enviar</el-button>
          <el-button size="small" icon="el-icon-edit" @click="openEdit(row)">Editar antes de enviar</el-button>
          <el-button type="danger" size="small" icon="el-icon-close" @click="openReject(row)">Rechazar</el-button>
        </div>
        <div v-if="row.status === 'failed'" class="wp-card-actions">
          <el-button type="warning" size="small" icon="el-icon-refresh-right" :loading="actingId === row.id" @click="retry(row)">Reintentar envío</el-button>
        </div>
      </el-card>
    </div>

    <!-- Modal: editar texto antes de aprobar -->
    <el-dialog title="Editar respuesta antes de enviar" :visible.sync="editVisible" width="540px" custom-class="wp-edit-dialog" append-to-body>
      <p class="wp-tip" v-if="editTarget">
        Este texto reemplaza al sugerido por el bot. Cuando hagas click en "Enviar editado", se mandará al cliente <strong>{{ editTarget.contact_name || editTarget.wa_id }}</strong>.
      </p>
      <el-input type="textarea" :rows="5" v-model="editText" placeholder="Texto a enviar..." :maxlength="1500" show-word-limit></el-input>
      <div slot="footer">
        <el-button @click="editVisible = false">Cancelar</el-button>
        <el-button type="success" icon="el-icon-check" :loading="actingId === (editTarget && editTarget.id)" @click="submitEdit">Enviar editado</el-button>
      </div>
    </el-dialog>

    <!-- Modal: rechazar con motivo -->
    <el-dialog title="Rechazar respuesta" :visible.sync="rejectVisible" width="440px" custom-class="wp-edit-dialog" append-to-body>
      <p class="wp-tip" v-if="rejectTarget">
        Si rechazás, NO se enviará nada al cliente <strong>{{ rejectTarget.contact_name || rejectTarget.wa_id }}</strong>. El motivo es opcional, queda como auditoría.
      </p>
      <el-input v-model="rejectReason" placeholder="Motivo (opcional)" :maxlength="200" show-word-limit></el-input>
      <div slot="footer">
        <el-button @click="rejectVisible = false">Cancelar</el-button>
        <el-button type="danger" icon="el-icon-close" :loading="actingId === (rejectTarget && rejectTarget.id)" @click="submitReject">Rechazar</el-button>
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
      filterStatus: 'pending',
      filterWaId: '',
      autoRefresh: true,
      refreshTimer: null,
      lastRefresh: null,
      actingId: null,
      // Edit modal
      editVisible: false,
      editTarget: null,
      editText: '',
      // Reject modal
      rejectVisible: false,
      rejectTarget: null,
      rejectReason: '',
    }
  },
  computed: {
    lastRefreshLabel() {
      if (!this.lastRefresh) return '—'
      const d = this.lastRefresh
      return d.toLocaleTimeString()
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
  },
  methods: {
    startAutoRefresh() {
      this.stopAutoRefresh()
      this.refreshTimer = setInterval(() => {
        // Refresca solo si la pestaña está visible para no quemar pedidos en background
        if (document.visibilityState === 'visible') {
          this.loadCount()
          // Si el usuario está mirando "pending", refrescamos también la lista
          if (this.filterStatus === 'pending' || !this.filterStatus) {
            this.loadList()
          }
        }
      }, 8000)
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

    async loadList() {
      this.loading = true
      try {
        const params = {}
        if (this.filterStatus) params.status = this.filterStatus
        if (this.filterWaId)   params.wa_id = this.filterWaId.trim()
        const r = await this.$http.get('/marketing/whatsapp-pending/list', { params })
        this.rows = (r.data && r.data.pending) || []
      } catch (e) {
        // silenciar errores transitorios
      } finally {
        this.loading = false
      }
    },

    async loadCount() {
      try {
        const r = await this.$http.get('/marketing/whatsapp-pending/count')
        this.pendingCount = (r.data && r.data.count) || 0
      } catch (e) { /* silenciar */ }
    },

    statusTag(status) {
      const map = {
        pending:  { label: 'Pendiente',     type: 'warning' },
        approved: { label: 'Aprobado',      type: 'success' },
        edited:   { label: 'Editado',       type: 'success' },
        sending:  { label: 'Enviando…',     type: 'primary' },
        sent:     { label: 'Enviado',       type: 'success' },
        rejected: { label: 'Rechazado',     type: 'info' },
        failed:   { label: 'Falló envío',   type: 'danger' },
      }
      return map[status] || { label: status, type: 'info' }
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
        const r = await this.$http.post('/marketing/whatsapp-pending/' + row.id + '/approve')
        if (r.data && r.data.ok) {
          this.$message.success('Mensaje enviado al cliente')
        } else {
          this.$message.error((r.data && r.data.message) || 'No se pudo aprobar')
        }
        await this.loadAll()
      } catch (e) {
        this.$message.error('Error al aprobar: ' + (e.response && e.response.data && e.response.data.message || ''))
      } finally {
        this.actingId = null
      }
    },

    openEdit(row) {
      this.editTarget = row
      this.editText = row.ai_reply
      this.editVisible = true
    },

    async submitEdit() {
      if (!this.editText || !this.editText.trim()) {
        this.$message.error('El texto editado no puede estar vacío')
        return
      }
      this.actingId = this.editTarget.id
      try {
        const r = await this.$http.post('/marketing/whatsapp-pending/' + this.editTarget.id + '/edit', {
          final_reply: this.editText.trim()
        })
        if (r.data && r.data.ok) {
          this.$message.success('Mensaje editado y enviado')
          this.editVisible = false
        } else {
          this.$message.error((r.data && r.data.message) || 'No se pudo enviar')
        }
        await this.loadAll()
      } catch (e) {
        this.$message.error('Error al editar/enviar: ' + (e.response && e.response.data && e.response.data.message || ''))
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
        const r = await this.$http.post('/marketing/whatsapp-pending/' + this.rejectTarget.id + '/reject', {
          reason: this.rejectReason || null
        })
        if (r.data && r.data.ok) {
          this.$message.info('Mensaje rechazado, no se envió')
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

    async retry(row) {
      this.actingId = row.id
      try {
        const r = await this.$http.post('/marketing/whatsapp-pending/' + row.id + '/retry')
        if (r.data && r.data.ok) {
          this.$message.success('Reenvío exitoso')
        } else {
          this.$message.error((r.data && r.data.message) || 'Falló el reenvío')
        }
        await this.loadAll()
      } catch (e) {
        this.$message.error('Error al reintentar')
      } finally {
        this.actingId = null
      }
    },
  },
}
</script>

<style scoped>
.wa-pending { padding: 16px; }
.wp-header {
  display: flex; justify-content: space-between; align-items: flex-start;
  margin-bottom: 16px;
}
.wp-title { margin: 0 0 4px 0; font-size: 22px; font-weight: 600; }
.wp-subtitle { margin: 0; color: #909399; font-size: 13px; }
.wp-badge {
  display: inline-block; margin-left: 8px; padding: 2px 8px;
  background: #f56c6c; color: #fff; border-radius: 10px; font-size: 11px;
}
.wp-actions { display: flex; gap: 12px; align-items: center; }
.wp-empty { text-align: center; padding: 40px 16px; }
.wp-list { display: flex; flex-direction: column; gap: 12px; }
.wp-card { transition: box-shadow 0.2s; }
.wp-card--pending { border-left: 4px solid #e6a23c; }
.wp-card--sent     { border-left: 4px solid #67c23a; opacity: 0.85; }
.wp-card--rejected { border-left: 4px solid #909399; opacity: 0.7; }
.wp-card--failed   { border-left: 4px solid #f56c6c; }
.wp-card--sending  { border-left: 4px solid #409eff; }
.wp-card-head {
  display: flex; justify-content: space-between; align-items: center;
}
.wp-block { margin: 10px 0; }
.wp-label {
  font-size: 11px; color: #909399; margin-bottom: 4px;
  text-transform: uppercase; letter-spacing: 0.5px;
}
.wp-bubble {
  padding: 10px 14px; border-radius: 10px; font-size: 14px;
  line-height: 1.45; white-space: pre-wrap; word-wrap: break-word;
}
.wp-bubble--user { background: #f4f4f5; color: #303133; }
.wp-bubble--bot  { background: #e8f4fd; color: #1f4068; border-left: 3px solid #409eff; }
.wp-bubble--final { background: #f0f9eb; color: #2d4d1f; border-left: 3px solid #67c23a; }
.wp-catalog-hint { margin-top: 6px; font-size: 12px; color: #606266; }
.wp-status-info {
  margin-top: 10px; padding: 8px 12px; border-radius: 6px;
  font-size: 13px;
}
.wp-status-info--ok    { background: #f0f9eb; color: #67c23a; }
.wp-status-info--reject { background: #f4f4f5; color: #909399; }
.wp-status-info--fail   { background: #fef0f0; color: #f56c6c; }
.wp-card-actions {
  margin-top: 14px; padding-top: 12px; border-top: 1px solid #ebeef5;
  display: flex; gap: 8px; flex-wrap: wrap;
}
.wp-tip {
  background: #f4f8fb; border-left: 3px solid #409EFF; color: #606266;
  padding: 10px 14px; font-size: 13px; margin: 0 0 14px 0; border-radius: 0 4px 4px 0;
}
</style>
