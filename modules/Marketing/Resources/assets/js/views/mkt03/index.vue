<template>
  <div>
    <div class="page-header pr-0">
      <h2><i class="fas fa-calendar-alt"></i> Social Media Content (MKT-03)</h2>
    </div>

    <el-tabs v-model="activeTab" type="card">
      <el-tab-pane label="Dashboard" name="dashboard">
        <mkt03-dashboard v-if="activeTab === 'dashboard'"></mkt03-dashboard>
      </el-tab-pane>

      <el-tab-pane label="Grillas" name="grids">
        <mkt03-grid-editor v-if="activeTab === 'grids'"></mkt03-grid-editor>
      </el-tab-pane>

      <el-tab-pane label="Piezas" name="pieces">
        <mkt03-piece-producer v-if="activeTab === 'pieces'"></mkt03-piece-producer>
      </el-tab-pane>

      <el-tab-pane label="Historico" name="historical">
        <mkt03-historical-library v-if="activeTab === 'historical'"></mkt03-historical-library>
      </el-tab-pane>

      <el-tab-pane label="Extraordinario" name="extraordinary">
        <mkt03-extraordinary-manager v-if="activeTab === 'extraordinary'"></mkt03-extraordinary-manager>
      </el-tab-pane>

      <el-tab-pane label="Ferias" name="ferias">
        <mkt03-ferias-view v-if="activeTab === 'ferias'"></mkt03-ferias-view>
      </el-tab-pane>

      <el-tab-pane label="Configuracion" name="config">
        <mkt03-platform-config v-if="activeTab === 'config'"></mkt03-platform-config>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script>
import Mkt03Dashboard from '../../components/mkt03/Dashboard.vue'
import Mkt03GridEditor from '../../components/mkt03/GridEditor.vue'
import Mkt03PieceProducer from '../../components/mkt03/PieceProducer.vue'
import Mkt03HistoricalLibrary from '../../components/mkt03/HistoricalLibrary.vue'
import Mkt03ExtraordinaryManager from '../../components/mkt03/ExtraordinaryManager.vue'
import Mkt03FeriasView from '../../components/mkt03/FeriasView.vue'
import Mkt03PlatformConfig from '../../components/mkt03/PlatformConfig.vue'

export default {
  components: {
    Mkt03Dashboard,
    Mkt03GridEditor,
    Mkt03PieceProducer,
    Mkt03HistoricalLibrary,
    Mkt03ExtraordinaryManager,
    Mkt03FeriasView,
    Mkt03PlatformConfig,
  },
  data() {
    return {
      activeTab: 'dashboard',
    }
  },
}
</script>
