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
        </template>
      </el-table-column>
    </el-table>

    <!-- Dialog: Crear extraordinario -->
    <el-dialog title="Crear contenido extraordinario" :visible.sync="showCreateDialog" width="500px">
      <el-form :model="form" label-position="top" size="small">
        <el-row :gutter="10">
          <el-col :span="12">
            <el-form-item label="Fecha">
              <el-date-picker v-model="form.fecha" type="date" value-format="yyyy-MM-dd" style="width:100%"></el-date-picker>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Red">
              <el-select v-model="form.red_social">
                <el-option v-for="r in redes" :key="r" :label="r" :value="r"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="Motivo">
          <el-input v-model="form.motivo" placeholder="Ej: Anuncio urgente de lanzamiento"></el-input>
        </el-form-item>
        <el-form-item label="Clasificacion">
          <el-select v-model="form.clasificacion">
            <el-option label="Urgente" value="urgente"></el-option>
            <el-option label="Oportunidad" value="oportunidad"></el-option>
            <el-option label="Reactivo" value="reactivo"></el-option>
            <el-option label="Promocional" value="promocional"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="Descripcion">
          <el-input type="textarea" v-model="form.descripcion" :rows="3"></el-input>
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="showCreateDialog = false">Cancelar</el-button>
        <el-button type="primary" @click="create" :loading="saving">Crear</el-button>
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

    estadoType(estado) {
      var map = {
        'solicitado': 'info', 'en_desarrollo': 'warning', 'entregado': 'primary',
        'aprobado_para_futuras': 'success', 'no_aprobado': 'info',
      }
      return map[estado] || 'info'
    }
  }
}
</script>
