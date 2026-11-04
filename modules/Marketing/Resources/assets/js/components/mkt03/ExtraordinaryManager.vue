<template>
  <div>
    <el-row :gutter="15" class="mb-3">
      <el-col :span="6">
        <el-select v-model="filters.estado" clearable placeholder="Estado" @change="load">
          <el-option v-for="e in estados" :key="e" :label="e" :value="e"></el-option>
        </el-select>
      </el-col>
      <el-col :span="6">
        <el-select v-model="filters.red_social" clearable placeholder="Red" @change="load">
          <el-option v-for="r in redes" :key="r" :label="r" :value="r"></el-option>
        </el-select>
      </el-col>
      <el-col :span="4">
        <el-button icon="el-icon-refresh" @click="load">Recargar</el-button>
      </el-col>
      <el-col :span="8" style="text-align:right">
        <el-button type="primary" icon="el-icon-plus" @click="showCreateDialog = true">
          Nuevo extraordinario
        </el-button>
      </el-col>
    </el-row>

    <el-table :data="list" stripe border v-loading="loading" empty-text="Sin extraordinarios">
      <el-table-column prop="id" label="ID" width="60"></el-table-column>
      <el-table-column prop="fecha" label="Fecha" width="110">
        <template slot-scope="scope">
          {{ scope.row.fecha ? scope.row.fecha.substring(0, 10) : '' }}
        </template>
      </el-table-column>
      <el-table-column prop="motivo" label="Motivo" min-width="200"></el-table-column>
      <el-table-column prop="red_social" label="Red" width="100">
        <template slot-scope="scope">
          <el-tag size="mini">{{ scope.row.red_social }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="clasificacion" label="Clasificacion" width="130"></el-table-column>
      <el-table-column label="Estado" width="180">
        <template slot-scope="scope">
          <el-tag :type="estadoType(scope.row.estado)" size="small">{{ scope.row.estado }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="A futuras" width="100" align="center">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.aprobado_para_futuras_grillas === true" type="success" size="mini">SI</el-tag>
          <el-tag v-else-if="scope.row.aprobado_para_futuras_grillas === false" type="info" size="mini">NO</el-tag>
          <el-tag v-else type="warning" size="mini">Pendiente</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="220" align="center">
        <template slot-scope="scope">
          <el-button v-if="scope.row.aprobado_para_futuras_grillas === null" size="mini" type="success"
                     @click="decide(scope.row, true)">A futuras</el-button>
          <el-button v-if="scope.row.aprobado_para_futuras_grillas === null" size="mini"
                     @click="decide(scope.row, false)">Descartar</el-button>
          <el-button size="mini" type="warning" icon="el-icon-plus"
                     v-if="scope.row.aprobado_para_futuras_grillas !== null"
                     @click="insertarEnGrillaExistenete(scope.row)">Añadir a grilla</el-button>
          <el-button size="mini" type="danger" icon="el-icon-delete"
                     v-if="scope.row.aprobado_para_futuras_grillas !== null"
                     @click="deleteExtraordinary(scope.row)">Borrar</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- Dialog: Crear extraordinario -->
    <el-dialog
      :visible.sync="showCreateDialog"
      top="6vh"
      width="min(680px, 94vw)"
      custom-class="extraordinary-create-dialog"
    >
      <div slot="title" class="extraordinary-dialog-heading">
        <span>
          <strong>Crear contenido extraordinario</strong>
          <small>Registra una publicación fuera de la planificación habitual</small>
        </span>
      </div>

      <div class="extraordinary-dialog-content">
        <div class="extraordinary-intro">
          <span class="extraordinary-intro-icon"><i class="el-icon-warning-outline"></i></span>
          <span>
            <strong>Una oportunidad para comunicar a tiempo</strong>
            <small>Define cuándo se publicará, en qué red y cuál es el motivo de la solicitud.</small>
          </span>
        </div>

        <el-form :model="form" label-position="top" size="small" class="extraordinary-create-form">
          <section class="extraordinary-form-section">
            <div class="extraordinary-section-heading">
              <span class="extraordinary-section-number">01</span>
              <span>
                <strong>Fecha y canal</strong>
                <small>Indica cuándo y dónde se compartirá el contenido</small>
              </span>
            </div>
            <el-row :gutter="14">
              <el-col :xs="24" :sm="12">
                <el-form-item label="Fecha de publicación">
                  <el-date-picker
                    v-model="form.fecha"
                    type="date"
                    value-format="yyyy-MM-dd"
                    placeholder="Selecciona una fecha"
                    style="width:100%"
                  ></el-date-picker>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="12">
                <el-form-item label="Red social">
                  <el-select v-model="form.red_social" placeholder="Selecciona una red" style="width:100%">
                    <el-option v-for="r in redes" :key="r" :label="r" :value="r"></el-option>
                  </el-select>
                </el-form-item>
              </el-col>
            </el-row>
          </section>

          <section class="extraordinary-form-section">
            <div class="extraordinary-section-heading">
              <span class="extraordinary-section-number">02</span>
              <span>
                <strong>Motivo y clasificación</strong>
                <small>Resume la necesidad y el tipo de comunicación</small>
              </span>
            </div>
            <el-form-item label="Motivo">
              <el-input v-model="form.motivo" placeholder="Ej. Anuncio urgente de lanzamiento"></el-input>
            </el-form-item>
            <el-form-item label="Clasificación">
              <el-select v-model="form.clasificacion" placeholder="Selecciona una clasificación" style="width:100%">
                <el-option label="Urgente" value="urgente"></el-option>
                <el-option label="Oportunidad" value="oportunidad"></el-option>
                <el-option label="Reactivo" value="reactivo"></el-option>
                <el-option label="Promocional" value="promocional"></el-option>
              </el-select>
            </el-form-item>
          </section>

          <section class="extraordinary-form-section extraordinary-description-section">
            <div class="extraordinary-section-heading">
              <span class="extraordinary-section-number">03</span>
              <span>
                <strong>Descripción</strong>
                <small>Añade contexto para facilitar la producción del contenido</small>
              </span>
            </div>
            <el-form-item label="Contexto adicional (opcional)">
              <el-input
                type="textarea"
                v-model="form.descripcion"
                :rows="3"
                placeholder="Incluye detalles, enlaces o indicaciones importantes"
              ></el-input>
            </el-form-item>
          </section>
        </el-form>
      </div>

      <div slot="footer" class="extraordinary-dialog-footer">
        <el-button class="extraordinary-cancel-button" @click="showCreateDialog = false" :disabled="saving">
          Cancelar
        </el-button>
        <el-button
          class="extraordinary-submit-button"
          type="primary"
          @click="create"
          :loading="saving"
          icon="el-icon-plus"
        >
          Crear extraordinario
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
      filters: { estado: '', red_social: '' },
      estados: ['solicitado', 'en_desarrollo', 'entregado', 'aprobado_para_futuras', 'no_aprobado'],
      redes: ['facebook', 'instagram', 'linkedin', 'tiktok'],
      showCreateDialog: false,
      saving: false,
      form: {
        fecha: '',
        red_social: 'facebook',
        motivo: '',
        clasificacion: '',
        descripcion: '',
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
        if (this.filters.red_social) params.red_social = this.filters.red_social
        var r = await this.$http.get('/marketing/mkt03/extraordinary', { params: params })
        this.list = (r.data.extraordinary_content && r.data.extraordinary_content.data)
          || r.data.extraordinary_content || []
      } catch (e) {
        this.$message.error('Error cargando')
      }
      this.loading = false
    },

    async create() {
      if (!this.form.fecha || !this.form.motivo) {
        this.$message.warning('Completa fecha y motivo')
        return
      }
      const hoy = new Date().toISOString().split('T')[0]
      if (this.form.fecha < hoy) {
        this.$message.warning('La fecha no puede ser anterior a hoy')
        return
      }
      this.saving = true
      try {
        await this.$http.post('/marketing/mkt03/extraordinary', this.form)
        this.$message.success('Extraordinario creado')
        this.showCreateDialog = false
        this.form = { fecha: '', red_social: 'facebook', motivo: '', clasificacion: '', descripcion: '' }
        this.load()
      } catch (e) {
        this.$message.error('Error creando')
      }
      this.saving = false
    },

    async decide(extra, approved) {
      try {
        await this.$http.put('/marketing/mkt03/extraordinary/' + extra.id + '/decide', {
          approved: approved,
        })
        this.$message.success(approved ? 'Aprobado para futuras grillas' : 'Descartado')
        this.load()
      } catch (e) {
        this.$message.error('Error en decision')
      }
    },

    async deleteExtraordinary(item) {
      try {
        await this.$confirm(
          'Â¿Seguro que quieres eliminar este extraordinario?',
          'Eliminar',
          { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
        )
      } catch (e) {
        return // el usuario cancela
      }
      try {
        await this.$http.delete('/marketing/mkt03/extraordinary/' + item.id)
        this.$message.success('Extraordinario eliminado')
        this.load()
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error eliminando'
        this.$message.error(msg)
      }
    },

    estadoType(estado) {
      var map = {
        'solicitado': 'info', 'en_desarrollo': 'warning', 'entregado': 'primary',
        'aprobado_para_futuras': 'success', 'no_aprobado': 'info',
      }
      return map[estado] || 'info'
    },

    async insertarEnGrillaExistenete(extra) {
      if (!confirm('¿Estás seguro de añadir este extraordinary a una grilla existente?')) {
        return
      }
      this.saving = true
      try {
        // Llamada al backend para insertar el extraordinary en la grilla
        // Este endpoint debería llamar al método insertExtraordinarioDespuesDeGrilla
        // Por ahora, mostramos un mensaje y recargamos la lista
        await this.$http.post('/marketing/mkt03/extraordinary', {
          // Enviamos los datos necesarios para insertar en grilla
          grid_id: extra.grid_id, // Asumiendo que el grid_id está en el objeto
          extraordinary_id: extra.id,
          fecha_publicacion: extra.fecha,
          item_order: 0, // Se calculará en el backend
          origen: 'extraordinario'
        })
        this.$message.success('Extraordinario añadido a la grilla')
        this.load()
      } catch (e) {
        this.$message.error('Error al añadir a la grilla')
      }
      this.saving = false
    }
  }
}
</script>

<style scoped>
.extraordinary-create-dialog >>> .el-dialog {
  display: flex;
  max-height: 88vh;
  flex-direction: column;
  overflow: hidden;
  border-radius: 16px;
  box-shadow: 0 24px 70px rgba(15, 23, 42, .24);
}

.extraordinary-create-dialog >>> .el-dialog__header {
  padding: 21px 26px;
  border-bottom: 1px solid #edf0f5;
}

.extraordinary-create-dialog >>> .el-dialog__headerbtn {
  top: 24px;
  right: 24px;
  font-size: 18px;
}

.extraordinary-create-dialog >>> .el-dialog__body {
  display: flex;
  min-height: 0;
  flex: 1;
  padding: 0;
  overflow: hidden;
}

.extraordinary-create-dialog >>> .el-dialog__footer {
  padding: 0;
  border-top: 1px solid #edf0f5;
}

.extraordinary-dialog-heading,
.extraordinary-dialog-heading span,
.extraordinary-intro strong,
.extraordinary-intro small,
.extraordinary-section-heading strong,
.extraordinary-section-heading small {
  display: block;
}

.extraordinary-dialog-heading strong {
  color: #182338;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.3;
}

.extraordinary-dialog-heading small {
  margin-top: 4px;
  color: #7b8798;
  font-size: 12px;
  font-weight: 400;
}

.extraordinary-dialog-content {
  width: 100%;
  overflow-y: auto;
  padding: 22px 26px;
  background: #f8fafc;
}

.extraordinary-intro {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 18px;
  background: linear-gradient(110deg, #eef2ff, #eff6ff);
  border: 1px solid #e0e7ff;
  border-radius: 11px;
}

.extraordinary-intro-icon,
.extraordinary-section-number {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
}

.extraordinary-intro-icon {
  width: 36px;
  height: 36px;
  color: #4f46e5;
  background: rgba(255, 255, 255, .8);
  border-radius: 10px;
  font-size: 17px;
}

.extraordinary-intro strong {
  color: #28375a;
  font-size: 13px;
  font-weight: 650;
}

.extraordinary-intro small {
  margin-top: 3px;
  color: #64748b;
  font-size: 12px;
  line-height: 1.45;
}

.extraordinary-form-section {
  padding: 17px 18px 4px;
  margin-bottom: 13px;
  background: #fff;
  border: 1px solid #e7ebf2;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, .025);
}

.extraordinary-section-heading {
  display: flex;
  align-items: center;
  gap: 11px;
  margin-bottom: 16px;
}

.extraordinary-section-number {
  width: 32px;
  height: 32px;
  color: #4f46e5;
  background: #eef2ff;
  border-radius: 9px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .03em;
}

.extraordinary-section-heading strong {
  color: #263449;
  font-size: 13px;
  font-weight: 650;
}

.extraordinary-section-heading small {
  margin-top: 3px;
  color: #8792a2;
  font-size: 11px;
}

.extraordinary-create-form >>> .el-form-item {
  margin-bottom: 13px;
}

.extraordinary-create-form >>> .el-form-item__label {
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

.extraordinary-create-form >>> .el-select,
.extraordinary-create-form >>> .el-date-editor {
  width: 100%;
}

.extraordinary-create-form >>> .el-input__inner,
.extraordinary-create-form >>> .el-textarea__inner {
  color: #263449;
  background: #fbfcfe;
  border-color: #dce3ed;
  border-radius: 8px;
  transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
}

.extraordinary-create-form >>> .el-input__inner:hover,
.extraordinary-create-form >>> .el-textarea__inner:hover {
  border-color: #aab9ce;
}

.extraordinary-create-form >>> .el-input__inner:focus,
.extraordinary-create-form >>> .el-textarea__inner:focus {
  background: #fff;
  border-color: #5283e8;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
}

.extraordinary-description-section {
  margin-bottom: 0;
}

.extraordinary-dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  padding: 15px 26px;
  background: #fff;
}

.extraordinary-dialog-footer >>> .el-button {
  min-width: 112px;
  height: 36px;
  border-radius: 8px;
  font-weight: 600;
}

.extraordinary-submit-button {
  background: linear-gradient(135deg, #4f46e5, #2563eb);
  border-color: #3b5fda;
  box-shadow: 0 4px 10px rgba(59, 95, 218, .18);
}

.extraordinary-submit-button:hover,
.extraordinary-submit-button:focus {
  background: linear-gradient(135deg, #4338ca, #1d4ed8);
  border-color: #3153c8;
}

@media (max-width: 640px) {
  .extraordinary-create-dialog >>> .el-dialog__header {
    padding: 17px 18px;
  }

  .extraordinary-create-dialog >>> .el-dialog__headerbtn {
    top: 19px;
    right: 16px;
  }

  .extraordinary-dialog-content {
    padding: 16px;
  }

  .extraordinary-form-section {
    padding: 14px 13px 3px;
  }

  .extraordinary-dialog-footer {
    padding: 12px 16px;
  }
}
</style>
