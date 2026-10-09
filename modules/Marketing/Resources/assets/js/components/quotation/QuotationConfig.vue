<template>
  <div>
    <el-row :gutter="20">
      <!-- Parametros generales -->
      <el-col :span="12">
        <el-card shadow="never">
          <div slot="header"><strong>Parametros generales</strong></div>
          <div v-loading="loadingConfig">
            <div v-for="cfg in configs" :key="cfg.id" class="mb-2">
              <el-row :gutter="10" type="flex" align="middle">
                <el-col :span="12">
                  <small style="color:#666">{{ cfg.description || cfg.config_key }}</small>
                </el-col>
                <el-col :span="8">
                  <el-input v-model="cfg.config_value" size="small"></el-input>
                </el-col>
                <el-col :span="4">
                  <el-button size="mini" type="primary" icon="el-icon-check" @click="saveConfig(cfg)"></el-button>
                </el-col>
              </el-row>
            </div>
          </div>
        </el-card>
      </el-col>

      <!-- Reglas de margen -->
      <el-col :span="12">
        <el-card shadow="never">
          <div slot="header">
            <strong>Reglas de margen</strong>
            <el-button size="mini" type="primary" style="float:right" @click="showMarginForm = true">
              + Nueva regla
            </el-button>
          </div>
          <div v-loading="loadingMargins">
            <el-table :data="margins" stripe border size="mini" empty-text="Sin reglas configuradas">
              <el-table-column prop="category" label="Categoria" width="100"></el-table-column>
              <el-table-column prop="product_name" label="Producto" min-width="120">
                <template slot-scope="scope">{{ scope.row.product_name || '(toda la categoria)' }}</template>
              </el-table-column>
              <el-table-column label="Tramo" width="100">
                <template slot-scope="scope">
                  {{ scope.row.quantity_from }} - {{ scope.row.quantity_to || '+' }}
                </template>
              </el-table-column>
              <el-table-column label="Margen %" width="80" align="right">
                <template slot-scope="scope">{{ scope.row.margin_percentage }}%</template>
              </el-table-column>
              <el-table-column label="" width="80" align="center">
                <template slot-scope="scope">
                  <el-button size="mini" type="danger" icon="el-icon-delete" circle
                             @click="deleteMargin(scope.row.id)"></el-button>
                </template>
              </el-table-column>
            </el-table>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- Dialog: Nueva regla de margen -->
    <el-dialog title="Nueva regla de margen" :visible.sync="showMarginForm" width="450px">
      <el-form :model="marginForm" label-position="top" size="small">
        <el-form-item label="Categoria">
          <el-select v-model="marginForm.category" placeholder="Seleccionar">
            <el-option label="General" value="General"></el-option>
            <el-option label="Textil" value="Textil"></el-option>
            <el-option label="Impresos" value="Impresos"></el-option>
            <el-option label="Ecologica" value="Ecologica"></el-option>
            <el-option label="Packs" value="Packs"></el-option>
            <el-option label="Accesorios" value="Accesorios"></el-option>
            <el-option label="Otros" value="Otros"></el-option>
          </el-select>
        </el-form-item>
        <el-form-item label="Producto especifico (opcional)">
          <el-input v-model="marginForm.product_name" placeholder="Dejar vacio para toda la categoria"></el-input>
        </el-form-item>
        <el-row :gutter="10">
          <el-col :span="8">
            <el-form-item label="Desde (cant.)">
              <el-input-number v-model="marginForm.quantity_from" :min="1" size="small"></el-input-number>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="Hasta (cant.)">
              <el-input-number v-model="marginForm.quantity_to" :min="0" size="small" placeholder="Sin limite"></el-input-number>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="Margen %">
              <el-input-number v-model="marginForm.margin_percentage" :min="0" :max="999" :precision="1" size="small"></el-input-number>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <div slot="footer">
        <el-button @click="showMarginForm = false">Cancelar</el-button>
        <el-button type="primary" @click="createMargin" :loading="savingMargin">Guardar</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  data() {
    return {
      configs: [],
      loadingConfig: false,
      margins: [],
      loadingMargins: false,
      showMarginForm: false,
      savingMargin: false,
      marginForm: {
        category: 'General',
        product_name: '',
        quantity_from: 1,
        quantity_to: null,
        margin_percentage: 30,
      }
    }
  },
  created() {
    this.loadConfig()
    this.loadMargins()
  },
  methods: {
    async loadConfig() {
      this.loadingConfig = true
      try {
        var response = await this.$http.get('/marketing/quotation/config')
        this.configs = response.data.configs
      } catch (e) {
        this.$message.error('Error cargando configuracion')
      }
      this.loadingConfig = false
    },

    async saveConfig(cfg) {
      try {
        await this.$http.put('/marketing/quotation/config/' + cfg.config_key, {
          config_value: cfg.config_value
        })
        this.$message.success('Configuracion actualizada: ' + cfg.config_key)
      } catch (e) {
        this.$message.error('Error guardando')
      }
    },

    async loadMargins() {
      this.loadingMargins = true
      try {
        var response = await this.$http.get('/marketing/quotation/margins')
        this.margins = response.data.margins
      } catch (e) {
        this.$message.error('Error cargando margenes')
      }
      this.loadingMargins = false
    },

    async createMargin() {
      this.savingMargin = true
      try {
        await this.$http.post('/marketing/quotation/margins', this.marginForm)
        this.$message.success('Regla de margen creada')
        this.showMarginForm = false
        this.marginForm = { category: 'General', product_name: '', quantity_from: 1, quantity_to: null, margin_percentage: 30 }
        this.loadMargins()
      } catch (e) {
        this.$message.error('Error creando regla')
      }
      this.savingMargin = false
    },

    async deleteMargin(id) {
      try {
        await this.$confirm('Eliminar esta regla de margen?', 'Confirmar')
        await this.$http.delete('/marketing/quotation/margins/' + id)
        this.$message.success('Regla eliminada')
        this.loadMargins()
      } catch (e) {
        // Cancelled or error
      }
    }
  }
}
</script>
