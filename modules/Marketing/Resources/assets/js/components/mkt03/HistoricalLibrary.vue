<template>
  <div>
    <el-row :gutter="15" class="mb-3">
      <el-col :span="8">
        <el-button type="primary" icon="el-icon-upload" @click="showUploadDialog = true">
          Importar Excel historico
        </el-button>
      </el-col>
      <el-col :span="4">
        <el-button icon="el-icon-refresh" @click="loadHistoricals">Recargar</el-button>
      </el-col>
    </el-row>

    <el-table :data="historicals" stripe border v-loading="loading" empty-text="Sin historicos importados">
      <el-table-column prop="id" label="ID" width="60"></el-table-column>
      <el-table-column prop="anio" label="Ano" width="80"></el-table-column>
      <el-table-column prop="nombre" label="Nombre" min-width="200"></el-table-column>
      <el-table-column prop="archivo_original" label="Archivo" min-width="200"></el-table-column>
      <el-table-column prop="total_items" label="Items" width="80" align="center"></el-table-column>
      <el-table-column prop="learning_patterns_count" label="Patrones" width="90" align="center"></el-table-column>
      <el-table-column label="Estado" width="120">
        <template slot-scope="scope">
          <el-tag :type="estadoType(scope.row.estado)" size="small">{{ scope.row.estado }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="220" align="center">
        <template slot-scope="scope">
          <el-button size="mini" type="primary" icon="el-icon-data-analysis"
                     :disabled="!scope.row.contenido_normalizado || scope.row.estado === 'analizado'"
                     :loading="analyzingId === scope.row.id"
                     @click="analyze(scope.row.id)">
            Analizar
          </el-button>
          <el-button size="mini" type="danger" icon="el-icon-delete" @click="deleteHistorical(scope.row.id)"></el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- Dialog: Upload Excel -->
    <el-dialog title="Importar Planner Historico" :visible.sync="showUploadDialog" width="500px">
      <el-form :model="uploadForm" label-position="top" size="small">
        <el-form-item label="Ano">
          <el-input-number v-model="uploadForm.anio" :min="2020" :max="2100"></el-input-number>
        </el-form-item>
        <el-form-item label="Nombre descriptivo">
          <el-input v-model="uploadForm.nombre" placeholder="Ej: Planner 2023 FB-IG-LinkedIn"></el-input>
        </el-form-item>
        <el-form-item label="Archivo Excel (.xlsx)">
          <el-upload action="" :auto-upload="false" :limit="1" :on-change="handleFileChange"
                     accept=".xlsx,.xls">
            <el-button size="small" type="primary">Seleccionar archivo</el-button>
          </el-upload>
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="showUploadDialog = false">Cancelar</el-button>
        <el-button type="primary" @click="upload" :loading="uploading">Importar</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  data() {
    return {
      historicals: [],
      loading: false,
      analyzingId: null,
      // Upload
      showUploadDialog: false,
      uploading: false,
      uploadForm: {
        anio: new Date().getFullYear() - 1,
        nombre: '',
      },
      selectedFile: null,
    }
  },
  created() {
    this.loadHistoricals()
  },
  methods: {
    async loadHistoricals() {
      this.loading = true
      try {
        var r = await this.$http.get('/marketing/mkt03/historical')
        this.historicals = (r.data.historicals && r.data.historicals.data) || r.data.historicals || []
      } catch (e) {
        this.$message.error('Error cargando historicos')
      }
      this.loading = false
    },

    handleFileChange(file) {
      this.selectedFile = file.raw
    },

    async upload() {
      if (!this.selectedFile) {
        this.$message.warning('Selecciona un archivo')
        return
      }
      if (!this.uploadForm.nombre) {
        this.$message.warning('Ingresa un nombre')
        return
      }
      this.uploading = true
      try {
        var fd = new FormData()
        fd.append('anio', this.uploadForm.anio)
        fd.append('nombre', this.uploadForm.nombre)
        fd.append('archivo', this.selectedFile)
        var r = await this.$http.post('/marketing/mkt03/historical/import', fd)
        this.$message.success('Importado (ID: ' + r.data.historical.id + '). ' + (r.data.note || ''))
        this.showUploadDialog = false
        this.loadHistoricals()
      } catch (e) {
        var responseData = e.response && e.response.data
        var errors = responseData && responseData.errors
        var firstError = errors && errors[Object.keys(errors)[0]]
        var msg = (responseData && responseData.message) || (firstError && firstError[0]) || 'Error importando'
        this.$message.error(msg)
      }
      this.uploading = false
    },

    async analyze(id) {
      this.analyzingId = id
      try {
        var r = await this.$http.post('/marketing/mkt03/historical/' + id + '/analyze')
        this.$message.success('Analisis completado: ' + r.data.patterns_saved + ' patrones guardados')
        this.loadHistoricals()
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error analizando'
        this.$message.error(msg)
      }
      this.analyzingId = null
    },

    async deleteHistorical(id) {
      try {
        await this.$confirm('Eliminar este historico?', 'Confirmar')
        await this.$http.delete('/marketing/mkt03/historical/' + id)
        this.$message.success('Eliminado')
        this.loadHistoricals()
      } catch (e) {
        // cancelado
      }
    },

    estadoType(estado) {
      var map = { 'importado': 'info', 'analizado': 'success', 'error': 'danger' }
      return map[estado] || 'info'
    }
  }
}
</script>
