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
    <el-dialog title="Registrar feria" :visible.sync="showCreateDialog" width="600px">
      <el-form :model="form" label-position="top" size="small">
        <el-row :gutter="10">
          <el-col :span="16">
            <el-form-item label="Nombre de la feria">
              <el-input v-model="form.nombre"></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="Fecha">
              <el-date-picker v-model="form.fecha" type="date" value-format="yyyy-MM-dd" style="width:100%"></el-date-picker>
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="10">
          <el-col :span="12">
            <el-form-item label="Sector">
              <el-input v-model="form.sector"></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="Ciudad">
              <el-input v-model="form.ciudad"></el-input>
            </el-form-item>
          </el-col>
          <el-col :span="6">
            <el-form-item label="Participacion">
              <el-select v-model="form.tipo_participacion">
                <el-option label="Stand" value="stand"></el-option>
                <el-option label="Invitado" value="invitado"></el-option>
                <el-option label="Visitante" value="visitante"></el-option>
                <el-option label="Ponente" value="ponente"></el-option>
                <el-option label="Sponsor" value="sponsor"></el-option>
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="Objetivo comunicacional">
          <el-input type="textarea" v-model="form.objetivo_comunicacional" :rows="2"></el-input>
        </el-form-item>
        <el-form-item label="Contexto interno (no se publica)">
          <el-input type="textarea" v-model="form.contexto_interno" :rows="2"></el-input>
        </el-form-item>
        <el-form-item label="Redes target">
          <el-checkbox-group v-model="form.redes_sociales_target">
            <el-checkbox label="facebook">Facebook</el-checkbox>
            <el-checkbox label="instagram">Instagram</el-checkbox>
            <el-checkbox label="linkedin">LinkedIn</el-checkbox>
            <el-checkbox label="tiktok">TikTok</el-checkbox>
          </el-checkbox-group>
        </el-form-item>
      </el-form>
      <div slot="footer">
        <el-button @click="showCreateDialog = false">Cancelar</el-button>
        <el-button type="primary" @click="create" :loading="saving">Registrar</el-button>
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
