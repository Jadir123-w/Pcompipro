<template>
  <div>
    <el-row :gutter="15" class="mb-3">
      <el-col :span="6">
        <el-select v-model="filters.red_social" clearable placeholder="Red social" @change="loadGrids">
          <el-option v-for="r in redes" :key="r.value" :label="r.label" :value="r.value"></el-option>
        </el-select>
      </el-col>
      <el-col :span="4">
        <el-input-number v-model="filters.anio" :min="2020" :max="2100" placeholder="Ano"
                         @change="loadGrids"></el-input-number>
      </el-col>
      <el-col :span="4">
        <el-button icon="el-icon-refresh" @click="loadGrids">Recargar</el-button>
      </el-col>
      <el-col :span="10" style="text-align:right">
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
      <el-table-column label="Acciones" width="220" align="center">
        <template slot-scope="scope">
          <el-button size="mini" type="primary" icon="el-icon-view" @click="openGrid(scope.row.id)">Ver</el-button>
          <el-button size="mini" icon="el-icon-plus" @click="openExtend(scope.row)">Ampliar</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- Dialog: Generar grilla -->
    <el-dialog title="Generar Grilla con IA" :visible.sync="showGenerateDialog" width="600px">
      <el-form :model="genForm" label-position="top" size="small">
        <el-row :gutter="10">
          <el-col :span="12">
            <el-form-item label="Ano">
              <el-input-number v-model="genForm.anio" :min="2020" :max="2100"></el-input-number>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Tipo de periodo">
              <el-select v-model="genForm.tipo_periodo">
                <el-option label="Anual" value="anual"></el-option>
                <el-option label="Trimestral" value="trimestral"></el-option>
                <el-option label="Semestral" value="semestral"></el-option>
                <el-option label="Mensual" value="mensual"></el-option>
                <el-option label="Campania" value="campania"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="Identificador de periodo (ej: 2026, 2026-Q1, 2026-05)">
          <el-input v-model="genForm.periodo"></el-input>
        </el-form-item>
        <el-row :gutter="10">
          <el-col :span="12">
            <el-form-item label="Fecha desde">
              <el-date-picker v-model="genForm.fecha_desde" type="date" value-format="yyyy-MM-dd" style="width:100%"></el-date-picker>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Fecha hasta">
              <el-date-picker v-model="genForm.fecha_hasta" type="date" value-format="yyyy-MM-dd" style="width:100%"></el-date-picker>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="Redes sociales">
          <el-checkbox-group v-model="genForm.redes">
            <el-checkbox label="facebook">Facebook</el-checkbox>
            <el-checkbox label="instagram">Instagram</el-checkbox>
            <el-checkbox label="linkedin">LinkedIn</el-checkbox>
            <el-checkbox label="tiktok">TikTok</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
        <el-form-item label="Titulo (opcional)">
          <el-input v-model="genForm.titulo"></el-input>
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="showGenerateDialog = false">Cancelar</el-button>
        <el-button type="primary" @click="generate" :loading="generating" icon="el-icon-magic-stick">
          Generar con IA
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
              <el-button size="mini" type="success" icon="el-icon-magic-stick"
                         @click="producePiece(scope.row)" :loading="producingId === scope.row.id">
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
    async loadGrids() {
      this.loading = true
      try {
        var params = {}
        if (this.filters.red_social) params.red_social = this.filters.red_social
        if (this.filters.anio) params.anio = this.filters.anio
        var r = await this.$http.get('/marketing/mkt03/grids', { params: params })
        this.grids = (r.data.grids && r.data.grids.data) || r.data.grids || []
      } catch (e) {
        this.$message.error('Error cargando grillas')
      }
      this.loading = false
    },

    async generate() {
      if (!this.genForm.fecha_desde || !this.genForm.fecha_hasta || !this.genForm.redes.length) {
        this.$message.warning('Completa fechas y al menos una red')
        return
      }
      this.generating = true
      try {
        var payload = Object.assign({}, this.genForm)
        if (!payload.periodo) payload.periodo = String(payload.anio)
        var r = await this.$http.post('/marketing/mkt03/grids/generate', payload)
        this.$message.success('Grilla(s) generada(s): ' + (r.data.items_count || 0) + ' items totales')
        this.showGenerateDialog = false
        this.loadGrids()
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error generando'
        this.$message.error(msg)
      }
      this.generating = false
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
        this.loadGrids()
      } catch (e) {
        this.$message.error('Error ampliando')
      }
      this.extending = false
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
        this.$message.success('Pieza producida (ID: ' + r.data.piece.id + ')')
      } catch (e) {
        this.$message.error('Error produciendo pieza')
      }
      this.producingId = null
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
