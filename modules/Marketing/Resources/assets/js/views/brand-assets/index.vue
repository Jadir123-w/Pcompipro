<template>
  <div class="brand-asset-library">
    <!-- ═════════════════════════════════════════ -->
    <!-- Header + filtros                            -->
    <!-- ═════════════════════════════════════════ -->
    <div class="bal-header">
      <div>
        <h2 class="bal-title">🎨 Brand Asset Library</h2>
        <p class="bal-subtitle">Identidad visual y de marca compartida entre MKT-01 (Chatbot), COM-02 (Cotizador) y MKT-03 (Social Media)</p>
      </div>
      <div>
        <el-button type="primary" icon="el-icon-plus" @click="openCreate">Nuevo asset</el-button>
      </div>
    </div>

    <el-row :gutter="12" class="bal-filters" type="flex" align="middle">
      <el-col :span="5">
        <el-select v-model="filters.type" placeholder="Tipo" clearable @change="loadAssets">
          <el-option v-for="t in typeOptions" :key="t.value" :label="t.label" :value="t.value">
            <span style="margin-right: 6px;">{{ t.emoji }}</span>{{ t.label }}
          </el-option>
        </el-select>
      </el-col>
      <el-col :span="5">
        <el-select v-model="filters.agent" placeholder="Agente" clearable @change="loadAssets">
          <el-option label="MKT-01 Chatbot" value="mkt01"></el-option>
          <el-option label="COM-02 Cotizador" value="com02"></el-option>
          <el-option label="MKT-03 Social Media" value="mkt03"></el-option>
        </el-select>
      </el-col>
      <el-col :span="4">
        <el-select v-model="filters.active" placeholder="Estado" clearable @change="loadAssets">
          <el-option label="Activos" value="1"></el-option>
          <el-option label="Inactivos" value="0"></el-option>
        </el-select>
      </el-col>
      <el-col :span="6">
        <el-input v-model="filters.q" placeholder="Buscar por nombre…" clearable @keyup.native.enter="loadAssets">
          <el-button slot="append" icon="el-icon-search" @click="loadAssets"></el-button>
        </el-input>
      </el-col>
      <el-col :span="4" style="text-align: right;">
        <el-button icon="el-icon-refresh" @click="loadAssets" :loading="loading">Recargar</el-button>
      </el-col>
    </el-row>

    <!-- ═════════════════════════════════════════ -->
    <!-- Galería                                     -->
    <!-- ═════════════════════════════════════════ -->
    <div v-loading="loading" class="bal-gallery">
      <div v-if="!loading && assets.length === 0" class="bal-empty">
        <p>Sin assets. Click en <strong>"Nuevo asset"</strong> para empezar.</p>
        <p class="text-muted">Sugerencia: comienza subiendo el <strong>logo principal</strong>, luego los <strong>colores corporativos</strong>, y después fotos de productos hero para usar como referencia en MKT-03.</p>
      </div>

      <div v-for="asset in assets" :key="asset.id" class="bal-card" :class="{ 'inactive': !asset.active }">
        <!-- Preview según tipo -->
        <div class="bal-preview">
          <template v-if="isImageType(asset.type)">
            <img v-if="asset.public_url" :src="asset.public_url" :alt="asset.name" />
            <div v-else class="preview-placeholder">📷</div>
          </template>
          <template v-else-if="asset.type === 'color'">
            <div class="color-swatch" :style="{ backgroundColor: asset.hex_color || '#ccc' }">
              <span class="hex-label">{{ asset.hex_color }}</span>
            </div>
          </template>
          <template v-else-if="asset.type === 'brand_guideline'">
            <div class="text-preview">
              <span class="text-icon">📜</span>
              <p>{{ truncate(asset.text_value, 120) }}</p>
            </div>
          </template>
          <template v-else-if="asset.type === 'font'">
            <div class="font-preview">
              <span class="font-icon">𝒶𝒷𝒸</span>
              <p>{{ asset.text_value || asset.name }}</p>
            </div>
          </template>
        </div>

        <!-- Datos -->
        <div class="bal-meta">
          <div class="bal-name">{{ asset.name }}</div>
          <div class="bal-tags">
            <el-tag size="mini" :type="typeTagColor(asset.type)">{{ typeLabel(asset.type) }}</el-tag>
            <el-tag size="mini" v-if="!asset.active" type="info">Inactivo</el-tag>
          </div>
          <div class="bal-agents">
            <el-tag size="mini" effect="plain" v-if="asset.use_in_mkt01">💬 MKT-01</el-tag>
            <el-tag size="mini" effect="plain" v-if="asset.use_in_com02">💼 COM-02</el-tag>
            <el-tag size="mini" effect="plain" v-if="asset.use_in_mkt03">📱 MKT-03</el-tag>
            <span class="text-muted" v-if="!asset.use_in_mkt01 && !asset.use_in_com02 && !asset.use_in_mkt03">
              <em>(no asignado a agentes)</em>
            </span>
          </div>
          <div class="bal-tags-list" v-if="Array.isArray(asset.tags) && asset.tags.length">
            <el-tag size="mini" v-for="t in asset.tags" :key="t" type="info" effect="plain">#{{ t }}</el-tag>
          </div>
        </div>

        <!-- Acciones -->
        <div class="bal-actions">
          <el-button size="mini" icon="el-icon-edit" @click="openEdit(asset)">Editar</el-button>
          <el-button size="mini" type="danger" icon="el-icon-delete" @click="confirmDelete(asset)"></el-button>
        </div>
      </div>
    </div>

    <!-- Paginación -->
    <div class="bal-pagination" v-if="pagination.total > pagination.per_page">
      <el-pagination
        layout="prev, pager, next, total"
        :current-page="pagination.current_page"
        :page-size="pagination.per_page"
        :total="pagination.total"
        @current-change="onPageChange"
      ></el-pagination>
    </div>

    <!-- ═════════════════════════════════════════ -->
    <!-- Dialog crear / editar                       -->
    <!-- ═════════════════════════════════════════ -->
    <el-dialog
      :title="editingId ? 'Editar asset' : 'Nuevo asset'"
      :visible.sync="formVisible"
      width="640px"
      :close-on-click-modal="false"
      append-to-body
      custom-class="bal-dialog"
    >
      <el-form label-position="top" v-loading="saving">
        <!-- Tipo (solo en crear) -->
        <el-form-item label="Tipo de asset" v-if="!editingId">
          <el-radio-group v-model="form.type" size="medium">
            <el-radio-button v-for="t in typeOptions" :key="t.value" :label="t.value">
              <span>{{ t.emoji }}</span> {{ t.label }}
            </el-radio-button>
          </el-radio-group>
        </el-form-item>

        <!-- Campos comunes -->
        <el-form-item label="Nombre" required>
          <el-input v-model="form.name" placeholder="Ej: Logo COMPIPRO horizontal blanco"></el-input>
        </el-form-item>

        <el-form-item label="Descripción (opcional)">
          <el-input v-model="form.description" type="textarea" :rows="2" placeholder="Para uso interno del equipo"></el-input>
        </el-form-item>

        <!-- Campo según tipo -->
        <el-form-item v-if="isImageType(form.type)" label="Archivo">
          <input ref="fileInput" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" @change="onFilePicked" />
          <div class="text-muted" style="font-size: 12px; margin-top: 4px;">
            PNG / JPEG / WEBP / SVG, máx 10 MB
          </div>
          <div v-if="form.filePreview" style="margin-top: 8px;">
            <img :src="form.filePreview" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;" />
          </div>
          <div v-if="editingId && form.currentUrl && !form.filePreview" style="margin-top: 8px;">
            <p class="text-muted" style="font-size: 12px;">Archivo actual:</p>
            <img :src="form.currentUrl" style="max-width: 200px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;" />
          </div>
        </el-form-item>

        <el-form-item v-if="form.type === 'color'" label="Color (hex)">
          <el-color-picker v-model="form.hex_color" show-alpha></el-color-picker>
          <span style="margin-left: 12px; font-family: monospace;">{{ form.hex_color || '—' }}</span>
        </el-form-item>

        <el-form-item v-if="form.type === 'brand_guideline'" label="Texto de la guía">
          <el-input v-model="form.text_value" type="textarea" :rows="4" placeholder="Ej: Tono cercano, B2B. Evitar: 'barato', 'oferta'. Preferir: 'inversión', 'eficiencia'."></el-input>
        </el-form-item>

        <el-form-item v-if="form.type === 'font'" label="Familia o nombre">
          <el-input v-model="form.text_value" placeholder="Ej: Inter, peso 600"></el-input>
        </el-form-item>

        <!-- Tags -->
        <el-form-item label="Tags">
          <el-select
            v-model="form.tags"
            multiple
            filterable
            allow-create
            default-first-option
            placeholder="Ej: primary, hero, header"
            style="width: 100%;"
          >
            <el-option v-for="t in tagSuggestions" :key="t" :label="t" :value="t"></el-option>
          </el-select>
        </el-form-item>

        <!-- Usage context -->
        <el-form-item label="Contexto de uso (hint para IA)">
          <el-input v-model="form.usage_context" type="textarea" :rows="2" placeholder="Ej: Usar como referencia visual default en piezas de Instagram. Esquina inferior derecha."></el-input>
        </el-form-item>

        <!-- Agentes -->
        <el-form-item label="Disponible para">
          <el-checkbox v-model="form.use_in_mkt01">💬 MKT-01 Chatbot</el-checkbox>
          <el-checkbox v-model="form.use_in_com02">💼 COM-02 Cotizador</el-checkbox>
          <el-checkbox v-model="form.use_in_mkt03">📱 MKT-03 Social Media</el-checkbox>
        </el-form-item>

        <!-- Estado + orden -->
        <el-row :gutter="12">
          <el-col :span="12">
            <el-form-item label="Estado">
              <el-switch v-model="form.active" active-text="Activo" inactive-text="Inactivo"></el-switch>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Orden">
              <el-input-number v-model="form.sort_order" :min="0" :max="9999"></el-input-number>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>

      <div slot="footer">
        <el-button @click="formVisible = false">Cancelar</el-button>
        <el-button type="primary" @click="save" :loading="saving" icon="el-icon-check">
          {{ editingId ? 'Guardar cambios' : 'Crear asset' }}
        </el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  data() {
    return {
      assets: [],
      loading: false,
      saving: false,
      formVisible: false,
      editingId: null,
      filters: {
        type: '',
        agent: '',
        active: '1',
        q: '',
      },
      pagination: {
        current_page: 1,
        per_page: 24,
        total: 0,
      },
      form: this.emptyForm(),
      typeOptions: [
        { value: 'logo', label: 'Logo', emoji: '🏷️' },
        { value: 'color', label: 'Color', emoji: '🎨' },
        { value: 'product_photo', label: 'Foto producto', emoji: '📷' },
        { value: 'brand_guideline', label: 'Guía de marca', emoji: '📜' },
        { value: 'icon', label: 'Ícono', emoji: '🔖' },
        { value: 'font', label: 'Tipografía', emoji: '🔤' },
      ],
      tagSuggestions: [
        'primary', 'secondary', 'header', 'footer', 'background',
        'hero', 'mkt03-hero', 'thumbnail', 'mockup', 'oscuro', 'claro',
        'horizontal', 'vertical', 'redondo', 'cuadrado',
      ],
    }
  },
  created() {
    this.loadAssets()
  },
  methods: {
    emptyForm() {
      return {
        type: 'logo',
        name: '',
        description: '',
        hex_color: '',
        text_value: '',
        usage_context: '',
        tags: [],
        use_in_mkt01: false,
        use_in_com02: false,
        use_in_mkt03: false,
        active: true,
        sort_order: 0,
        file: null,
        filePreview: null,
        currentUrl: null,
      }
    },

    async loadAssets() {
      this.loading = true
      try {
        const params = {
          per_page: this.pagination.per_page,
          page: this.pagination.current_page,
        }
        if (this.filters.type)   params.type   = this.filters.type
        if (this.filters.agent)  params.agent  = this.filters.agent
        if (this.filters.active !== '') params.active = this.filters.active
        if (this.filters.q)      params.q      = this.filters.q

        const r = await this.$http.get('/marketing/brand-assets/list', { params })
        const data = r.data.assets
        this.assets = data.data || []
        this.pagination.current_page = data.current_page || 1
        this.pagination.per_page     = data.per_page     || 24
        this.pagination.total        = data.total        || 0
      } catch (e) {
        this.$message.error('Error cargando assets')
      }
      this.loading = false
    },

    onPageChange(page) {
      this.pagination.current_page = page
      this.loadAssets()
    },

    openCreate() {
      this.editingId = null
      this.form = this.emptyForm()
      if (this.$refs.fileInput) this.$refs.fileInput.value = ''
      this.formVisible = true
    },

    openEdit(asset) {
      this.editingId = asset.id
      this.form = {
        type: asset.type,
        name: asset.name || '',
        description: asset.description || '',
        hex_color: asset.hex_color || '',
        text_value: asset.text_value || '',
        usage_context: asset.usage_context || '',
        tags: Array.isArray(asset.tags) ? asset.tags.slice() : [],
        use_in_mkt01: !!asset.use_in_mkt01,
        use_in_com02: !!asset.use_in_com02,
        use_in_mkt03: !!asset.use_in_mkt03,
        active: !!asset.active,
        sort_order: asset.sort_order || 0,
        file: null,
        filePreview: null,
        currentUrl: asset.public_url || null,
      }
      if (this.$refs.fileInput) this.$refs.fileInput.value = ''
      this.formVisible = true
    },

    onFilePicked(e) {
      const file = e.target.files && e.target.files[0]
      if (!file) {
        this.form.file = null
        this.form.filePreview = null
        return
      }
      if (file.size > 10 * 1024 * 1024) {
        this.$message.error('Archivo excede 10 MB')
        e.target.value = ''
        return
      }
      this.form.file = file
      const reader = new FileReader()
      reader.onload = ev => { this.form.filePreview = ev.target.result }
      reader.readAsDataURL(file)
    },

    async save() {
      if (!this.form.name) {
        this.$message.error('Falta el nombre')
        return
      }

      // Validación específica por tipo
      if (this.isImageType(this.form.type) && !this.editingId && !this.form.file) {
        this.$message.error('Debes seleccionar un archivo')
        return
      }
      if (this.form.type === 'color' && !this.form.hex_color) {
        this.$message.error('Debes elegir un color')
        return
      }
      if ((this.form.type === 'brand_guideline' || this.form.type === 'font') && !this.form.text_value) {
        this.$message.error('Debes escribir el texto')
        return
      }

      this.saving = true
      try {
        if (this.editingId) {
          // Update — JSON sin archivo
          await this.$http.put(`/marketing/brand-assets/${this.editingId}`, this.payloadJson())
          // Si hay archivo nuevo, llamar al endpoint dedicado
          if (this.form.file) {
            const fd = new FormData()
            fd.append('file', this.form.file)
            await this.$http.post(`/marketing/brand-assets/${this.editingId}/file`, fd, {
              headers: { 'Content-Type': 'multipart/form-data' },
            })
          }
          this.$message.success('Asset actualizado')
        } else {
          // Create — multipart si hay archivo, JSON si no
          if (this.form.file) {
            const fd = this.payloadFormData()
            await this.$http.post('/marketing/brand-assets', fd, {
              headers: { 'Content-Type': 'multipart/form-data' },
            })
          } else {
            await this.$http.post('/marketing/brand-assets', this.payloadJson())
          }
          this.$message.success('Asset creado')
        }

        this.formVisible = false
        this.loadAssets()
      } catch (e) {
        const msg = (e.response && e.response.data && (e.response.data.message ||
                    (e.response.data.errors && JSON.stringify(e.response.data.errors)))) || 'Error guardando'
        this.$message.error(msg)
      }
      this.saving = false
    },

    payloadJson() {
      return {
        type: this.form.type,
        name: this.form.name,
        description: this.form.description,
        hex_color: this.form.hex_color,
        text_value: this.form.text_value,
        usage_context: this.form.usage_context,
        tags: this.form.tags,
        use_in_mkt01: this.form.use_in_mkt01,
        use_in_com02: this.form.use_in_com02,
        use_in_mkt03: this.form.use_in_mkt03,
        active: this.form.active,
        sort_order: this.form.sort_order,
      }
    },

    payloadFormData() {
      const fd = new FormData()
      fd.append('type', this.form.type)
      fd.append('name', this.form.name)
      if (this.form.description)   fd.append('description', this.form.description)
      if (this.form.hex_color)     fd.append('hex_color', this.form.hex_color)
      if (this.form.text_value)    fd.append('text_value', this.form.text_value)
      if (this.form.usage_context) fd.append('usage_context', this.form.usage_context)
      fd.append('tags', JSON.stringify(this.form.tags || []))
      fd.append('use_in_mkt01', this.form.use_in_mkt01 ? '1' : '0')
      fd.append('use_in_com02', this.form.use_in_com02 ? '1' : '0')
      fd.append('use_in_mkt03', this.form.use_in_mkt03 ? '1' : '0')
      fd.append('active', this.form.active ? '1' : '0')
      fd.append('sort_order', String(this.form.sort_order || 0))
      if (this.form.file) fd.append('file', this.form.file)
      return fd
    },

    confirmDelete(asset) {
      this.$confirm(
        `¿Eliminar el asset "${asset.name}"? Esta acción es reversible (soft delete).`,
        'Confirmar eliminación',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
      ).then(async () => {
        try {
          await this.$http.delete(`/marketing/brand-assets/${asset.id}`)
          this.$message.success('Asset eliminado')
          this.loadAssets()
        } catch (e) {
          this.$message.error('Error eliminando')
        }
      }).catch(() => {})
    },

    isImageType(type) {
      return ['logo', 'product_photo', 'icon'].indexOf(type) !== -1
    },

    typeLabel(type) {
      const found = this.typeOptions.find(t => t.value === type)
      return found ? `${found.emoji} ${found.label}` : type
    },

    typeTagColor(type) {
      const map = {
        logo: 'primary',
        color: 'success',
        product_photo: 'warning',
        brand_guideline: 'info',
        icon: 'primary',
        font: 'info',
      }
      return map[type] || 'info'
    },

    truncate(s, n) {
      if (!s) return ''
      return s.length > n ? s.substring(0, n) + '…' : s
    },
  },
}
</script>

<style scoped>
.brand-asset-library {
  padding: 16px;
}

.bal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 18px;
}

.bal-title {
  margin: 0 0 4px 0;
  font-size: 22px;
  font-weight: 600;
}

.bal-subtitle {
  margin: 0;
  color: #909399;
  font-size: 13px;
}

.bal-filters {
  margin-bottom: 18px;
  padding: 12px;
  background: #fafafa;
  border-radius: 6px;
}

.bal-gallery {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
  min-height: 200px;
}

.bal-empty {
  grid-column: 1 / -1;
  text-align: center;
  padding: 60px 20px;
  color: #909399;
}

.bal-card {
  border: 1px solid #ebeef5;
  border-radius: 8px;
  background: #fff;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: box-shadow 0.2s;
}
.bal-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}
.bal-card.inactive {
  opacity: 0.55;
}

.bal-preview {
  width: 100%;
  height: 160px;
  background: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}
.bal-preview img {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.color-swatch {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 8px;
}
.color-swatch .hex-label {
  background: rgba(255, 255, 255, 0.85);
  padding: 4px 8px;
  border-radius: 4px;
  font-family: monospace;
  font-size: 12px;
  font-weight: 600;
}

.preview-placeholder {
  font-size: 48px;
  color: #c0c4cc;
}

.text-preview, .font-preview {
  padding: 12px;
  text-align: center;
}
.text-icon {
  font-size: 32px;
  display: block;
  margin-bottom: 8px;
}
.text-preview p {
  font-size: 12px;
  color: #606266;
  margin: 0;
  font-style: italic;
}
.font-icon {
  font-size: 36px;
  display: block;
  margin-bottom: 8px;
  color: #409EFF;
}
.font-preview p {
  margin: 0;
  font-weight: 600;
  color: #303133;
}

.bal-meta {
  padding: 10px 12px;
  flex-grow: 1;
}
.bal-name {
  font-weight: 600;
  margin-bottom: 6px;
  font-size: 14px;
}
.bal-tags, .bal-agents, .bal-tags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-bottom: 4px;
}

.bal-actions {
  padding: 8px 10px;
  border-top: 1px solid #ebeef5;
  display: flex;
  justify-content: space-between;
  background: #fafafa;
}

.bal-pagination {
  margin-top: 24px;
  text-align: center;
}

.text-muted {
  color: #909399;
}
</style>

<!-- Estilos NO scoped para el dialog (Element UI usa append-to-body, fuera del scope del componente) -->
<style>
.bal-dialog .el-form-item__label {
  width: 100% !important;
  float: none !important;
  text-align: left !important;
  padding: 0 !important;
  line-height: 1.4 !important;
  font-weight: 500;
  margin-bottom: 4px;
}
.bal-dialog .el-form-item__content {
  margin-left: 0 !important;
  line-height: 1.4 !important;
}
.bal-dialog .el-form-item {
  margin-bottom: 18px;
}
.bal-dialog .el-form-item.is-required:not(.is-no-asterisk) > .el-form-item__label::before {
  margin-right: 4px;
}
</style>
