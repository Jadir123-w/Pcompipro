<template>
  <div>
    <el-row :gutter="15" class="mb-3">
      <el-col :span="6">
        <el-select v-model="filters.red_social" clearable placeholder="Red social" @change="loadGrids()">
          <el-option v-for="r in redes" :key="r.value" :label="r.label" :value="r.value"></el-option>
        </el-select>
      </el-col>
      <el-col :span="4">
        <el-input-number v-model="filters.anio" :min="2020" :max="2100" placeholder="Ano"
                         @change="loadGrids()"></el-input-number>
      </el-col>
      <el-col :span="4">
        <el-button icon="el-icon-refresh" @click="loadGrids(true)">Recargar</el-button>
      </el-col>
      <el-col :span="10" style="text-align:middle">
        <el-button type="primary" icon="el-icon-magic-stick" @click="showGenerateDialog = true">
          Generar Grilla con IA (Modo A)
        </el-button>
      </el-col>
    </el-row>

    <el-table :data="grids" stripe border v-loading="loading" empty-text="Sin grillas">
      <el-table-column prop="id" label="ID" width="60"></el-table-column>
      <el-table-column prop="titulo" label="Titulo" min-width="200"></el-table-column>
      <el-table-column prop="anio" label="Ano" width="80"></el-table-column>
      <el-table-column prop="red_social" label="Red" width="110">
        <template slot-scope="scope">
          <el-tag size="mini">{{ scope.row.red_social }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Estado" width="130">
        <template slot-scope="scope">
          <el-tag :type="estadoType(scope.row.estado)" size="small">{{ scope.row.estado }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="items_count" label="Items" width="80" align="center"></el-table-column>
      <el-table-column label="Acciones" width="300" align="center">
        <template slot-scope="scope">
          <el-button size="mini" type="primary" icon="el-icon-view" @click="openGrid(scope.row.id)">Ver</el-button>
          <el-button size="mini" icon="el-icon-plus" @click="openExtend(scope.row)">Ampliar</el-button>
          <el-button size="mini" type="danger" icon="el-icon-delete"
                     :loading="deletingGridId === scope.row.id" :disabled="deletingGridId !== null"
                     @click="deleteGrid(scope.row)">Borrar</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- Dialog: Generar grilla -->
    <el-dialog
      :visible.sync="showGenerateDialog"
      top="4vh"
      width="min(900px, 94vw)"
      custom-class="generate-grid-dialog"
    >
      <div slot="title" class="generate-dialog-heading">
        <span class="generate-heading-copy">
          <strong>Generar grilla con IA</strong>
          <small>Configura el periodo, las fechas y las redes para planificar tu contenido</small>
        </span>
      </div>

      <div class="generate-dialog-content">
        <div class="generate-intro">
          <span class="generate-intro-icon"><i class="el-icon-date"></i></span>
          <span>
            <strong>Planificación editorial</strong>
            <small>La IA organizará propuestas de publicaciones según el rango seleccionado.</small>
          </span>
        </div>

        <el-form class="generate-grid-form" :model="genForm" label-position="top" size="small">
          <section class="generate-form-section">
            <div class="generate-section-heading">
              <span class="generate-section-number">01</span>
              <span>
                <strong>Periodo de planificación</strong>
                <small>Define cómo identificar y organizar la grilla</small>
              </span>
            </div>
            <el-row :gutter="14">
              <el-col :xs="24" :sm="8">
                <el-form-item label="Año">
                  <el-input-number v-model="genForm.anio" :min="2026" :max="2100" controls-position="right"></el-input-number>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="Tipo de periodo">
                  <el-select v-model="genForm.tipo_periodo" style="width:100%">
                    <el-option label="Anual" value="anual"></el-option>
                    <el-option label="Trimestral" value="trimestral"></el-option>
                    <el-option label="Semestral" value="semestral"></el-option>
                    <el-option label="Mensual" value="mensual"></el-option>
                    <el-option label="Campaña" value="campania"></el-option>
                  </el-select>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="Identificador">
                  <el-input v-model="genForm.periodo" placeholder="Ej. 2026-Q1"></el-input>
                </el-form-item>
              </el-col>
            </el-row>
            <p class="generate-field-hint"><i class="el-icon-info"></i> Si lo dejas vacío, se utilizará el año como identificador.</p>
          </section>

          <section class="generate-form-section">
            <div class="generate-section-heading">
              <span class="generate-section-number">02</span>
              <span>
                <strong>Rango de fechas</strong>
                <small>Selecciona las fechas que cubrirá la grilla</small>
              </span>
            </div>
            <el-row :gutter="14">
              <el-col :xs="24" :sm="12">
                <el-form-item label="Fecha desde">
                  <el-date-picker v-model="genForm.fecha_desde" type="date" value-format="yyyy-MM-dd"
                                  placeholder="Selecciona fecha inicial" style="width:100%"></el-date-picker>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="12">
                <el-form-item label="Fecha hasta">
                  <el-date-picker v-model="genForm.fecha_hasta" type="date" value-format="yyyy-MM-dd"
                                  placeholder="Selecciona fecha final" style="width:100%"></el-date-picker>
                </el-form-item>
              </el-col>
            </el-row>
          </section>

          <section class="generate-form-section generate-network-section">
            <div class="generate-section-heading">
              <span class="generate-section-number">03</span>
              <span>
                <strong>Redes sociales</strong>
                <small>Elige una o varias redes para generar contenido</small>
              </span>
            </div>
            <el-form-item label="Canales incluidos">
              <el-checkbox-group v-model="genForm.redes" class="generate-network-options">
                <el-checkbox label="facebook" border><i class="el-icon-share"></i> Facebook</el-checkbox>
                <el-checkbox label="instagram" border><i class="el-icon-camera"></i> Instagram</el-checkbox>
                <el-checkbox label="linkedin" border><i class="el-icon-user"></i> LinkedIn</el-checkbox>
                <el-checkbox label="tiktok" border><i class="el-icon-video-camera"></i> TikTok</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
          </section>

          <section class="generate-form-section generate-title-section">
            <div class="generate-section-heading">
              <span class="generate-section-number">04</span>
              <span>
                <strong>Nombre de la grilla</strong>
                <small>Agrega un título para reconocerla fácilmente</small>
              </span>
            </div>
            <el-form-item label="Título (opcional)">
              <el-input v-model="genForm.titulo" placeholder="Ej. Campaña de verano 2026"></el-input>
            </el-form-item>
          </section>
        </el-form>
      </div>

      <div slot="footer" class="generate-dialog-footer">
        <el-button class="generate-cancel-button" @click="showGenerateDialog = false" :disabled="generating">
          Cancelar
        </el-button>
        <el-button class="generate-submit-button" type="primary" @click="generate" :loading="generating" icon="el-icon-magic-stick">
          Generar grilla
        </el-button>
      </div>
    </el-dialog>

    <!-- Dialog: Detalle de grilla con items -->
    <el-dialog :title="'Grilla: ' + (detailGrid ? detailGrid.titulo : '')" :visible.sync="showDetail" width="90%" top="3vh">
      <div v-if="detailGrid" v-loading="loadingDetail">
        <el-row :gutter="10" class="mb-2">
          <el-col :span="6"><strong>Red:</strong> {{ detailGrid.red_social }}</el-col>
          <el-col :span="6"><strong>Ano:</strong> {{ detailGrid.anio }}</el-col>
          <el-col :span="6"><strong>Tipo:</strong> {{ detailGrid.tipo_periodo }}</el-col>
          <el-col :span="6">
            <strong>Estado:</strong>
            <el-tag :type="estadoType(detailGrid.estado)" size="small">{{ detailGrid.estado }}</el-tag>
          </el-col>
        </el-row>

        <el-table :data="detailGrid.items || []" stripe border size="mini" empty-text="Sin items">
          <el-table-column prop="item_order" label="#" width="40" align="center"></el-table-column>
          <el-table-column prop="fecha_publicacion" label="Fecha" width="110">
            <template slot-scope="scope">
              {{ scope.row.fecha_publicacion ? scope.row.fecha_publicacion.substring(0, 10) : '' }}
            </template>
          </el-table-column>
          <el-table-column prop="tematica" label="Tematica" min-width="140"></el-table-column>
          <el-table-column prop="tipo_contenido" label="Tipo" width="120"></el-table-column>
          <el-table-column prop="linea_comercial" label="Linea" width="140"></el-table-column>
          <el-table-column prop="objetivo" label="Objetivo" width="110"></el-table-column>
          <el-table-column prop="origen" label="Origen" width="100">
            <template slot-scope="scope">
              <el-tag size="mini" :type="scope.row.origen === 'feria' ? 'warning' : 'info'">{{ scope.row.origen }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="Acciones" width="140" align="center">
            <template slot-scope="scope">
              <el-tag v-if="Number(scope.row.pieces_count) > 0" type="success" size="small">
                <i class="el-icon-check"></i> Ya producido
              </el-tag>
              <el-button v-else size="mini" type="success" icon="el-icon-magic-stick"
                         @click="producePiece(scope.row)" :loading="producingId === scope.row.id"
                         :disabled="producingId !== null">
                Producir
              </el-button>
            </template>
          </el-table-column>
        </el-table>
      </div>

      <div slot="footer">
        <el-button @click="showDetail = false">Cerrar</el-button>
        <el-button v-if="detailGrid && detailGrid.estado === 'borrador'" type="warning"
                   @click="changeStatus('validada')" icon="el-icon-circle-check">Validar</el-button>
        <el-button v-if="detailGrid && detailGrid.estado === 'validada'" type="success"
                   @click="changeStatus('activa')" icon="el-icon-check">Activar</el-button>
      </div>
    </el-dialog>

    <!-- Dialog: Ampliar grilla -->
    <el-dialog title="Ampliar Grilla (Modo C)" :visible.sync="showExtendDialog" width="450px">
      <el-form :model="extendForm" label-position="top" size="small">
        <p v-if="extendTarget"><strong>Grilla:</strong> {{ extendTarget.titulo }}</p>
        <el-row :gutter="10">
          <el-col :span="12">
            <el-form-item label="Fecha desde">
              <el-date-picker v-model="extendForm.fecha_desde" type="date" value-format="yyyy-MM-dd" style="width:100%"></el-date-picker>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Fecha hasta">
              <el-date-picker v-model="extendForm.fecha_hasta" type="date" value-format="yyyy-MM-dd" style="width:100%"></el-date-picker>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <div slot="footer">
        <el-button @click="showExtendDialog = false">Cancelar</el-button>
        <el-button type="primary" @click="extend" :loading="extending">Ampliar</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  data() {
    return {
      grids: [],
      loading: false,
      gridLoadRequestId: 0,
      deletingGridId: null,
      filters: { red_social: '', anio: null },
      redes: [
        { value: '', label: 'Todas' },
        { value: 'facebook', label: 'Facebook' },
        { value: 'instagram', label: 'Instagram' },
        { value: 'linkedin', label: 'LinkedIn' },
        { value: 'tiktok', label: 'TikTok' },
      ],
      // Generar
      showGenerateDialog: false,
      generating: false,
      genForm: {
        anio: new Date().getFullYear(),
        tipo_periodo: 'mensual',
        periodo: '',
        fecha_desde: '',
        fecha_hasta: '',
        redes: ['facebook'],
        titulo: '',
      },
      // Detail
      showDetail: false,
      detailGrid: null,
      loadingDetail: false,
      producingId: null,
      // Extend
      showExtendDialog: false,
      extending: false,
      extendTarget: null,
      extendForm: { fecha_desde: '', fecha_hasta: '' },
    }
  },
  created() {
    this.loadGrids()
  },
  methods: {
    async loadGrids(preserveOnEmpty) {
      var requestId = ++this.gridLoadRequestId
      this.loading = true
      try {
        var params = { page: 1 }
        if (this.filters.red_social) params.red_social = this.filters.red_social
        if (this.filters.anio) params.anio = this.filters.anio
        var r = await this.$http.get('/marketing/mkt03/grids', { params: params })
        if (requestId !== this.gridLoadRequestId) return

        var grids = r.data.grids
        if (grids && !Array.isArray(grids)) grids = grids.data
        if (!Array.isArray(grids)) throw new Error('Formato de respuesta de grillas no válido')
        if (preserveOnEmpty && grids.length === 0 && this.grids.length > 0) {
          this.$message.warning('El servidor no devolvió grillas; se conserva la lista actual')
          return
        }
        this.grids = grids
      } catch (e) {
        if (requestId === this.gridLoadRequestId) {
          this.$message.error('Error cargando grillas')
        }
      } finally {
        if (requestId === this.gridLoadRequestId) {
          this.loading = false
        }
      }
    },

    async deleteGrid(grid) {
      try {
        await this.$confirm(
          '¿Eliminar la grilla "' + grid.titulo + '"? Esta acción no se puede deshacer desde esta pantalla.',
          'Confirmar eliminación',
          { type: 'warning' }
        )
      } catch (e) {
        if (e !== 'cancel' && e !== 'close') {
          this.$message.error('No se pudo confirmar la eliminación')
        }
        return
      }

      this.deletingGridId = grid.id
      try {
        await this.$http.delete('/marketing/mkt03/grids/' + grid.id)
        this.grids = this.grids.filter(function (item) {
          return String(item.id) !== String(grid.id)
        })
        this.$message.success('Grilla eliminada')
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error eliminando grilla'
        this.$message.error(msg)
      } finally {
        this.deletingGridId = null
      }
    },

    async generate() {
      if (!this.genForm.fecha_desde || !this.genForm.fecha_hasta || !this.genForm.redes.length) {
        this.$message.warning('Completa fechas y al menos una red')
        return
      }
      const hoy = new Date().toISOString().split('T')[0]
      if (this.genForm.fecha_desde < hoy) {
        this.$message.warning('La fecha desde no puede ser anterior a hoy')
        return
      }
      this.generating = true
      try {
        var payload = Object.assign({}, this.genForm)
        if (!payload.periodo) payload.periodo = String(payload.anio)
        var r = await this.$http.post('/marketing/mkt03/grids/generate', payload)
        this.$message.success('Grilla(s) generada(s): ' + (r.data.items_count || 0) + ' items totales')
        this.showGenerateDialog = false
        await this.loadGrids(true)
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error generando'
        this.$message.error(msg)
      } finally {
        this.generating = false
      }
    },

    async openGrid(id) {
      this.showDetail = true
      this.loadingDetail = true
      try {
        var r = await this.$http.get('/marketing/mkt03/grids/' + id)
        this.detailGrid = r.data.grid
      } catch (e) {
        this.$message.error('Error cargando grilla')
      }
      this.loadingDetail = false
    },

    openExtend(grid) {
      this.extendTarget = grid
      this.extendForm = { fecha_desde: '', fecha_hasta: '' }
      this.showExtendDialog = true
    },

    async extend() {
      if (!this.extendForm.fecha_desde || !this.extendForm.fecha_hasta) {
        this.$message.warning('Completa ambas fechas')
        return
      }
      this.extending = true
      try {
        var r = await this.$http.post(
          '/marketing/mkt03/grids/' + this.extendTarget.id + '/extend',
          this.extendForm
        )
        this.$message.success('Grilla ampliada: ' + r.data.items_added + ' items agregados')
        this.showExtendDialog = false
        await this.loadGrids(true)
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error ampliando'
        this.$message.error(msg)
      } finally {
        this.extending = false
      }
    },

    async changeStatus(estado) {
      try {
        await this.$http.put('/marketing/mkt03/grids/' + this.detailGrid.id + '/status', { estado: estado })
        this.$message.success('Estado cambiado a: ' + estado)
        this.openGrid(this.detailGrid.id)
        this.loadGrids()
      } catch (e) {
        this.$message.error('Error cambiando estado')
      }
    },

    async producePiece(item) {
      this.producingId = item.id
      try {
        var r = await this.$http.post('/marketing/mkt03/items/' + item.id + '/produce', {
          generate_image: false,
        })
        this.$set(item, 'pieces_count', Math.max(Number(item.pieces_count) || 0, 1))
        this.$message.success('Pieza producida (ID: ' + r.data.piece.id + ')')
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error produciendo pieza'
        this.$message.error(msg)
      } finally {
        this.producingId = null
      }
    },

    estadoType(estado) {
      var map = {
        'borrador': 'info', 'en_revision': 'warning', 'validada': 'primary',
        'activa': 'success', 'cerrada': 'info',
      }
      return map[estado] || 'info'
    }
  }
}
</script>

<style scoped>
.generate-grid-dialog >>> .el-dialog {
  display: flex;
  max-height: 92vh;
  flex-direction: column;
  overflow: hidden;
  border-radius: 16px;
  box-shadow: 0 24px 70px rgba(15, 23, 42, .24);
}

.generate-grid-dialog >>> .el-dialog__header {
  padding: 21px 26px;
  border-bottom: 1px solid #edf0f5;
}

.generate-grid-dialog >>> .el-dialog__headerbtn {
  top: 24px;
  right: 24px;
  font-size: 18px;
}

.generate-grid-dialog >>> .el-dialog__body {
  display: flex;
  min-height: 0;
  flex: 1;
  padding: 0;
  overflow: hidden;
}

.generate-grid-dialog >>> .el-dialog__footer {
  padding: 0;
  border-top: 1px solid #edf0f5;
}

.generate-dialog-heading {
  display: flex;
  align-items: center;
  gap: 13px;
}

.generate-intro-icon,
.generate-section-number {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
}

.generate-heading-copy {
  padding-right: 28px;
}

.generate-heading-copy strong,
.generate-heading-copy small,
.generate-intro strong,
.generate-intro small,
.generate-section-heading strong,
.generate-section-heading small {
  display: block;
}

.generate-heading-copy strong {
  color: #182338;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.3;
}

.generate-heading-copy small {
  margin-top: 4px;
  color: #7b8798;
  font-size: 12px;
  font-weight: 400;
}

.generate-dialog-content {
  width: 100%;
  overflow-y: auto;
  padding: 22px 26px;
  background: #f8fafc;
}

.generate-intro {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 18px;
  background: linear-gradient(110deg, #eef2ff, #eff6ff);
  border: 1px solid #e0e7ff;
  border-radius: 11px;
}

.generate-intro-icon {
  width: 36px;
  height: 36px;
  color: #4f46e5;
  background: rgba(255, 255, 255, .8);
  border-radius: 10px;
  font-size: 17px;
}

.generate-intro strong {
  color: #28375a;
  font-size: 13px;
  font-weight: 650;
}

.generate-intro small {
  margin-top: 3px;
  color: #64748b;
  font-size: 12px;
  line-height: 1.45;
}

.generate-grid-form {
  display: block;
}

.generate-form-section {
  padding: 17px 18px 4px;
  margin-bottom: 13px;
  background: #fff;
  border: 1px solid #e7ebf2;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, .025);
}

.generate-section-heading {
  display: flex;
  align-items: center;
  gap: 11px;
  margin-bottom: 16px;
}

.generate-section-number {
  width: 32px;
  height: 32px;
  color: #4f46e5;
  background: #eef2ff;
  border-radius: 9px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .03em;
}

.generate-section-heading strong {
  color: #263449;
  font-size: 13px;
  font-weight: 650;
}

.generate-section-heading small {
  margin-top: 3px;
  color: #8792a2;
  font-size: 11px;
}

.generate-grid-form >>> .el-form-item {
  margin-bottom: 13px;
}

.generate-grid-form >>> .el-form-item__label {
  display: block;
  width: 100%;
  max-width: 100%;
  padding-bottom: 5px;
  color: #475569;
  font-size: 12px;
  font-weight: 600;
  line-height: 19px;
  white-space: normal;
  word-break: normal;
}

.generate-grid-form >>> .el-input-number,
.generate-grid-form >>> .el-select,
.generate-grid-form >>> .el-date-editor {
  width: 100%;
}

.generate-grid-form >>> .el-input__inner,
.generate-grid-form >>> .el-textarea__inner {
  color: #263449;
  background: #fbfcfe;
  border-color: #dce3ed;
  border-radius: 8px;
  transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
}

.generate-grid-form >>> .el-input__inner:hover,
.generate-grid-form >>> .el-textarea__inner:hover {
  border-color: #aab9ce;
}

.generate-grid-form >>> .el-input__inner:focus,
.generate-grid-form >>> .el-textarea__inner:focus {
  background: #fff;
  border-color: #5283e8;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
}

.generate-field-hint {
  margin: -5px 0 11px;
  color: #8792a2;
  font-size: 11px;
}

.generate-field-hint i {
  margin-right: 3px;
  color: #6885c4;
}

.generate-network-section {
  padding-bottom: 3px;
}

.generate-network-options {
  display: flex;
  flex-wrap: wrap;
  gap: 9px;
}

.generate-network-options >>> .el-checkbox.is-bordered {
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

.generate-network-options >>> .el-checkbox.is-bordered:hover {
  border-color: #aab9ce;
}

.generate-network-options >>> .el-checkbox.is-bordered.is-checked {
  background: #eff6ff;
  border-color: #7ca1eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, .07);
}

.generate-network-options >>> .el-checkbox__label {
  color: #475569;
  font-size: 12px;
}

.generate-network-options >>> .el-checkbox.is-checked .el-checkbox__label,
.generate-network-options >>> .el-checkbox.is-checked i {
  color: #315fb7;
}

.generate-network-options >>> i {
  margin-right: 4px;
}

.generate-title-section {
  margin-bottom: 0;
}

.generate-dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  padding: 15px 26px;
  background: #fff;
}

.generate-dialog-footer >>> .el-button {
  min-width: 112px;
  height: 36px;
  border-radius: 8px;
  font-weight: 600;
}

.generate-submit-button {
  background: linear-gradient(135deg, #4f46e5, #2563eb);
  border-color: #3b5fda;
  box-shadow: 0 4px 10px rgba(59, 95, 218, .18);
}

.generate-submit-button:hover,
.generate-submit-button:focus {
  background: linear-gradient(135deg, #4338ca, #1d4ed8);
  border-color: #3153c8;
}

@media (max-width: 640px) {
  .generate-grid-dialog >>> .el-dialog__header {
    padding: 17px 18px;
  }

  .generate-grid-dialog >>> .el-dialog__headerbtn {
    top: 19px;
    right: 16px;
  }

  .generate-dialog-content {
    padding: 16px;
  }

  .generate-form-section {
    padding: 14px 13px 3px;
  }

  .generate-network-options {
    gap: 7px;
  }

  .generate-network-options >>> .el-checkbox.is-bordered {
    padding: 0 9px;
  }

  .generate-dialog-footer {
    padding: 12px 16px;
  }

  .generate-dialog-footer >>> .el-button {
    flex: 1;
    min-width: 0;
  }
}
</style>