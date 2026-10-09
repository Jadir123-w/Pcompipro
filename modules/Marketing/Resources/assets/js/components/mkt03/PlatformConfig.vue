<template>
  <div>
    <el-tabs v-model="innerTab" type="border-card">
      <!-- ── Tab: Config por red ── -->
      <el-tab-pane label="Redes sociales" name="platforms">
        <div v-loading="loadingConfigs">
          <el-table :data="configs" stripe border size="small">
            <el-table-column prop="nombre_publico" label="Red" width="110">
              <template slot-scope="scope">
                <el-tag size="small">{{ scope.row.nombre_publico }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="Funcion editorial" min-width="280">
              <template slot-scope="scope">
                <small>{{ scope.row.funcion_editorial_principal }}</small>
              </template>
            </el-table-column>
            <el-table-column label="Max copy" width="90" align="center">
              <template slot-scope="scope">{{ scope.row.max_copy_length }}</template>
            </el-table-column>
            <el-table-column label="Max hashtags" width="100" align="center">
              <template slot-scope="scope">{{ scope.row.max_hashtags }}</template>
            </el-table-column>
            <el-table-column label="Activa" width="80" align="center">
              <template slot-scope="scope">
                <el-tag v-if="scope.row.active" type="success" size="mini">SI</el-tag>
                <el-tag v-else type="info" size="mini">NO</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="Acciones" width="100" align="center">
              <template slot-scope="scope">
                <el-button size="mini" type="primary" icon="el-icon-edit" @click="editConfig(scope.row)">Editar</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>

        <!-- Dialog: editar config -->
        <el-dialog :title="'Editar: ' + (editingConfig ? editingConfig.nombre_publico : '')"
                   :visible.sync="showEditDialog" width="700px">
          <el-form v-if="editingConfig" :model="editingConfig" label-position="top" size="small">
            <el-form-item label="Funcion editorial principal">
              <el-input type="textarea" v-model="editingConfig.funcion_editorial_principal" :rows="2"></el-input>
            </el-form-item>
            <el-form-item label="Que priorizar">
              <el-input type="textarea" v-model="editingConfig.que_priorizar" :rows="3"></el-input>
            </el-form-item>
            <el-form-item label="Que evitar">
              <el-input type="textarea" v-model="editingConfig.que_evitar" :rows="3"></el-input>
            </el-form-item>
            <el-form-item label="Tono recomendado">
              <el-input v-model="editingConfig.tono_recomendado"></el-input>
            </el-form-item>
            <el-row :gutter="10">
              <el-col :span="12">
                <el-form-item label="Max copy length">
                  <el-input-number v-model="editingConfig.max_copy_length" :min="100" :max="10000"></el-input-number>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="Max hashtags">
                  <el-input-number v-model="editingConfig.max_hashtags" :min="0" :max="50"></el-input-number>
                </el-form-item>
              </el-col>
            </el-row>
          </el-form>
          <div slot="footer">
            <el-button @click="showEditDialog = false">Cancelar</el-button>
            <el-button type="primary" @click="saveConfig" :loading="savingConfig">Guardar</el-button>
          </div>
        </el-dialog>
      </el-tab-pane>

      <!-- ── Tab: Patrones aprendidos ── -->
      <el-tab-pane label="Patrones aprendidos" name="patterns">
        <div v-loading="loadingPatterns">
          <el-row :gutter="10" class="mb-2">
            <el-col :span="6">
              <el-select v-model="pFilters.tipo_patron" clearable placeholder="Tipo" @change="loadPatterns">
                <el-option v-for="t in tipos" :key="t" :label="t" :value="t"></el-option>
              </el-select>
            </el-col>
            <el-col :span="6">
              <el-select v-model="pFilters.red_social" clearable placeholder="Red" @change="loadPatterns">
                <el-option v-for="r in redes" :key="r" :label="r" :value="r"></el-option>
              </el-select>
            </el-col>
            <el-col :span="4">
              <el-button icon="el-icon-refresh" @click="loadPatterns">Recargar</el-button>
            </el-col>
          </el-row>

          <el-table :data="patterns" stripe border size="small">
            <el-table-column prop="tipo_patron" label="Tipo" width="130"></el-table-column>
            <el-table-column prop="red_social" label="Red" width="100">
              <template slot-scope="scope">
                <el-tag v-if="scope.row.red_social" size="mini">{{ scope.row.red_social }}</el-tag>
                <span v-else>Todas</span>
              </template>
            </el-table-column>
            <el-table-column prop="titulo" label="Titulo" min-width="250"></el-table-column>
            <el-table-column prop="ocurrencias" label="Ocurr." width="70" align="center"></el-table-column>
            <el-table-column prop="confianza" label="Confianza" width="90" align="center">
              <template slot-scope="scope">{{ scope.row.confianza }}%</template>
            </el-table-column>
            <el-table-column label="Activo" width="70" align="center">
              <template slot-scope="scope">
                <el-tag v-if="scope.row.active" type="success" size="mini">SI</el-tag>
                <el-tag v-else type="info" size="mini">NO</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="Aprobado" width="90" align="center">
              <template slot-scope="scope">
                <el-tag v-if="scope.row.aprobado_por_usuario" type="success" size="mini">SI</el-tag>
                <el-tag v-else type="info" size="mini">NO</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="Acciones" width="170" align="center">
              <template slot-scope="scope">
                <el-button v-if="!scope.row.aprobado_por_usuario" size="mini" type="success"
                           @click="approvePattern(scope.row, true, true)">Aprobar</el-button>
                <el-button v-if="scope.row.active" size="mini"
                           @click="approvePattern(scope.row, scope.row.aprobado_por_usuario, false)">Desactivar</el-button>
                <el-button v-else size="mini" type="primary"
                           @click="approvePattern(scope.row, scope.row.aprobado_por_usuario, true)">Activar</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-tab-pane>
    </el-tabs>
  </div>
</template>

<script>
export default {
  data() {
    return {
      innerTab: 'platforms',
      // Config
      configs: [],
      loadingConfigs: false,
      editingConfig: null,
      showEditDialog: false,
      savingConfig: false,
      // Patterns
      patterns: [],
      loadingPatterns: false,
      pFilters: { tipo_patron: '', red_social: '' },
      tipos: [
        'frecuencia', 'temporada', 'campania', 'distribucion_red', 'tono',
        'tipo_pieza', 'ritmo', 'festividad', 'comunicacion_recurrente',
      ],
      redes: ['facebook', 'instagram', 'linkedin', 'tiktok'],
    }
  },
  created() {
    this.loadConfigs()
    this.loadPatterns()
  },
  methods: {
    // ── Configs ──
    async loadConfigs() {
      this.loadingConfigs = true
      try {
        var r = await this.$http.get('/marketing/mkt03/platform-config')
        this.configs = r.data.configs || []
      } catch (e) {
        this.$message.error('Error cargando configuracion')
      }
      this.loadingConfigs = false
    },

    editConfig(cfg) {
      this.editingConfig = JSON.parse(JSON.stringify(cfg))
      this.showEditDialog = true
    },

    async saveConfig() {
      this.savingConfig = true
      try {
        await this.$http.put(
          '/marketing/mkt03/platform-config/' + this.editingConfig.red_social,
          this.editingConfig
        )
        this.$message.success('Configuracion guardada')
        this.showEditDialog = false
        this.loadConfigs()
      } catch (e) {
        this.$message.error('Error guardando')
      }
      this.savingConfig = false
    },

    // ── Patterns ──
    async loadPatterns() {
      this.loadingPatterns = true
      try {
        var params = { per_page: 50 }
        if (this.pFilters.tipo_patron) params.tipo_patron = this.pFilters.tipo_patron
        if (this.pFilters.red_social) params.red_social = this.pFilters.red_social
        var r = await this.$http.get('/marketing/mkt03/patterns', { params: params })
        this.patterns = (r.data.patterns && r.data.patterns.data) || r.data.patterns || []
      } catch (e) {
        this.$message.error('Error cargando patrones')
      }
      this.loadingPatterns = false
    },

    async approvePattern(pattern, aprobado, active) {
      try {
        await this.$http.put('/marketing/mkt03/patterns/' + pattern.id + '/approve', {
          aprobado: aprobado,
          active: active,
        })
        this.$message.success('Patron actualizado')
        this.loadPatterns()
      } catch (e) {
        this.$message.error('Error')
      }
    },
  }
}
</script>
