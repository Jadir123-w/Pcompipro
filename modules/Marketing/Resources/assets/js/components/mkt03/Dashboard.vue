<template>
  <div v-loading="loading">
    <el-row :gutter="15" class="mb-3">
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-label">Grillas activas</div>
          <div class="stat-value">{{ stats.grids_activas }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-label">Items planificados</div>
          <div class="stat-value">{{ stats.items_total }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-label">Piezas listas</div>
          <div class="stat-value">{{ stats.piezas_listas }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never">
          <div class="stat-label">Ferias activas</div>
          <div class="stat-value">{{ stats.ferias_activas }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never">
      <div slot="header"><strong>Grillas recientes</strong></div>
      <el-table :data="grids" stripe border size="small" empty-text="Sin grillas creadas">
        <el-table-column prop="id" label="ID" width="60"></el-table-column>
        <el-table-column prop="titulo" label="Titulo" min-width="200"></el-table-column>
        <el-table-column prop="anio" label="Ano" width="80"></el-table-column>
        <el-table-column prop="red_social" label="Red" width="110">
          <template slot-scope="scope">
            <el-tag size="mini">{{ scope.row.red_social }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="tipo_periodo" label="Tipo" width="100"></el-table-column>
        <el-table-column label="Estado" width="130">
          <template slot-scope="scope">
            <el-tag :type="estadoType(scope.row.estado)" size="small">{{ scope.row.estado }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="items_count" label="Items" width="80" align="center"></el-table-column>
        <el-table-column prop="created_at" label="Creada" width="110">
          <template slot-scope="scope">
            {{ scope.row.created_at ? scope.row.created_at.substring(0, 10) : '' }}
          </template>
        </el-table-column>
      </el-table>
    </el-card>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading: false,
      stats: {
        grids_activas: 0,
        items_total: 0,
        piezas_listas: 0,
        ferias_activas: 0,
      },
      grids: [],
    }
  },
  created() {
    this.loadDashboard()
  },
  methods: {
    async loadDashboard() {
      this.loading = true
      try {
        var gr = await this.$http.get('/marketing/mkt03/grids', { params: { per_page: 10 } })
        var list = (gr.data.grids && gr.data.grids.data) || gr.data.grids || []
        this.grids = list
        this.stats.grids_activas = list.filter(function (g) { return g.estado === 'activa' }).length
        this.stats.items_total   = list.reduce(function (acc, g) { return acc + (parseInt(g.items_count) || 0) }, 0)

        var pi = await this.$http.get('/marketing/mkt03/pieces', { params: { estado_pieza: 'aprobado', per_page: 1 } })
        this.stats.piezas_listas = (pi.data.pieces && pi.data.pieces.total) || 0

        var fe = await this.$http.get('/marketing/mkt03/ferias', { params: { estado: 'activa', per_page: 1 } })
        this.stats.ferias_activas = (fe.data.ferias && fe.data.ferias.total) || 0
      } catch (e) {
        this.$message.error('Error cargando dashboard')
      }
      this.loading = false
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
.stat-label { color: #666; font-size: 13px; }
.stat-value { font-size: 28px; font-weight: 700; color: #111; margin-top: 5px; }
</style>
