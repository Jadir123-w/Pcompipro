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
    <el-dialog :title="'Pieza #' + (detailPiece ? detailPiece.id : '')" :visible.sync="showDetail" width="850px" top="5vh">
      <div v-if="detailPiece" v-loading="savingDetail">
        <el-row :gutter="15">
          <el-col :span="12">
            <strong>Red:</strong> {{ detailPiece.red_social }} |
            <strong>Formato:</strong> {{ detailPiece.formato }}
          </el-col>
          <el-col :span="12">
            <strong>Estado:</strong>
            <el-tag :type="estadoType(detailPiece.estado_pieza)" size="small">{{ detailPiece.estado_pieza }}</el-tag>
          </el-col>
        </el-row>

        <div style="margin-top: 15px;">
          <label><strong>Copy:</strong></label>
          <el-input type="textarea" v-model="detailPiece.copy" :rows="6"></el-input>
        </div>

        <div style="margin-top: 10px;">
          <label><strong>CTA:</strong></label>
          <el-input v-model="detailPiece.cta"></el-input>
        </div>

        <div style="margin-top: 10px;">
          <label><strong>Hashtags:</strong></label>
          <el-input v-model="detailPiece.hashtags"></el-input>
        </div>

        <div style="margin-top: 10px;">
          <label><strong>Texto en imagen/video (brief):</strong></label>
          <el-input type="textarea" v-model="detailPiece.texto_en_imagen_video" :rows="2"></el-input>
        </div>

        <div style="margin-top: 10px;">
          <label><strong>Enfoque visual:</strong></label>
          <el-input type="textarea" v-model="detailPiece.enfoque_visual" :rows="2"></el-input>
        </div>

        <div style="margin-top: 10px;">
          <label><strong>Prompt de imagen (DALL-E 3):</strong></label>
          <el-input type="textarea" v-model="detailPiece.imagen_prompt" :rows="3"></el-input>
        </div>

        <div v-if="detailPiece.imagen_final_url" style="margin-top: 15px; text-align: center;">
          <img :src="detailPiece.imagen_final_url" style="max-width: 400px; max-height: 400px; border: 1px solid #ddd; border-radius: 8px;" />
        </div>
      </div>

      <div slot="footer">
        <el-button @click="showDetail = false">Cerrar</el-button>
        <el-button type="warning" @click="saveEdits" :loading="savingDetail" icon="el-icon-check">Guardar cambios</el-button>
        <el-button type="primary" @click="regenerateImage" :loading="regenerating" icon="el-icon-picture">
          {{ detailPiece && detailPiece.imagen_final_url ? 'Regenerar imagen' : 'Generar imagen' }}
        </el-button>
        <el-button v-if="detailPiece && detailPiece.estado_pieza !== 'lista_publicar'"
                   type="success" @click="approve" icon="el-icon-check">Aprobar</el-button>
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
