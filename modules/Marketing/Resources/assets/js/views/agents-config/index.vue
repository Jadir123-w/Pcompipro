<template>
  <div class="agents-config">
    <!-- ═════════════════════════════════════════ -->
    <!-- Header                                       -->
    <!-- ═════════════════════════════════════════ -->
    <div class="ac-header">
      <div>
        <h2 class="ac-title">⚙️ Configuración de Agentes IA</h2>
        <p class="ac-subtitle">Provider, modelo y parámetros que usa cada agente. Los cambios aplican inmediatamente sin reiniciar el sistema.</p>
      </div>
    </div>

    <el-tabs v-model="activeTab" type="border-card" v-loading="loading">

      <!-- ═════════════════════════════════════════ -->
      <!-- Tab por agente                              -->
      <!-- ═════════════════════════════════════════ -->
      <el-tab-pane v-for="agent in agents" :key="agent.agent_key" :name="agent.agent_key">
        <span slot="label">
          <i :class="agentIcon(agent.agent_key)"></i>
          {{ agentLabel(agent.agent_key) }}
        </span>

        <div v-if="forms[agent.agent_key]" class="agent-form">
          <!-- Info actual resuelta (solo si ya se cargó del backend) -->
          <el-alert v-if="resolved[agent.agent_key] && resolved[agent.agent_key].text"
                    type="info" show-icon :closable="false" class="resolved-banner">
            <template slot="title">
              <strong>Resolución actual en runtime</strong> · source: <code>{{ resolved[agent.agent_key].text.source }}</code>
            </template>
            <div>
              <strong>Texto:</strong>
              {{ resolved[agent.agent_key].text.provider }} ·
              modelo: <code>{{ resolved[agent.agent_key].text.model }}</code> ·
              temp: {{ resolved[agent.agent_key].text.temperature }} ·
              tokens: {{ resolved[agent.agent_key].text.max_tokens }} ·
              api_key:
              <el-tag size="mini" :type="resolved[agent.agent_key].text.api_key_set ? 'success' : 'danger'">
                {{ resolved[agent.agent_key].text.api_key_set ? 'OK' : 'MISSING' }}
              </el-tag>
            </div>
            <div v-if="agent.agent_key === 'mkt03' && resolved[agent.agent_key].image" style="margin-top: 4px;">
              <strong>Imagen:</strong>
              {{ resolved[agent.agent_key].image.provider }} ·
              modelo: <code>{{ resolved[agent.agent_key].image.model }}</code> ·
              quality: {{ resolved[agent.agent_key].image.quality }} ·
              format: {{ resolved[agent.agent_key].image.format }} ·
              ratio: {{ resolved[agent.agent_key].image.aspect_ratio }} ·
              edit:
              <el-tag size="mini" :type="resolved[agent.agent_key].image.supports_image_edit ? 'success' : 'info'">
                {{ resolved[agent.agent_key].image.supports_image_edit ? 'YES' : 'NO' }}
              </el-tag>
            </div>
          </el-alert>

          <!-- ═════════════════════════════════════════ -->
          <!-- Sección: Texto                              -->
          <!-- ═════════════════════════════════════════ -->
          <div class="ac-section">
            <h3 class="ac-section-title">📝 Configuración de TEXTO (Claude / OpenAI)</h3>

            <el-form label-position="top" size="small">
              <el-row :gutter="16">
                <el-col :span="12">
                  <el-form-item label="Provider de texto">
                    <el-select v-model="forms[agent.agent_key].text_provider_id" placeholder="Elegir provider" style="width: 100%;">
                      <el-option v-for="p in textProviders" :key="p.id" :value="p.id"
                                 :label="p.display_name + (p.is_configured ? '' : ' (sin API key)')"
                                 :disabled="!p.is_configured">
                        <span>{{ p.display_name }}</span>
                        <span style="float: right; color: #909399; font-size: 12px;">
                          {{ p.provider_key }}
                        </span>
                      </el-option>
                    </el-select>
                    <small class="ac-hint" v-if="selectedTextProvider(agent.agent_key)">
                      {{ selectedTextProvider(agent.agent_key).pricing_notes }}
                    </small>
                  </el-form-item>
                </el-col>

                <el-col :span="12">
                  <el-form-item label="Modelo (override)">
                    <el-select v-model="forms[agent.agent_key].text_model_override"
                               placeholder="(usar default del provider)"
                               clearable style="width: 100%;">
                      <el-option v-for="m in textModelOptions(agent.agent_key)" :key="m" :value="m" :label="m"></el-option>
                    </el-select>
                    <small class="ac-hint">Vacío = usa el default del provider</small>
                  </el-form-item>
                </el-col>
              </el-row>

              <el-row :gutter="16">
                <el-col :span="12">
                  <el-form-item label="Temperature">
                    <el-slider v-model="forms[agent.agent_key].text_temperature"
                               :min="0" :max="2" :step="0.05"
                               show-stops :marks="{ 0: '0', 1: '1', 2: '2' }"
                               style="margin-top: 8px;"></el-slider>
                    <small class="ac-hint">
                      0 = determinista (cotizar) · 0.7 = balanceado · 1.5+ = creativo
                    </small>
                  </el-form-item>
                </el-col>

                <el-col :span="12">
                  <el-form-item label="Max tokens">
                    <el-input-number v-model="forms[agent.agent_key].text_max_tokens"
                                     :min="100" :max="32000" :step="256"
                                     style="width: 100%;"></el-input-number>
                    <small class="ac-hint">Máximo de tokens en la respuesta</small>
                  </el-form-item>
                </el-col>
              </el-row>
            </el-form>
          </div>

          <!-- ═════════════════════════════════════════ -->
          <!-- Sección: Imagen (solo MKT-03)              -->
          <!-- ═════════════════════════════════════════ -->
          <div v-if="agent.agent_key === 'mkt03'" class="ac-section">
            <h3 class="ac-section-title">🎨 Configuración de IMAGEN (DALL-E 3 / gpt-image-2)</h3>

            <el-form label-position="top" size="small">
              <el-row :gutter="16">
                <el-col :span="12">
                  <el-form-item label="Provider de imagen">
                    <el-select v-model="forms[agent.agent_key].image_provider_id"
                               placeholder="(no usar imagen)"
                               clearable style="width: 100%;"
                               @change="onImageProviderChange(agent.agent_key)">
                      <el-option v-for="p in imageProviders" :key="p.id" :value="p.id"
                                 :label="p.display_name + (p.is_configured ? '' : ' (sin API key)')"
                                 :disabled="!p.is_configured">
                        <span>{{ p.display_name }}</span>
                        <span style="float: right; color: #909399; font-size: 12px;">
                          {{ p.provider_key }}
                          <el-tag v-if="p.supports_image_edit" size="mini" type="success" style="margin-left: 4px;">edit</el-tag>
                        </span>
                      </el-option>
                    </el-select>
                    <small class="ac-hint" v-if="selectedImageProvider(agent.agent_key)">
                      {{ selectedImageProvider(agent.agent_key).pricing_notes }}
                    </small>
                  </el-form-item>
                </el-col>

                <el-col :span="12">
                  <el-form-item label="Modelo (override)">
                    <el-select v-model="forms[agent.agent_key].image_model_override"
                               placeholder="(usar default)" clearable style="width: 100%;">
                      <el-option v-for="m in imageModelOptions(agent.agent_key)" :key="m" :value="m" :label="m"></el-option>
                    </el-select>
                  </el-form-item>
                </el-col>
              </el-row>

              <el-row :gutter="16">
                <el-col :span="8">
                  <el-form-item label="Calidad">
                    <el-select v-model="forms[agent.agent_key].image_quality"
                               placeholder="(default)" clearable style="width: 100%;">
                      <el-option v-for="q in imageQualityOptions(agent.agent_key)" :key="q" :value="q" :label="q"></el-option>
                    </el-select>
                  </el-form-item>
                </el-col>

                <el-col :span="8">
                  <el-form-item label="Format">
                    <el-select v-model="forms[agent.agent_key].image_format" clearable style="width: 100%;">
                      <el-option label="png" value="png"></el-option>
                      <el-option label="jpeg" value="jpeg"></el-option>
                      <el-option label="webp" value="webp"></el-option>
                    </el-select>
                  </el-form-item>
                </el-col>

                <el-col :span="8">
                  <el-form-item label="Aspect ratio default">
                    <el-select v-model="forms[agent.agent_key].image_aspect_ratio_default"
                               placeholder="(default)" clearable style="width: 100%;">
                      <el-option v-for="r in imageRatioOptions(agent.agent_key)" :key="r" :value="r" :label="r"></el-option>
                    </el-select>
                  </el-form-item>
                </el-col>
              </el-row>

              <el-form-item label="Generación automática">
                <el-switch v-model="forms[agent.agent_key].image_auto_generate"
                           active-text="Sí: generar imagen al producir pieza"
                           inactive-text="No: generar a demanda desde la UI"></el-switch>
              </el-form-item>
            </el-form>
          </div>

          <!-- ═════════════════════════════════════════ -->
          <!-- Acciones                                    -->
          <!-- ═════════════════════════════════════════ -->
          <div class="ac-actions">
            <el-button @click="resetAgent(agent.agent_key)" :loading="saving[agent.agent_key]" icon="el-icon-refresh-left">
              Restaurar valores de fábrica
            </el-button>
            <el-button type="primary" @click="saveAgent(agent.agent_key)" :loading="saving[agent.agent_key]" icon="el-icon-check">
              Guardar cambios
            </el-button>
          </div>
        </div>
      </el-tab-pane>

      <!-- ═════════════════════════════════════════ -->
      <!-- Tab Providers (catálogo)                     -->
      <!-- ═════════════════════════════════════════ -->
      <el-tab-pane name="providers">
        <span slot="label"><i class="el-icon-set-up"></i> Providers (catálogo)</span>

        <div class="providers-list">
          <p class="text-muted" style="margin-bottom: 12px;">
            Proveedores de IA disponibles. Activa o desactiva cuáles puede elegir el usuario en cada agente.
          </p>

          <el-table :data="providers" border>
            <el-table-column label="Provider" min-width="220">
              <template slot-scope="scope">
                <div>
                  <strong>{{ scope.row.display_name }}</strong>
                  <el-tag size="mini" :type="scope.row.capability === 'text' ? 'info' : 'warning'" style="margin-left: 8px;">
                    {{ scope.row.capability }}
                  </el-tag>
                  <el-tag v-if="scope.row.supports_image_edit" size="mini" type="success" style="margin-left: 4px;">
                    edit
                  </el-tag>
                </div>
                <div class="text-muted" style="font-size: 12px;">
                  <code>{{ scope.row.provider_key }}</code> · default: <code>{{ scope.row.default_model }}</code>
                </div>
              </template>
            </el-table-column>

            <el-table-column label="API Key" width="120" align="center">
              <template slot-scope="scope">
                <el-tag :type="scope.row.is_configured ? 'success' : 'danger'" size="mini">
                  {{ scope.row.is_configured ? 'OK' : 'MISSING' }}
                </el-tag>
                <div class="text-muted" style="font-size: 11px; margin-top: 2px;">
                  {{ scope.row.api_key_env_var }}
                </div>
              </template>
            </el-table-column>

            <el-table-column label="Pricing" min-width="280">
              <template slot-scope="scope">
                <span class="text-muted" style="font-size: 12px;">{{ scope.row.pricing_notes || '—' }}</span>
              </template>
            </el-table-column>

            <el-table-column label="Habilitado" width="120" align="center">
              <template slot-scope="scope">
                <el-switch v-model="scope.row.enabled" @change="toggleProviderEnabled(scope.row)"></el-switch>
              </template>
            </el-table-column>
          </el-table>

          <p class="text-muted" style="margin-top: 12px; font-size: 12px;">
            <strong>Nota:</strong> las API keys se mantienen en el archivo <code>.env</code> del servidor por seguridad.
            Aquí solo gestionas qué providers están disponibles para usar.
          </p>
        </div>
      </el-tab-pane>

    </el-tabs>
  </div>
</template>

<script>
export default {
  data() {
    return {
      activeTab: 'mkt01',
      loading: false,
      agents: [],
      providers: [],
      forms: {},        // forms[agent_key] = { ...campos editables }
      resolved: {},     // resolved[agent_key] = { text: {...}, image: {...} }
      saving: {},
    }
  },
  computed: {
    textProviders() {
      return this.providers.filter(p => p.capability === 'text')
    },
    imageProviders() {
      return this.providers.filter(p => p.capability === 'image')
    },
  },
  created() {
    this.loadAll()
  },
  methods: {
    async loadAll() {
      this.loading = true
      try {
        await Promise.all([
          this.loadProviders(),
          this.loadAgents(),
        ])
      } finally {
        this.loading = false
      }
    },

    async loadProviders() {
      const r = await this.$http.get('/marketing/agents-config/providers')
      this.providers = r.data.providers || []
    },

    async loadAgents() {
      const r = await this.$http.get('/marketing/agents-config/agents')
      this.agents = r.data.agents || []
      // Inicializar forms y cargar resolución para cada uno
      for (const a of this.agents) {
        this.$set(this.forms, a.agent_key, this.formFromAgent(a))
        this.$set(this.saving, a.agent_key, false)
        await this.loadResolved(a.agent_key)
      }
    },

    async loadResolved(key) {
      try {
        const r = await this.$http.get('/marketing/agents-config/agents/' + key)
        this.$set(this.resolved, key, {
          text: r.data.resolved_text,
          image: r.data.resolved_image,
        })
      } catch (e) {
        // ignorar
      }
    },

    formFromAgent(a) {
      return {
        text_provider_id:           a.text_provider_id,
        text_model_override:        a.text_model_override,
        text_temperature:           a.text_temperature !== null ? parseFloat(a.text_temperature) : 0.7,
        text_max_tokens:            a.text_max_tokens || 1024,
        image_provider_id:          a.image_provider_id,
        image_model_override:       a.image_model_override,
        image_quality:              a.image_quality,
        image_format:               a.image_format,
        image_aspect_ratio_default: a.image_aspect_ratio_default,
        image_auto_generate:        !!a.image_auto_generate,
      }
    },

    async saveAgent(key) {
      this.$set(this.saving, key, true)
      try {
        const payload = JSON.parse(JSON.stringify(this.forms[key]))
        const r = await this.$http.put('/marketing/agents-config/agents/' + key, payload)
        // Actualizar agent en la lista
        const idx = this.agents.findIndex(a => a.agent_key === key)
        if (idx >= 0) this.$set(this.agents, idx, r.data.agent)
        this.$set(this.forms, key, this.formFromAgent(r.data.agent))
        await this.loadResolved(key)
        this.$message.success('Configuración guardada y aplicada')
      } catch (e) {
        const msg = (e.response && e.response.data && e.response.data.message) || 'Error guardando'
        this.$message.error(msg)
      } finally {
        this.$set(this.saving, key, false)
      }
    },

    async resetAgent(key) {
      const confirmed = await this.$confirm(
        '¿Restaurar la configuración de fábrica para este agente? Se perderán los cambios actuales.',
        'Confirmar restauración',
        { confirmButtonText: 'Sí, restaurar', cancelButtonText: 'Cancelar', type: 'warning' }
      ).catch(() => false)
      if (!confirmed) return

      this.$set(this.saving, key, true)
      try {
        const r = await this.$http.post('/marketing/agents-config/agents/' + key + '/reset')
        const idx = this.agents.findIndex(a => a.agent_key === key)
        if (idx >= 0) this.$set(this.agents, idx, r.data.agent)
        this.$set(this.forms, key, this.formFromAgent(r.data.agent))
        await this.loadResolved(key)
        this.$message.success('Configuración restaurada')
      } catch (e) {
        this.$message.error('Error restaurando')
      } finally {
        this.$set(this.saving, key, false)
      }
    },

    async toggleProviderEnabled(provider) {
      try {
        await this.$http.put('/marketing/agents-config/providers/' + provider.id, {
          enabled: provider.enabled,
        })
        this.$message.success(provider.display_name + ' ' + (provider.enabled ? 'habilitado' : 'deshabilitado'))
      } catch (e) {
        provider.enabled = !provider.enabled // revertir
        this.$message.error('Error cambiando estado')
      }
    },

    onImageProviderChange(agentKey) {
      // Limpiar overrides incompatibles con el nuevo provider
      const provider = this.selectedImageProvider(agentKey)
      if (!provider) {
        this.forms[agentKey].image_model_override = null
        this.forms[agentKey].image_quality = null
        return
      }
      // Si el quality actual no está en los soportados, limpiar
      const qualities = provider.supported_qualities || []
      if (this.forms[agentKey].image_quality && qualities.length && qualities.indexOf(this.forms[agentKey].image_quality) === -1) {
        this.forms[agentKey].image_quality = qualities[0] || null
      }
    },

    selectedTextProvider(agentKey) {
      const id = this.forms[agentKey] && this.forms[agentKey].text_provider_id
      return this.providers.find(p => p.id === id)
    },
    selectedImageProvider(agentKey) {
      const id = this.forms[agentKey] && this.forms[agentKey].image_provider_id
      return this.providers.find(p => p.id === id)
    },
    textModelOptions(agentKey) {
      const p = this.selectedTextProvider(agentKey)
      return p && Array.isArray(p.supported_models) ? p.supported_models : []
    },
    imageModelOptions(agentKey) {
      const p = this.selectedImageProvider(agentKey)
      return p && Array.isArray(p.supported_models) ? p.supported_models : []
    },
    imageQualityOptions(agentKey) {
      const p = this.selectedImageProvider(agentKey)
      return p && Array.isArray(p.supported_qualities) ? p.supported_qualities : []
    },
    imageRatioOptions(agentKey) {
      const p = this.selectedImageProvider(agentKey)
      return p && Array.isArray(p.supported_aspect_ratios) ? p.supported_aspect_ratios : ['1:1', '4:5', '16:9']
    },

    agentLabel(key) {
      const map = {
        mkt01: 'MKT-01 Chatbot',
        com02: 'COM-02 Cotizador',
        mkt03: 'MKT-03 Social Media',
      }
      return map[key] || key
    },
    agentIcon(key) {
      const map = {
        mkt01: 'el-icon-chat-line-square',
        com02: 'el-icon-document',
        mkt03: 'el-icon-picture-outline',
      }
      return map[key] || 'el-icon-cpu'
    },
  },
}
</script>

<style scoped>
.agents-config {
  padding: 16px;
}
.ac-header {
  margin-bottom: 16px;
}
.ac-title {
  margin: 0 0 4px 0;
  font-size: 22px;
  font-weight: 600;
}
.ac-subtitle {
  margin: 0;
  color: #909399;
  font-size: 13px;
}
.resolved-banner {
  margin-bottom: 16px;
}
.resolved-banner code {
  background: rgba(0,0,0,0.05);
  padding: 1px 5px;
  border-radius: 3px;
  font-size: 12px;
}
.ac-section {
  margin-top: 16px;
  padding: 16px;
  background: #fafbfc;
  border-radius: 6px;
  border: 1px solid #ebeef5;
}
.ac-section-title {
  margin: 0 0 12px 0;
  font-size: 15px;
  color: #303133;
}
.ac-actions {
  margin-top: 20px;
  text-align: right;
  padding-top: 12px;
  border-top: 1px solid #ebeef5;
}
.ac-hint {
  display: block;
  color: #909399;
  font-size: 11px;
  margin-top: 2px;
  line-height: 1.3;
}
.text-muted {
  color: #909399;
}
.providers-list code {
  background: rgba(0,0,0,0.05);
  padding: 1px 5px;
  border-radius: 3px;
  font-size: 12px;
}
</style>
