<template>
  <div>
    <el-row :gutter="15" class="mb-3">
      <el-col :span="6">
        <el-select v-model="filters.estado" clearable placeholder="Estado" @change="load">
          <el-option v-for="e in estados" :key="e" :label="e" :value="e"></el-option>
        </el-select>
      </el-col>
      <el-col :span="4">
        <el-button icon="el-icon-refresh" @click="load">Recargar</el-button>
      </el-col>
      <el-col :span="14" style="text-align:right">
        <el-button type="primary" icon="el-icon-plus" @click="showCreateDialog = true">
          Registrar feria
        </el-button>
      </el-col>
    </el-row>

    <el-alert type="info" :closable="false" class="mb-3">
      <strong>Skeleton:</strong> Este modulo recibe ferias manualmente. El "agente de ferias" automatizado no esta en el alcance actual y se notificara via API externa cuando exista.
    </el-alert>

    <el-table :data="list" stripe border v-loading="loading" empty-text="Sin ferias registradas">
      <el-table-column prop="id" label="ID" width="60"></el-table-column>
      <el-table-column prop="nombre" label="Nombre" min-width="200"></el-table-column>
      <el-table-column prop="fecha" label="Fecha" width="110">
        <template slot-scope="scope">
          {{ scope.row.fecha ? scope.row.fecha.substring(0, 10) : '' }}
        </template>
      </el-table-column>
      <el-table-column prop="sector" label="Sector" width="130"></el-table-column>
      <el-table-column prop="ciudad" label="Ciudad" width="100"></el-table-column>
      <el-table-column prop="tipo_participacion" label="Participacion" width="130"></el-table-column>
      <el-table-column prop="fase_editorial_actual" label="Fase actual" width="100" align="center">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.fase_editorial_actual" size="mini">{{ scope.row.fase_editorial_actual }}</el-tag>
          <span v-else>-</span>
        </template>
      </el-table-column>
      <el-table-column label="Piezas generadas" width="150">
        <template slot-scope="scope">
          <el-tag :type="scope.row.piezas_2m_generadas ? 'success' : 'info'" size="mini">2m</el-tag>
          <el-tag :type="scope.row.piezas_1m_generadas ? 'success' : 'info'" size="mini" style="margin-left:2px">1m</el-tag>
          <el-tag :type="scope.row.piezas_15d_generadas ? 'success' : 'info'" size="mini" style="margin-left:2px">15d</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Estado" width="130">
        <template slot-scope="scope">
          <el-tag :type="estadoType(scope.row.estado)" size="small">{{ scope.row.estado }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="180" align="center">
        <template slot-scope="scope">
          <el-button size="mini" type="primary" icon="el-icon-magic-stick"
                     :loading="generatingId === scope.row.id"
                     @click="generateItems(scope.row)">
            Generar items
          </el-button>
          <el-button size="mini" type="danger" icon="el-icon-delete"
                     @click="deleteFeria(scope.row)"></el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- Dialog: Registrar feria -->
    <el-dialog
      :visible.sync="showCreateDialog"
      top="4vh"
      width="min(780px, 94vw)"
      custom-class="feria-create-dialog"
    >
      <div slot="title" class="feria-dialog-heading">
        <span>
          <strong>Registrar feria</strong>
          <small>Organiza la participación y planifica la comunicación del evento</small>
        </span>
      </div>

      <div class="feria-dialog-content">
        <div class="feria-intro">
          <span class="feria-intro-icon"><i class="el-icon-date"></i></span>
          <span>
            <strong>Prepara la cobertura de tu feria</strong>
            <small>Registra los datos del evento, su objetivo y las redes donde quieres comunicarlo.</small>
          </span>
        </div>

        <el-form :model="form" label-position="top" size="small" class="feria-create-form">
          <section class="feria-form-section">
            <div class="feria-section-heading">
              <span class="feria-section-number">01</span>
              <span>
                <strong>Datos del evento</strong>
                <small>Identifica la feria y cuándo se realizará</small>
              </span>
            </div>
            <el-row :gutter="14">
              <el-col :xs="24" :sm="16">
                <el-form-item label="Nombre de la feria">
                  <el-input v-model="form.nombre" placeholder="Ej. Expoalimentaria 2026"></el-input>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="Fecha">
                  <el-date-picker
                    v-model="form.fecha"
                    type="date"
                    value-format="yyyy-MM-dd"
                    placeholder="Selecciona una fecha"
                    style="width:100%"
                  ></el-date-picker>
                </el-form-item>
              </el-col>
            </el-row>
            <el-row :gutter="14">
              <el-col :xs="24" :sm="12">
                <el-form-item label="Sector">
                  <el-input v-model="form.sector" placeholder="Ej. Alimentos y bebidas"></el-input>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="6">
                <el-form-item label="Ciudad">
                  <el-input v-model="form.ciudad" placeholder="Ciudad"></el-input>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="6">
                <el-form-item label="Tipo de participación">
                  <el-select v-model="form.tipo_participacion" placeholder="Selecciona" style="width:100%">
                    <el-option label="Stand" value="stand"></el-option>
                    <el-option label="Invitado" value="invitado"></el-option>
                    <el-option label="Visitante" value="visitante"></el-option>
                    <el-option label="Ponente" value="ponente"></el-option>
                    <el-option label="Sponsor" value="sponsor"></el-option>
                  </el-select>
                </el-form-item>
              </el-col>
            </el-row>
          </section>

          <section class="feria-form-section">
            <div class="feria-section-heading">
              <span class="feria-section-number">02</span>
              <span>
                <strong>Plan de comunicación</strong>
                <small>Define el propósito y los canales de difusión</small>
              </span>
            </div>
            <el-form-item label="Objetivo comunicacional">
              <el-input
                type="textarea"
                v-model="form.objetivo_comunicacional"
                :rows="2"
                placeholder="¿Qué quieres lograr con la comunicación de esta feria?"
              ></el-input>
            </el-form-item>
            <el-form-item label="Redes sociales">
              <el-checkbox-group v-model="form.redes_sociales_target" class="feria-network-options">
                <el-checkbox label="facebook" border><i class="el-icon-share"></i> Facebook</el-checkbox>
                <el-checkbox label="instagram" border><i class="el-icon-camera"></i> Instagram</el-checkbox>
                <el-checkbox label="linkedin" border><i class="el-icon-user"></i> LinkedIn</el-checkbox>
                <el-checkbox label="tiktok" border><i class="el-icon-video-camera"></i> TikTok</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
          </section>

          <section class="feria-form-section feria-context-section">
            <div class="feria-section-heading">
              <span class="feria-section-number">03</span>
              <span>
                <strong>Contexto interno</strong>
                <small>Información de apoyo para el equipo, no se publica</small>
              </span>
            </div>
            <el-form-item label="Notas internas (opcional)">
              <el-input
                type="textarea"
                v-model="form.contexto_interno"
                :rows="2"
                placeholder="Agrega detalles útiles para preparar la cobertura"
              ></el-input>
            </el-form-item>
          </section>
        </el-form>
      </div>

      <div slot="footer" class="feria-dialog-footer">
        <el-button class="feria-cancel-button" @click="showCreateDialog = false" :disabled="saving">
          Cancelar
        </el-button>
        <el-button
          class="feria-submit-button"
          type="primary"
          @click="create"
          :loading="saving"
          icon="el-icon-date"
        >
          Registrar feria
        </el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  data() {
    return {
      list: [],
      loading: false,
      filters: { estado: '' },
      estados: ['activa', 'en_cobertura', 'concluida', 'cancelada'],
      showCreateDialog: false,
      saving: false,
      generatingId: null,
      form: {
        nombre: '',
        fecha: '',
        sector: '',
        ciudad: '',
        tipo_participacion: 'visitante',
        objetivo_comunicacional: '',
        contexto_interno: '',
        redes_sociales_target: ['facebook', 'instagram', 'linkedin'],
      },
    }
  },
  created() {
    this.load()
  },
  methods: {
    async load() {
      this.loading = true
      try {
        var params = {}
        if (this.filters.estado) params.estado = this.filters.estado
        var r = await this.$http.get('/marketing/mkt03/ferias', { params: params })
        this.list = (r.data.ferias && r.data.ferias.data) || r.data.ferias || []
      } catch (e) {
        this.$message.error('Error cargando ferias')
      }
      this.loading = false
    },

    async deleteFeria(item) {
      try {
        await this.$confirm(
          'Â¿Seguro que quieres eliminar esta feria?',
          'Eliminar',
          { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
        )
      } catch (e) {
        return // el usuario cancela
      }
      try {
        await this.$http.delete('/marketing/mkt03/ferias/' + item.id)
        this.$message.success('Feria eliminada')
        this.load()
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error eliminando'
        this.$message.error(msg)
      }
    },

    async create() {
      const hoy = new Date().toISOString().split('T')[0]
      if (this.form.fecha < hoy) {
        this.$message.warning('La fecha no puede ser anterior a hoy')
        return
      }
      
      if (!this.form.nombre || !this.form.fecha) {
        this.$message.warning('Completa nombre y fecha')
        return
      }
      this.saving = true
      try {
        await this.$http.post('/marketing/mkt03/ferias', this.form)
        this.$message.success('Feria registrada')
        this.showCreateDialog = false
        this.form = {
          nombre: '', fecha: '', sector: '', ciudad: '', tipo_participacion: 'visitante',
          objetivo_comunicacional: '', contexto_interno: '',
          redes_sociales_target: ['facebook', 'instagram', 'linkedin'],
        }
        this.load()
      } catch (e) {
        this.$message.error('Error creando')
      }
      this.saving = false
    },

    async generateItems(feria) {
      const hoy = new Date().toISOString().split('T')[0]
      if (feria.fecha < hoy) {
        this.$message.warning('La fecha de la feria ya pasó')
        return
      }
      this.generatingId = feria.id
      try {
        var r = await this.$http.post('/marketing/mkt03/ferias/' + feria.id + '/generate-items', {})
        this.$message.success('Items creados: ' + r.data.items_creados)
        this.load()
      } catch (e) {
        this.$message.error('Error generando items')
      }
      this.generatingId = null
    },

    estadoType(estado) {
      var map = {
        'activa': 'success', 'en_cobertura': 'warning',
        'concluida': 'info', 'cancelada': 'danger',
      }
      return map[estado] || 'info'
    }
  }
}
</script>

<style scoped>
.feria-create-dialog >>> .el-dialog {
  display: flex;
  max-height: 92vh;
  flex-direction: column;
  overflow: hidden;
  border-radius: 16px;
  box-shadow: 0 24px 70px rgba(15, 23, 42, .24);
}

.feria-create-dialog >>> .el-dialog__header {
  padding: 21px 26px;
  border-bottom: 1px solid #edf0f5;
}

.feria-create-dialog >>> .el-dialog__headerbtn {
  top: 24px;
  right: 24px;
  font-size: 18px;
}

.feria-create-dialog >>> .el-dialog__body {
  display: flex;
  min-height: 0;
  flex: 1;
  padding: 0;
  overflow: hidden;
}

.feria-create-dialog >>> .el-dialog__footer {
  padding: 0;
  border-top: 1px solid #edf0f5;
}

.feria-dialog-heading strong,
.feria-dialog-heading small,
.feria-intro strong,
.feria-intro small,
.feria-section-heading strong,
.feria-section-heading small {
  display: block;
}

.feria-dialog-heading strong {
  color: #182338;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.3;
}

.feria-dialog-heading small {
  margin-top: 4px;
  color: #7b8798;
  font-size: 12px;
  font-weight: 400;
}

.feria-dialog-content {
  width: 100%;
  overflow-y: auto;
  padding: 22px 26px;
  background: #f8fafc;
}

.feria-intro {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 18px;
  background: linear-gradient(110deg, #eef2ff, #eff6ff);
  border: 1px solid #e0e7ff;
  border-radius: 11px;
}

.feria-intro-icon,
.feria-section-number {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
}

.feria-intro-icon {
  width: 36px;
  height: 36px;
  color: #4f46e5;
  background: rgba(255, 255, 255, .8);
  border-radius: 10px;
  font-size: 17px;
}

.feria-intro strong {
  color: #28375a;
  font-size: 13px;
  font-weight: 650;
}

.feria-intro small {
  margin-top: 3px;
  color: #64748b;
  font-size: 12px;
  line-height: 1.45;
}

.feria-form-section {
  padding: 17px 18px 4px;
  margin-bottom: 13px;
  background: #fff;
  border: 1px solid #e7ebf2;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, .025);
}

.feria-section-heading {
  display: flex;
  align-items: center;
  gap: 11px;
  margin-bottom: 16px;
}

.feria-section-number {
  width: 32px;
  height: 32px;
  color: #4f46e5;
  background: #eef2ff;
  border-radius: 9px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .03em;
}

.feria-section-heading strong {
  color: #263449;
  font-size: 13px;
  font-weight: 650;
}

.feria-section-heading small {
  margin-top: 3px;
  color: #8792a2;
  font-size: 11px;
}

.feria-create-form >>> .el-form-item {
  margin-bottom: 13px;
}

.feria-create-form >>> .el-form-item__label {
  display: block;
  width: 100%;
  max-width: 100%;
  padding-bottom: 5px;
  color: #475569;
  font-size: 12px;
  font-weight: 600;
  line-height: 19px;
  white-space: normal;
}

.feria-create-form >>> .el-input__inner,
.feria-create-form >>> .el-textarea__inner {
  color: #263449;
  background: #fbfcfe;
  border-color: #dce3ed;
  border-radius: 8px;
  transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
}

.feria-create-form >>> .el-input__inner:hover,
.feria-create-form >>> .el-textarea__inner:hover {
  border-color: #aab9ce;
}

.feria-create-form >>> .el-input__inner:focus,
.feria-create-form >>> .el-textarea__inner:focus {
  background: #fff;
  border-color: #5283e8;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
}

.feria-network-options {
  display: flex;
  flex-wrap: wrap;
  gap: 9px;
}

.feria-network-options >>> .el-checkbox.is-bordered {
  display: inline-flex;
  height: 38px;
  align-items: center;
  margin: 0;
  padding: 0 12px;
  background: #fbfcfe;
  border-color: #dce3ed;
  border-radius: 8px;
  transition: border-color .2s ease, background .2s ease, box-shadow .2s ease;
}

.feria-network-options >>> .el-checkbox.is-bordered:hover {
  border-color: #aab9ce;
}

.feria-network-options >>> .el-checkbox.is-bordered.is-checked {
  background: #eff6ff;
  border-color: #7ca1eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, .07);
}

.feria-network-options >>> .el-checkbox__label {
  color: #475569;
  font-size: 12px;
}

.feria-network-options >>> .el-checkbox.is-checked .el-checkbox__label,
.feria-network-options >>> .el-checkbox.is-checked i {
  color: #315fb7;
}

.feria-network-options >>> i {
  margin-right: 4px;
}

.feria-context-section {
  margin-bottom: 0;
}

.feria-dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  padding: 15px 26px;
  background: #fff;
}

.feria-dialog-footer >>> .el-button {
  min-width: 112px;
  height: 36px;
  border-radius: 8px;
  font-weight: 600;
}

.feria-submit-button {
  background: linear-gradient(135deg, #4f46e5, #2563eb);
  border-color: #3b5fda;
  box-shadow: 0 4px 10px rgba(59, 95, 218, .18);
}

.feria-submit-button:hover,
.feria-submit-button:focus {
  background: linear-gradient(135deg, #4338ca, #1d4ed8);
  border-color: #3153c8;
}

@media (max-width: 640px) {
  .feria-create-dialog >>> .el-dialog__header {
    padding: 17px 18px;
  }

  .feria-create-dialog >>> .el-dialog__headerbtn {
    top: 19px;
    right: 16px;
  }

  .feria-dialog-content {
    padding: 16px;
  }

  .feria-form-section {
    padding: 14px 13px 3px;
  }

  .feria-network-options {
    gap: 7px;
  }

  .feria-network-options >>> .el-checkbox.is-bordered {
    padding: 0 9px;
  }

  .feria-dialog-footer {
    padding: 12px 16px;
  }
}
</style>
