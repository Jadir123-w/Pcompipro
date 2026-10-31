<template>
  <div>
    <el-row :gutter="15" class="mb-3">
      <el-col :span="6">
        <el-select v-model="filters.red_social" clearable placeholder="Red social" @change="loadPieces">
          <el-option v-for="r in redes" :key="r" :label="r" :value="r"></el-option>
        </el-select>
      </el-col>
      <el-col :span="6">
        <el-select v-model="filters.estado_pieza" clearable placeholder="Estado" @change="loadPieces">
          <el-option v-for="e in estados" :key="e" :label="e" :value="e"></el-option>
        </el-select>
      </el-col>
      <el-col :span="4">
        <el-button icon="el-icon-refresh" @click="loadPieces">Recargar</el-button>
      </el-col>
    </el-row>

    <el-table :data="pieces" stripe border v-loading="loading" empty-text="Sin piezas">
      <el-table-column prop="id" label="ID" width="60"></el-table-column>
      <el-table-column prop="red_social" label="Red" width="100">
        <template slot-scope="scope">
          <el-tag size="mini">{{ scope.row.red_social }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Copy (preview)" min-width="250">
        <template slot-scope="scope">
          {{ scope.row.copy ? scope.row.copy.substring(0, 100) + '...' : '-' }}
        </template>
      </el-table-column>
      <el-table-column prop="formato" label="Formato" width="100"></el-table-column>
      <el-table-column label="Imagen" width="80" align="center">
        <template slot-scope="scope">
          <el-tag v-if="scope.row.imagen_final_url" type="success" size="mini">OK</el-tag>
          <el-tag v-else type="info" size="mini">-</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Estado" width="140">
        <template slot-scope="scope">
          <el-tag :type="estadoType(scope.row.estado_pieza)" size="small">{{ scope.row.estado_pieza }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="180" align="center">
        <template slot-scope="scope">
          <el-button size="mini" type="primary" icon="el-icon-view" @click="openPiece(scope.row.id)">Ver</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- Dialog: Detalle de pieza -->
    <el-dialog :title="'Editar pieza #' + (detailPiece ? detailPiece.id : '')"
               :visible.sync="showDetail" width="min(850px, 94vw)" top="4vh" custom-class="mkt03-piece-dialog">
      <div v-if="detailPiece" v-loading="savingDetail" class="piece-dialog-content">
        <div class="piece-dialog-scroll">
          <div class="piece-summary">
            <div class="piece-summary-tags">
              <el-tag size="small" effect="plain">{{ detailPiece.red_social }}</el-tag>
              <el-tag size="small" type="info" effect="plain">{{ detailPiece.formato }}</el-tag>
              <el-tag :type="estadoType(detailPiece.estado_pieza)" size="small">
                {{ detailPiece.estado_pieza }}
              </el-tag>
            </div>
            <p class="piece-dialog-hint">
              Ajusta el texto principal. Los campos creativos y hashtags están en «Más detalles».
            </p>
          </div>

          <el-form label-position="top" size="small" class="piece-edit-form">
            <el-form-item label="Copy">
              <el-input type="textarea" v-model="detailPiece.copy" :rows="5"
                        placeholder="Escribe el texto de la publicación"></el-input>
            </el-form-item>
            <el-form-item label="Llamado a la acción (CTA)">
              <el-input v-model="detailPiece.cta" placeholder="Ej. Escríbenos para más información"></el-input>
            </el-form-item>

            <el-button type="text" class="piece-details-toggle"
                       :aria-expanded="showMoreDetails ? 'true' : 'false'"
                       @click="showMoreDetails = !showMoreDetails">
              <i :class="showMoreDetails ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"></i>
              {{ showMoreDetails ? 'Ocultar detalles' : 'Más detalles' }}
            </el-button>

            <el-collapse-transition>
              <div v-show="showMoreDetails" class="piece-more-details">
                <el-form-item label="Hashtags">
                  <el-input v-model="detailPiece.hashtags" placeholder="#marca #tema"></el-input>
                </el-form-item>
                <el-form-item label="Texto en imagen o video (brief)">
                  <el-input type="textarea" v-model="detailPiece.texto_en_imagen_video" :rows="2"></el-input>
                </el-form-item>
                <el-form-item label="Enfoque visual">
                  <el-input type="textarea" v-model="detailPiece.enfoque_visual" :rows="2"></el-input>
                </el-form-item>
                <el-form-item label="Prompt de imagen">
                  <el-input type="textarea" v-model="detailPiece.imagen_prompt" :rows="3"></el-input>
                </el-form-item>
              </div>
            </el-collapse-transition>

            <div v-if="detailPiece.imagen_final_url" class="piece-image-preview">
              <span class="piece-image-label">Imagen generada</span>
              <img :src="detailPiece.imagen_final_url" alt="Imagen generada para la pieza" />
            </div>
          </el-form>
        </div>
      </div>

      <div slot="footer" class="piece-dialog-footer">
        <el-button @click="showDetail = false">Cerrar</el-button>
        <div class="piece-dialog-actions">
          <el-button @click="regenerateImage" :loading="regenerating" icon="el-icon-picture">
            {{ detailPiece && detailPiece.imagen_final_url ? 'Regenerar imagen' : 'Generar imagen' }}
          </el-button>
          <el-button v-if="detailPiece && detailPiece.estado_pieza !== 'lista_publicar'"
                     type="success" @click="approve" icon="el-icon-check">Aprobar</el-button>
          <el-button type="primary" @click="saveEdits" :loading="savingDetail" icon="el-icon-check">
            Guardar cambios
          </el-button>
        </div>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  data() {
    return {
      pieces: [],
      loading: false,
      filters: { red_social: '', estado_pieza: '' },
      redes: ['facebook', 'instagram', 'linkedin', 'tiktok'],
      estados: ['pendiente', 'en_produccion', 'lista_publicar', 'publicada', 'observada', 'descartada'],
      showDetail: false,
      showMoreDetails: false,
      detailPiece: null,
      savingDetail: false,
      regenerating: false,
    }
  },
  created() {
    this.loadPieces()
  },
  methods: {
    async loadPieces() {
      this.loading = true
      try {
        var params = {}
        if (this.filters.red_social) params.red_social = this.filters.red_social
        if (this.filters.estado_pieza) params.estado_pieza = this.filters.estado_pieza
        var r = await this.$http.get('/marketing/mkt03/pieces', { params: params })
        this.pieces = (r.data.pieces && r.data.pieces.data) || r.data.pieces || []
      } catch (e) {
        this.$message.error('Error cargando piezas')
      }
      this.loading = false
    },

    async openPiece(id) {
      this.savingDetail = true
      this.showDetail = true
      this.showMoreDetails = false
      try {
        var r = await this.$http.get('/marketing/mkt03/pieces/' + id)
        this.detailPiece = r.data.piece
      } catch (e) {
        this.$message.error('Error cargando pieza')
      }
      this.savingDetail = false
    },

    async saveEdits() {
      this.savingDetail = true
      try {
        var payload = {
          copy: this.detailPiece.copy,
          cta: this.detailPiece.cta,
          hashtags: this.detailPiece.hashtags,
          texto_en_imagen_video: this.detailPiece.texto_en_imagen_video,
          enfoque_visual: this.detailPiece.enfoque_visual,
          imagen_prompt: this.detailPiece.imagen_prompt,
        }
        var r = await this.$http.put('/marketing/mkt03/pieces/' + this.detailPiece.id, payload)
        this.detailPiece = r.data.piece
        this.$message.success('Pieza actualizada')
        this.loadPieces()
      } catch (e) {
        this.$message.error('Error guardando')
      }
      this.savingDetail = false
    },

    async regenerateImage() {
      this.regenerating = true
      try {
        var r = await this.$http.post(
          '/marketing/mkt03/pieces/' + this.detailPiece.id + '/regenerate-image',
          { imagen_prompt: this.detailPiece.imagen_prompt }
        )
        this.detailPiece = r.data.piece
        this.$message.success('Imagen generada')
        this.loadPieces()
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error generando imagen'
        this.$message.error(msg)
      }
      this.regenerating = false
    },

    async approve() {
      try {
        await this.$http.put(
          '/marketing/mkt03/pieces/' + this.detailPiece.id + '/status',
          { estado_pieza: 'lista_publicar' }
        )
        this.$message.success('Pieza aprobada')
        this.openPiece(this.detailPiece.id)
        this.loadPieces()
      } catch (e) {
        this.$message.error('Error aprobando')
      }
    },

    estadoType(estado) {
      var map = {
        'pendiente': 'info', 'en_produccion': 'warning', 'lista_publicar': 'success',
        'publicada': 'success', 'observada': 'warning', 'descartada': 'danger',
      }
      return map[estado] || 'info'
    }
  }
}
</script>

<style scoped>
.piece-dialog-content {
  min-height: 120px;
}

.piece-dialog-scroll {
  max-height: 68vh;
  overflow-y: auto;
  padding: 0 12px 8px 0;
}

.piece-summary {
  padding: 12px 14px;
  margin-bottom: 16px;
  background: #f5f7fa;
  border: 1px solid #ebeef5;
  border-radius: 6px;
}

.piece-summary-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.piece-dialog-hint {
  margin: 8px 0 0;
  color: #606266;
  font-size: 13px;
}

.piece-edit-form ::v-deep .el-form-item {
  margin-bottom: 14px;
}

.piece-edit-form ::v-deep .el-form-item__label {
  padding-bottom: 4px;
  line-height: 20px;
}

.piece-details-toggle {
  padding: 4px 0 12px;
  font-size: 14px;
}

.piece-details-toggle i {
  margin-right: 4px;
}

.piece-more-details {
  padding: 14px 14px 1px;
  margin-bottom: 16px;
  background: #fafafa;
  border: 1px solid #ebeef5;
  border-radius: 6px;
}

.piece-image-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid #ebeef5;
}

.piece-image-label {
  color: #606266;
  font-size: 13px;
  font-weight: 600;
}

.piece-image-preview img {
  max-width: 100%;
  max-height: 320px;
  border: 1px solid #ddd;
  border-radius: 8px;
  object-fit: contain;
}

.piece-dialog-footer,
.piece-dialog-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.piece-dialog-footer {
  justify-content: space-between;
}

.piece-dialog-actions {
  flex-wrap: wrap;
  justify-content: flex-end;
}

@media (max-width: 640px) {
  .piece-dialog-scroll {
    max-height: 62vh;
    padding-right: 6px;
  }

  .piece-dialog-footer,
  .piece-dialog-actions {
    align-items: stretch;
    flex-direction: column-reverse;
    width: 100%;
  }

  .piece-dialog-footer ::v-deep .el-button {
    margin-left: 0;
  }
}
</style>
