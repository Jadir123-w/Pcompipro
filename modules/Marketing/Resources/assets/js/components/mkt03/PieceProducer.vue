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
      <el-table-column label="Acciones" width="240" align="center">
        <template slot-scope="scope">
          <el-button size="mini" type="primary" icon="el-icon-view" @click="openPiece(scope.row.id)">Ver</el-button>
          <el-button size="mini" type="danger" icon="el-icon-delete" @click="deleteItem(scope.row)" :loading="deletingItemId === scope.row.id">Borrar</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- Dialog: Detalle de pieza -->
    <el-dialog :visible.sync="showDetail" width="min(850px, 94vw)" top="4vh" custom-class="mkt03-piece-dialog">
      <div slot="title" class="piece-dialog-heading">
        <span>
          <strong>Editar pieza</strong>
          <small v-if="detailPiece">Contenido #{{ detailPiece.id }}</small>
        </span>
      </div>
      <div v-if="detailPiece" v-loading="savingDetail" class="piece-dialog-content">
        <div class="piece-dialog-scroll">
          <div class="piece-summary">
            <div class="piece-summary-heading">
              <span class="piece-summary-kicker">CONTENIDO DE PUBLICACIÓN</span>
              <span class="piece-summary-id">ID {{ detailPiece.id }}</span>
            </div>
            <div class="piece-summary-tags">
              <el-tag size="small" effect="plain"><i class="el-icon-share"></i> {{ detailPiece.red_social }}</el-tag>
              <el-tag size="small" type="info" effect="plain"><i class="el-icon-document"></i> {{ detailPiece.formato }}</el-tag>
              <el-tag :type="estadoType(detailPiece.estado_pieza)" size="small">
                <i class="el-icon-time"></i> {{ estadoLabel(detailPiece.estado_pieza) }}
              </el-tag>
            </div>
            <p class="piece-dialog-hint">
              Personaliza el texto de tu publicación. Los recursos creativos están en «Más detalles».
            </p>
          </div>

          <el-form label-position="top" size="small" class="piece-edit-form">
            <div class="piece-form-section">
              <div class="piece-section-heading">
                <span class="piece-section-icon"><i class="el-icon-chat-line-round"></i></span>
                <span>
                  <strong>Mensaje principal</strong>
                  <small>El texto que verá tu audiencia</small>
                </span>
              </div>
              <el-form-item label="Copy">
                <el-input type="textarea" v-model="detailPiece.copy" :rows="5"
                          placeholder="Escribe el texto de la publicación"></el-input>
              </el-form-item>
              <el-form-item label="Llamado a la acción (CTA)">
                <el-input v-model="detailPiece.cta" placeholder="Ej. Escríbenos para más información"></el-input>
              </el-form-item>
              <el-form-item label="Estado de la pieza">
                <el-select v-model="detailPiece.estado_pieza" style="width:100%">
                  <el-option v-for="estado in estados" :key="estado" :label="estadoLabel(estado)" :value="estado"></el-option>
                </el-select>
              </el-form-item>
            </div>

            <el-button type="text" class="piece-details-toggle"
                       :aria-expanded="showMoreDetails ? 'true' : 'false'"
                       @click="showMoreDetails = !showMoreDetails">
              <span class="piece-details-toggle-icon">
                <i :class="showMoreDetails ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"></i>
              </span>
              <span>{{ showMoreDetails ? 'Ocultar detalles' : 'Más detalles creativos' }}</span>
            </el-button>

            <el-collapse-transition>
              <div v-show="showMoreDetails" class="piece-more-details">
                <div class="piece-section-heading">
                  <span class="piece-section-icon"><i class="el-icon-picture-outline"></i></span>
                  <span>
                    <strong>Recursos creativos</strong>
                    <small>Hashtags, contenido visual y prompt de imagen</small>
                  </span>
                </div>
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
              <div class="piece-image-heading">
                <span class="piece-section-icon"><i class="el-icon-picture"></i></span>
                <span>
                  <strong>Imagen de la publicación</strong>
                  <small>Vista previa del recurso generado</small>
                </span>
              </div>
              <div class="piece-image-frame">
                <img :src="detailPiece.imagen_final_url" alt="Imagen generada para la pieza" />
              </div>
            </div>
          </el-form>
        </div>
      </div>

      <div slot="footer" class="piece-dialog-footer">
        <el-button class="piece-close-button" @click="showDetail = false" icon="el-icon-close">Cerrar</el-button>
        <div class="piece-dialog-actions">
          <el-button class="piece-image-button" @click="regenerateImage" :loading="regenerating" icon="el-icon-picture">
            {{ detailPiece && detailPiece.imagen_final_url ? 'Regenerar imagen' : 'Generar imagen' }}
          </el-button>
          <el-button v-if="detailPiece && detailPiece.estado_pieza !== 'aprobado'"
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
      estados: ['pendiente', 'por_revisar', 'aprobado', 'publicado', 'observada', 'programado'],
      showDetail: false,
      showMoreDetails: false,
      detailPiece: null,
      savingDetail: false,
      regenerating: false,
      // Ítem borrando (spinner del botón de borrar)
      deletingItemId: null,
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
          estado_pieza: this.detailPiece.estado_pieza,
        }
        var r = await this.$http.put('/marketing/mkt03/pieces/' + this.detailPiece.id, payload)
        this.detailPiece = r.data.piece
        this.$message.success('Pieza actualizada')
        this.loadPieces()
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error guardando pieza'
        this.$message.error(msg)
        this.loadPieces()
      } finally {
        this.savingDetail = false
      }
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
          { estado_pieza: 'aprobado' }
        )
        this.$message.success('Pieza aprobada')
        this.openPiece(this.detailPiece.id)
        this.loadPieces()
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error aprobando'
        this.$message.error(msg)
      }
    },

    async deleteItem(item) {
      if (!item) return
      try {
        await this.$confirm(
          '¿Seguro que quieres eliminar esta pieza?',
          'Eliminar pieza',
          { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
        )
      } catch (e) {
        return // el usuario canceló
      }
      try {
        await this.$http.delete(
          '/marketing/mkt03/pieces/' + item.id
        )
        this.$message.success('Pieza eliminada')
        this.loadPieces()
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error eliminando'
        this.$message.error(msg)
      }
    },

    estadoType(estado) {
      var map = {
        'pendiente': 'info', 'por_revisar': 'warning', 'aprobado': 'success',
        'publicado': 'success', 'observada': 'warning',
      }
      return map[estado] || 'info'
    },

    estadoLabel(estado) {
      var labels = {
        pendiente: 'Pendiente',
        por_revisar: 'Por revisar',
        aprobado: 'Aprobada · lista para publicar',
        publicada: 'Publicada',
        observada: 'Observada',
        programado: 'Programada',
      }
      return labels[estado] || estado
    }
  }
}
</script>

<style scoped>
.piece-dialog-content {
  min-height: 160px;
}

.piece-dialog-scroll {
  max-height: 66vh;
  overflow-y: auto;
  padding: 2px 12px 12px 2px;
  scrollbar-color: #cbd5e1 transparent;
  scrollbar-width: thin;
}

.piece-dialog-heading {
  display: flex;
  align-items: center;
  gap: 12px;
}

.piece-section-icon,
.piece-details-toggle-icon {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
}

.piece-dialog-heading strong,
.piece-dialog-heading small,
.piece-section-heading strong,
.piece-section-heading small,
.piece-image-heading strong,
.piece-image-heading small {
  display: block;
}

.piece-dialog-heading strong {
  color: #172033;
  font-size: 17px;
  line-height: 1.35;
}

.piece-dialog-heading small,
.piece-section-heading small,
.piece-image-heading small {
  margin-top: 3px;
  color: #7b8798;
  font-size: 12px;
  font-weight: 400;
}

.piece-summary {
  padding: 17px 19px;
  margin-bottom: 18px;
  background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
  border: 1px solid #e3eaf5;
  border-radius: 12px;
}

.piece-summary-heading {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 11px;
}

.piece-summary-kicker {
  color: #64748b;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .1em;
}

.piece-summary-id {
  padding: 3px 8px;
  color: #475569;
  background: rgba(255, 255, 255, .8);
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  font-size: 11px;
}

.piece-summary-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.piece-summary-tags ::v-deep .el-tag {
  height: 27px;
  padding: 0 10px;
  border-radius: 7px;
}

.piece-summary-tags ::v-deep .el-tag i {
  margin-right: 4px;
}

.piece-dialog-hint {
  margin: 10px 0 0;
  color: #64748b;
  font-size: 13px;
  line-height: 1.5;
}

.piece-form-section {
  padding: 17px 19px 2px;
  margin-bottom: 13px;
  background: #fff;
  border: 1px solid #e7ebf2;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, .025);
}

.piece-section-heading,
.piece-image-heading {
  display: flex;
  align-items: center;
  gap: 10px;
}

.piece-section-heading {
  margin-bottom: 16px;
}

.piece-section-heading strong,
.piece-image-heading strong {
  color: #263449;
  font-size: 13px;
  font-weight: 650;
}

.piece-section-icon {
  width: 34px;
  height: 34px;
  color: #2563eb;
  background: #eff6ff;
  font-size: 16px;
}

.piece-edit-form ::v-deep .el-form-item {
  margin-bottom: 16px;
}

.piece-edit-form ::v-deep .el-form-item__label {
  padding-bottom: 6px;
  color: #475569;
  font-size: 12px;
  font-weight: 600;
  line-height: 20px;
}

.piece-edit-form ::v-deep .el-input__inner,
.piece-edit-form ::v-deep .el-textarea__inner {
  color: #263449;
  background: #fbfcfe;
  border-color: #dce3ed;
  border-radius: 8px;
  transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
}

.piece-edit-form ::v-deep .el-input__inner:hover,
.piece-edit-form ::v-deep .el-textarea__inner:hover {
  border-color: #aab9ce;
}

.piece-edit-form ::v-deep .el-input__inner:focus,
.piece-edit-form ::v-deep .el-textarea__inner:focus {
  background: #fff;
  border-color: #5283e8;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
}

.piece-details-toggle {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  padding: 4px 0 12px;
  color: #315fb7;
  font-size: 13px;
  font-weight: 600;
}

.piece-details-toggle-icon {
  width: 25px;
  height: 25px;
  color: #315fb7;
  background: #eff6ff;
  font-size: 12px;
}

.piece-more-details {
  padding: 17px 19px 2px;
  margin: 0 0 17px;
  background: #f8fafc;
  border: 1px solid #e4eaf2;
  border-radius: 12px;
}

.piece-image-preview {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 17px 19px;
  margin-top: 4px;
  background: #f8fafc;
  border: 1px solid #e4eaf2;
  border-radius: 12px;
}

.piece-image-frame {
  display: flex;
  justify-content: center;
  padding: 12px;
  overflow: hidden;
  background-color: #fff;
  background-image: linear-gradient(45deg, #f1f5f9 25%, transparent 25%),
    linear-gradient(-45deg, #f1f5f9 25%, transparent 25%),
    linear-gradient(45deg, transparent 75%, #f1f5f9 75%),
    linear-gradient(-45deg, transparent 75%, #f1f5f9 75%);
  background-position: 0 0, 0 8px, 8px -8px, -8px 0;
  background-size: 16px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.piece-image-preview img {
  max-width: 100%;
  max-height: 320px;
  border-radius: 6px;
  object-fit: contain;
  box-shadow: 0 5px 16px rgba(15, 23, 42, .12);
}

.piece-dialog-footer,
.piece-dialog-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.piece-dialog-footer {
  justify-content: space-between;
  width: 100%;
}

.piece-close-button,
.piece-image-button {
  border-radius: 8px;
  font-weight: 600;
}

.piece-image-button {
  color: #315fb7;
  background: #eff6ff;
  border-color: #dbeafe;
}

.piece-image-button:hover,
.piece-image-button:focus {
  color: #1d4ed8;
  background: #dbeafe;
  border-color: #bfdbfe;
}

.piece-dialog-actions {
  flex-wrap: wrap;
  justify-content: flex-end;
}

@media (max-width: 640px) {
  .piece-dialog-scroll {
    max-height: 58vh;
    padding-right: 6px;
  }

  .piece-summary,
  .piece-form-section,
  .piece-more-details,
  .piece-image-preview {
    padding-right: 13px;
    padding-left: 13px;
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
