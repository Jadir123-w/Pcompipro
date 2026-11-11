<template>
  <div>
    <el-row :gutter="12" class="mb-3">
      <el-col :xs="24" :sm="5">
        <el-select v-model="filters.red_social" clearable placeholder="Todas las redes" style="width:100%" @change="filterItems">
          <el-option v-for="r in redes" :key="r.value" :label="r.label" :value="r.value"></el-option>
        </el-select>
      </el-col>
      <el-col :xs="24" :sm="8">
        <el-date-picker
          v-model="filters.fechas"
          type="daterange"
          value-format="yyyy-MM-dd"
          start-placeholder="Fecha desde"
          end-placeholder="Fecha hasta"
          style="width:100%"
          @change="filterItems"
        ></el-date-picker>
      </el-col>
      <el-col :xs="24" :sm="3">
        <el-input-number v-model="filters.anio" :min="2026" :max="2100" placeholder="Año"
                         style="width:100%" @change="filterItems"></el-input-number>
      </el-col>
       <!--<el-col :xs="12" :sm="3">
        <el-button icon="el-icon-refresh" @click="reloadItems">Recargar</el-button>
      </el-col>
    -->
      <el-col :xs="12" :sm="8" style="text-align:right">
        <div class="grid-toolbar-actions">
          <el-button icon="el-icon-plus" @click="openExtendDialog">
            Ampliar grilla
          </el-button>
          <el-button type="primary" icon="el-icon-magic-stick" @click="showGenerateDialog = true">
            Generar Grilla con IA (Modo A)
          </el-button>
        </div>
      </el-col>
    </el-row>

    <el-table :data="items" stripe border v-loading="loadingItems" empty-text="Sin ítems" class="grid-items-table">
      <el-table-column prop="id" label="ID" width="75" :resizable="false"></el-table-column>
      <el-table-column label="Fecha" width="125">
        <template slot-scope="scope">
          {{ formatDate(scope.row.fecha_publicacion) }}
        </template>
      </el-table-column>
      <el-table-column prop="red_social" label="Red social" width="130">
        <template slot-scope="scope">
          <el-tag size="mini">{{ scope.row.red_social }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="tematica" label="Temática" min-width="230" show-overflow-tooltip></el-table-column>
      <el-table-column prop="linea_comercial" label="Línea" min-width="190" show-overflow-tooltip></el-table-column>
      <el-table-column prop="tipo_contenido" label="Tipo" min-width="170" show-overflow-tooltip></el-table-column>
      <el-table-column prop="objetivo" label="Objetivo" min-width="200" show-overflow-tooltip></el-table-column>
      <el-table-column prop="origen" label="Origen" width="135">
        <template slot-scope="scope">
          <el-tag size="mini" :type="scope.row.origen === 'feria' ? 'warning' : 'info'">{{ scope.row.origen }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Estado" width="165">
        <template slot-scope="scope">
          <el-tag :type="pieceStateType(scope.row.current_piece ? scope.row.current_piece.estado_pieza : 'pendiente')" size="small">
            {{ scope.row.current_piece ? scope.row.current_piece.estado_pieza : 'pendiente' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="Acciones" width="110" align="center"
                       class-name="grid-items-actions" label-class-name="grid-items-actions">
        <template slot-scope="scope">
          <template v-if="Number(scope.row.pieces_count) === 0">
            <el-button
              type="primary"
              icon="el-icon-magic-stick"
              circle
              size="mini"
              class="grid-item-action-button"
              title="Producir pieza"
              aria-label="Producir pieza"
              @click="producePiece(scope.row)"
              :loading="producingId === scope.row.id"
              :disabled="producingId !== null"
            ></el-button>
            <el-button
              circle
              size="mini"
              class="grid-item-action-button"
              icon="el-icon-edit"
              title="Editar ítem"
              aria-label="Editar ítem"
              @click="openItemEditor(scope.row)"
            ></el-button>
          </template>
          <el-button
            v-else
            circle
            size="mini"
            class="grid-item-action-button"
            icon="el-icon-view"
            title="Ver pieza"
            aria-label="Ver pieza"
            @click="openItemViewer(scope.row)"
          ></el-button>
        </template>
      </el-table-column>
    </el-table>
    <el-pagination
      class="grid-items-pagination"
      background
      layout="total, prev, pager, next"
      :current-page="itemPagination.current_page"
      :page-size="itemPagination.per_page"
      :total="itemPagination.total"
      @current-change="loadItems"
    ></el-pagination>

    <!-- Administrar grillas queda temporalmente deshabilitado para reducir carga en esta vista. 
    <el-collapse v-model="gridManagerOpen" class="grid-manager">
      <el-collapse-item name="grids">
        <template slot="title">Administrar grillas ({{ grids.length }})</template>
        <el-table :data="grids" stripe border v-loading="loadingGrids" empty-text="Sin grillas">
          <el-table-column prop="id" label="ID" width="70"></el-table-column>
          <el-table-column prop="titulo" label="Título" min-width="180"></el-table-column>
          <el-table-column prop="anio" label="Año" width="85"></el-table-column>
          <el-table-column prop="red_social" label="Red" width="110"></el-table-column>
          <el-table-column label="Estado" width="125">
            <template slot-scope="scope">
              <el-tag :type="estadoType(scope.row.estado)" size="small">{{ scope.row.estado }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="items_count" label="Ítems" width="80" align="center"></el-table-column>
          <el-table-column label="Acciones" min-width="230" align="center">
            <template slot-scope="scope">
              <el-button v-if="scope.row.estado === 'borrador'" size="mini" type="warning"
                         @click="changeGridStatus(scope.row, 'validada')">Validar</el-button>
              <el-button v-if="scope.row.estado === 'validada'" size="mini" type="success"
                         @click="changeGridStatus(scope.row, 'activa')">Activar</el-button>
              <el-button size="mini" icon="el-icon-plus" @click="openExtend(scope.row)">Ampliar</el-button>
              <el-button size="mini" type="danger" icon="el-icon-delete"
                         :loading="deletingGridId === scope.row.id" :disabled="deletingGridId !== null"
                         @click="deleteGrid(scope.row)">Borrar</el-button>
            </template>
          </el-table-column>
        </el-table>
      </el-collapse-item>
    </el-collapse>
    -->
    <!-- Dialog: Generar grilla -->
    <el-dialog
      :visible.sync="showGenerateDialog"
      top="4vh"
      width="min(900px, 94vw)"
      custom-class="generate-grid-dialog"
    >
      <div slot="title" class="generate-dialog-heading">
        <span class="generate-heading-copy">
          <strong>Generar grilla con IA</strong>
          <small>Configura el periodo, las fechas y las redes para planificar tu contenido</small>
        </span>
      </div>

      <div class="generate-dialog-content">
        <div class="generate-intro">
          <span class="generate-intro-icon"><i class="el-icon-date"></i></span>
          <span>
            <strong>Planificación editorial</strong>
            <small>La IA organizará propuestas de publicaciones según el rango seleccionado.</small>
          </span>
        </div>

        <el-form class="generate-grid-form" :model="genForm" label-position="top" size="small">
          <section class="generate-form-section">
            <div class="generate-section-heading">
              <span class="generate-section-number">01</span>
              <span>
                <strong>Periodo de planificación</strong>
                <small>Define cómo identificar y organizar la grilla</small>
              </span>
            </div>
            <el-row :gutter="14">
              <el-col :xs="24" :sm="8">
                <el-form-item label="Año">
                  <el-input-number v-model="genForm.anio" :min="2026" :max="2100" controls-position="right"></el-input-number>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="Tipo de periodo">
                  <el-select v-model="genForm.tipo_periodo" style="width:100%">
                    <el-option label="Anual" value="anual"></el-option>
                    <el-option label="Trimestral" value="trimestral"></el-option>
                    <el-option label="Semestral" value="semestral"></el-option>
                    <el-option label="Mensual" value="mensual"></el-option>
                    <el-option label="Campaña" value="campania"></el-option>
                  </el-select>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="8">
                <el-form-item label="Identificador">
                  <el-input v-model="genForm.periodo" placeholder="Ej. 2026-Q1"></el-input>
                </el-form-item>
              </el-col>
            </el-row>
            <p class="generate-field-hint"><i class="el-icon-info"></i> Si lo dejas vacío, se utilizará el año como identificador.</p>
          </section>

          <section class="generate-form-section">
            <div class="generate-section-heading">
              <span class="generate-section-number">02</span>
              <span>
                <strong>Rango de fechas</strong>
                <small>Selecciona las fechas que cubrirá la grilla</small>
              </span>
            </div>
            <el-row :gutter="14">
              <el-col :xs="24" :sm="12">
                <el-form-item label="Fecha desde">
                  <el-date-picker v-model="genForm.fecha_desde" type="date" value-format="yyyy-MM-dd"
                                  placeholder="Selecciona fecha inicial" style="width:100%"></el-date-picker>
                </el-form-item>
              </el-col>
              <el-col :xs="24" :sm="12">
                <el-form-item label="Fecha hasta">
                  <el-date-picker v-model="genForm.fecha_hasta" type="date" value-format="yyyy-MM-dd"
                                  placeholder="Selecciona fecha final" style="width:100%"></el-date-picker>
                </el-form-item>
              </el-col>
            </el-row>
          </section>

          <section class="generate-form-section generate-network-section">
            <div class="generate-section-heading">
              <span class="generate-section-number">03</span>
              <span>
                <strong>Redes sociales</strong>
                <small>Elige una o varias redes para generar contenido</small>
              </span>
            </div>
            <el-form-item label="Canales incluidos">
              <el-checkbox-group v-model="genForm.redes" class="generate-network-options">
                <el-checkbox label="facebook" border><i class="el-icon-share"></i> Facebook</el-checkbox>
                <el-checkbox label="instagram" border><i class="el-icon-camera"></i> Instagram</el-checkbox>
                <el-checkbox label="linkedin" border><i class="el-icon-user"></i> LinkedIn</el-checkbox>
                <el-checkbox label="tiktok" border><i class="el-icon-video-camera"></i> TikTok</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
          </section>

          <section class="generate-form-section generate-title-section">
            <div class="generate-section-heading">
              <span class="generate-section-number">04</span>
              <span>
                <strong>Nombre de la grilla</strong>
                <small>Agrega un título para reconocerla fácilmente</small>
              </span>
            </div>
            <el-form-item label="Título (opcional)">
              <el-input v-model="genForm.titulo" placeholder="Ej. Campaña de verano 2026"></el-input>
            </el-form-item>
          </section>
        </el-form>
      </div>

      <div slot="footer" class="generate-dialog-footer">
        <el-button class="generate-cancel-button" @click="showGenerateDialog = false" :disabled="generating">
          Cancelar
        </el-button>
        <el-button class="generate-submit-button" type="primary" @click="generate" :loading="generating" icon="el-icon-magic-stick">
          Generar grilla
        </el-button>
      </div>
    </el-dialog>

    <!-- Dialog: editar ítem no producido o consultar ítem producido -->
    <el-dialog
      :visible.sync="showItemEditor"
      width="min(700px, 94vw)"
      top="6vh"
      custom-class="grid-item-dialog"
    >
      <div slot="title" class="grid-item-dialog-title">
        {{ itemDialogReadOnly ? 'Ver ítem #' : 'Editar ítem #' }}{{ editingItem ? editingItem.id : '' }}
      </div>
      <div v-if="editingItem" class="grid-item-dialog-content" v-loading="savingItem">
        <div class="grid-item-summary">
          <div class="grid-item-summary-cell">
            <small>ID ITEM</small>
            <strong>{{ editingItem.id }}</strong>
          </div>
          <div class="grid-item-summary-cell">
            <small>FECHA</small>
            <strong>{{ formatDate(editingItem.fecha_publicacion) }}</strong>
          </div>
          <div class="grid-item-summary-cell">
            <small>ORIGEN</small>
            <el-tag size="small" :type="editingItem.origen === 'feria' ? 'warning' : 'info'">
              {{ editingItem.origen }}
            </el-tag>
          </div>
        </div>

        <el-form :model="itemEditForm" label-position="top" size="small" class="grid-item-edit-form">
          <el-form-item label="Temática del contenido">
            <el-input v-model="itemEditForm.tematica" :disabled="itemDialogReadOnly"></el-input>
          </el-form-item>
          <el-row :gutter="16">
            <el-col :xs="24" :sm="12">
              <el-form-item label="Red Social">
                <el-select v-model="itemEditForm.red_social" style="width:100%" :disabled="itemDialogReadOnly">
                  <el-option v-for="red in redes" v-if="red.value" :key="red.value"
                             :label="red.label" :value="red.value"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="Tipo de Contenido">
                <el-select v-model="itemEditForm.tipo_contenido" filterable allow-create default-first-option
                           style="width:100%" :disabled="itemDialogReadOnly">
                  <el-option v-for="tipo in tiposContenido" :key="tipo" :label="tipo" :value="tipo"></el-option>
                  <el-option v-if="itemEditForm.tipo_contenido && tiposContenido.indexOf(itemEditForm.tipo_contenido) === -1"
                             :key="itemEditForm.tipo_contenido" :label="itemEditForm.tipo_contenido"
                             :value="itemEditForm.tipo_contenido"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="Línea Comercial">
                <el-select v-model="itemEditForm.linea_comercial" filterable allow-create default-first-option
                           style="width:100%" :disabled="itemDialogReadOnly">
                  <el-option v-for="linea in lineasComerciales" :key="linea" :label="linea" :value="linea"></el-option>
                  <el-option v-if="itemEditForm.linea_comercial && lineasComerciales.indexOf(itemEditForm.linea_comercial) === -1"
                             :key="itemEditForm.linea_comercial" :label="itemEditForm.linea_comercial"
                             :value="itemEditForm.linea_comercial"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="Objetivo">
                <el-select v-model="itemEditForm.objetivo" filterable allow-create default-first-option
                           style="width:100%" :disabled="itemDialogReadOnly">
                  <el-option v-for="objetivo in objetivos" :key="objetivo.value"
                             :label="objetivo.label" :value="objetivo.value"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>
          <el-form-item label="Estado">
            <el-select v-model="itemEditForm.estado_pieza" style="width:100%" disabled>
              <el-option v-for="estado in estadosPieza" :key="estado" :label="estadoLabel(estado)" :value="estado"></el-option>
            </el-select>
          </el-form-item>
        </el-form>
      </div>
      <div slot="footer">
        <el-button @click="showItemEditor = false">{{ itemDialogReadOnly ? 'Cerrar' : 'Cancelar' }}</el-button>
        <el-button v-if="!itemDialogReadOnly" type="primary" @click="saveItem" :loading="savingItem">Guardar cambios</el-button>
      </div>
    </el-dialog>

    <!-- Dialog: Ampliar grilla desde la toolbar (selector de grilla + rango) -->
    <el-dialog
      :visible.sync="showExtendDialogToolbar"
      width="min(560px, 94vw)"
      top="8vh"
      custom-class="extend-toolbar-dialog"
    >
      <div slot="title" class="extend-toolbar-dialog-heading">
        <span class="extend-toolbar-dialog-heading-copy">
          <strong>Ampliar grilla con IA</strong>
          <small>Agrega los tramos siguientes evitando duplicados de fechas y redes</small>
        </span>
      </div>

      <div class="extend-toolbar-dialog-content" v-loading="loadingGrids">
        <el-form :model="extendToolbarForm" label-position="top" size="small">
          <el-form-item label="Grilla a ampliar">
            <el-select
              v-model="extendToolbarForm.grid_id"
              filterable
              clearable
              placeholder="Selecciona una grilla"
              style="width:100%"
            >
              <el-option
                v-for="grid in expandableGrids"
                :key="grid.id"
                :value="grid.id"
                :label="'#' + grid.id + ' — ' + grid.titulo + ' (' + grid.red_social + ' · ' + grid.anio + ')'"
              >
                <span class="extend-toolbar-option">
                  <span class="extend-toolbar-option-title">
                    #{{ grid.id }} — {{ grid.titulo }}
                    <small>{{ grid.red_social }} · {{ grid.tipo_periodo }} · {{ grid.anio }}</small>
                  </span>
                  <el-tag size="mini" :type="estadoType(grid.estado)">{{ grid.estado }}</el-tag>
                </span>
              </el-option>
            </el-select>
          </el-form-item>

          <div v-if="extendToolbarTarget" class="extend-toolbar-target">
            <div class="extend-toolbar-target-cell">
              <small>RED</small>
              <el-tag size="small">{{ extendToolbarTarget.red_social }}</el-tag>
            </div>
            <div class="extend-toolbar-target-cell">
              <small>TIPO</small>
              <strong>{{ extendToolbarTarget.tipo_periodo }}</strong>
            </div>
            <div class="extend-toolbar-target-cell">
              <small>ÍTEMS</small>
              <strong>{{ extendToolbarTarget.items_count != null ? extendToolbarTarget.items_count : '—' }}</strong>
            </div>
          </div>

          <el-row :gutter="12">
            <el-col :xs="24" :sm="12">
              <el-form-item label="Fecha desde">
                <el-date-picker
                  v-model="extendToolbarForm.fecha_desde"
                  type="date"
                  value-format="yyyy-MM-dd"
                  placeholder="Selecciona fecha inicial"
                  style="width:100%"
                ></el-date-picker>
              </el-form-item>
            </el-col>
            <el-col :xs="24" :sm="12">
              <el-form-item label="Fecha hasta">
                <el-date-picker
                  v-model="extendToolbarForm.fecha_hasta"
                  type="date"
                  value-format="yyyy-MM-dd"
                  placeholder="Selecciona fecha final"
                  style="width:100%"
                ></el-date-picker>
              </el-form-item>
            </el-col>
          </el-row>
          <p class="extend-toolbar-hint">
            Se generarán ítems nuevos en la misma grilla; las fechas y redes existentes no se repiten.
          </p>
        </el-form>
      </div>

      <div slot="footer" class="extend-toolbar-dialog-footer">
        <el-button @click="showExtendDialogToolbar = false" :disabled="extendingToolbar">Cancelar</el-button>
        <el-button type="primary" icon="el-icon-plus" @click="extendFromToolbar" :loading="extendingToolbar">
          Ampliar
        </el-button>
      </div>
    </el-dialog>

    <!-- Dialog: Ampliar grilla -->
    <el-dialog title="Ampliar Grilla (Modo C)" :visible.sync="showExtendDialog" width="450px">
      <el-form :model="extendForm" label-position="top" size="small">
        <p v-if="extendTarget"><strong>Grilla:</strong> {{ extendTarget.titulo }}</p>
        <el-row :gutter="10">
          <el-col :span="12">
            <el-form-item label="Fecha desde">
              <el-date-picker v-model="extendForm.fecha_desde" type="date" value-format="yyyy-MM-dd" style="width:100%"></el-date-picker>
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="Fecha hasta">
              <el-date-picker v-model="extendForm.fecha_hasta" type="date" value-format="yyyy-MM-dd" style="width:100%"></el-date-picker>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <div slot="footer">
        <el-button @click="showExtendDialog = false">Cancelar</el-button>
        <el-button type="primary" @click="extend" :loading="extending">Ampliar</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
export default {
  data() {
    return {
      grids: [],
      loadingGrids: false,
      loadingItems: false,
      gridLoadRequestId: 0,
      itemLoadRequestId: 0,
      items: [],
      itemPagination: { current_page: 1, per_page: 20, total: 0 },
      deletingGridId: null,
      filters: { red_social: '', anio: null, fechas: [] },
      gridManagerOpen: [],
      redes: [
        { value: '', label: 'Todas' },
        { value: 'facebook', label: 'Facebook' },
        { value: 'instagram', label: 'Instagram' },
        { value: 'linkedin', label: 'LinkedIn' },
        { value: 'tiktok', label: 'TikTok' },
      ],
      estadosPieza: ['pendiente', 'por_revisar', 'aprobado', 'publicado', 'observada', 'programado'],
      tiposContenido: ['Post', 'Reel', 'Story', 'Carrusel', 'Video', 'Imagen', 'Producto', 'Recordatorio', 'Contenido de valor', 'Promoción', 'Expectativa', 'Feria', 'brief_visual_para_video_humano'],
      lineasComerciales: ['Línea Industrial', 'Línea Tecnología', 'Línea Corporativa'],
      objetivos: [
        { value: 'visibilidad', label: 'Visibilidad' },
        { value: 'posicionamiento', label: 'Posicionamiento' },
        { value: 'recordacion', label: 'Recordación' },
        { value: 'campania', label: 'Campaña' },
        { value: 'temporada', label: 'Temporada' },
        { value: 'evento', label: 'Evento' },
        { value: 'conversion', label: 'Conversión' },
        { value: 'presencia_institucional', label: 'Presencia institucional' },
      ],
      // Generar
      showGenerateDialog: false,
      generating: false,
      genForm: {
        anio: new Date().getFullYear(),
        tipo_periodo: 'mensual',
        periodo: '',
        fecha_desde: '',
        fecha_hasta: '',
        redes: ['facebook'],
        titulo: '',
      },
      // Editar ítem
      showItemEditor: false,
      savingItem: false,
      editingItem: null,
      itemDialogReadOnly: false,
      itemEditForm: { tematica: '', red_social: '', linea_comercial: '', tipo_contenido: '', objetivo: '', estado_pieza: 'pendiente' },
      producingId: null,
      // Extend
      showExtendDialog: false,
      extending: false,
      extendTarget: null,
      extendForm: { fecha_desde: '', fecha_hasta: '' },
      // Extender desde la toolbar (selector de grilla + rango)
      showExtendDialogToolbar: false,
      extendingToolbar: false,
      extendToolbarForm: { grid_id: null, fecha_desde: '', fecha_hasta: '' },
    }
  },
  computed: {
    expandableGrids() {
      return this.grids.filter(function (grid) {
        return ['borrador', 'en_revision', 'activa','validada'].indexOf(grid.estado) !== -1
      })
    },
    extendToolbarTarget() {
      var self = this
      if (!this.extendToolbarForm.grid_id) return null
      return this.grids.find(function (grid) {
        return String(grid.id) === String(self.extendToolbarForm.grid_id)
      }) || null
    },
  },
  created() {
    this.loadItems(1)
  },
  methods: {
    async loadGrids(preserveOnEmpty) {
      var requestId = ++this.gridLoadRequestId
      this.loadingGrids = true
      try {
        var params = { page: 1 }
        if (this.filters.red_social) params.red_social = this.filters.red_social
        if (this.filters.anio) params.anio = this.filters.anio
        var r = await this.$http.get('/marketing/mkt03/grids', { params: params })
        if (requestId !== this.gridLoadRequestId) return

        var grids = r.data.grids
        if (grids && !Array.isArray(grids)) grids = grids.data
        if (!Array.isArray(grids)) throw new Error('Formato de respuesta de grillas no válido')
        if (preserveOnEmpty && grids.length === 0 && this.grids.length > 0) {
          this.$message.warning('El servidor no devolvió grillas; se conserva la lista actual')
          return
        }
        this.grids = grids
      } catch (e) {
        if (requestId === this.gridLoadRequestId) {
          this.$message.error('Error cargando grillas')
        }
      } finally {
        if (requestId === this.gridLoadRequestId) {
          this.loadingGrids = false
        }
      }
    },

    async loadItems(page, preserveOnEmpty) {
      if (typeof page === 'number') this.itemPagination.current_page = page
      var requestId = ++this.itemLoadRequestId
      this.loadingItems = true
      try {
        var params = {
          page: this.itemPagination.current_page,
          per_page: this.itemPagination.per_page,
        }
        if (this.filters.red_social) params.red_social = this.filters.red_social
        if (this.filters.anio) params.anio = this.filters.anio
        if (this.filters.fechas && this.filters.fechas[0]) params.fecha_desde = this.filters.fechas[0]
        if (this.filters.fechas && this.filters.fechas[1]) params.fecha_hasta = this.filters.fechas[1]

        var r = await this.$http.get('/marketing/mkt03/grid-items', { params: params })
        if (requestId !== this.itemLoadRequestId) return

        var pagination = r.data.items
        if (!pagination || !Array.isArray(pagination.data)) {
          throw new Error('Formato de respuesta de ítems no válido')
        }
        if (preserveOnEmpty && pagination.data.length === 0 && this.items.length > 0) {
          this.$message.warning('El servidor no devolvió ítems; se conserva la lista actual')
          return
        }
        this.items = pagination.data
        this.itemPagination = {
          current_page: pagination.current_page,
          per_page: pagination.per_page,
          total: pagination.total,
        }
      } catch (e) {
        if (requestId === this.itemLoadRequestId) {
          this.$message.error('Error cargando ítems')
        }
      } finally {
        if (requestId === this.itemLoadRequestId) {
          this.loadingItems = false
        }
      }
    },

    filterItems() {
      this.itemPagination.current_page = 1
      this.loadItems(1)
    },

    reloadItems() {
      return this.loadItems(this.itemPagination.current_page, true)
    },

    reloadAll() {
      var self = this
      return Promise.all([this.reloadItems(), this.loadGrids(true)]).catch(function () {
        // reloadItems y loadGrids ya reportan errores propios
      })
    },

    async deleteGrid(grid) {
      try {
        await this.$confirm(
          '¿Eliminar la grilla "' + grid.titulo + '"? Esta acción no se puede deshacer desde esta pantalla.',
          'Confirmar eliminación',
          { type: 'warning' }
        )
      } catch (e) {
        if (e !== 'cancel' && e !== 'close') {
          this.$message.error('No se pudo confirmar la eliminación')
        }
        return
      }

      this.deletingGridId = grid.id
      try {
        await this.$http.delete('/marketing/mkt03/grids/' + grid.id)
        this.grids = this.grids.filter(function (item) {
          return String(item.id) !== String(grid.id)
        })
        await this.loadItems(1)
        this.$message.success('Grilla eliminada')
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error eliminando grilla'
        this.$message.error(msg)
      } finally {
        this.deletingGridId = null
      }
    },

    async generate() {
      if (!this.genForm.fecha_desde || !this.genForm.fecha_hasta || !this.genForm.redes.length) {
        this.$message.warning('Completa fechas y al menos una red')
        return
      }
      const hoy = new Date().toISOString().split('T')[0]
      if (this.genForm.fecha_desde < hoy) {
        this.$message.warning('La fecha desde no puede ser anterior a hoy')
        return
      }
      this.generating = true
      try {
        var payload = Object.assign({}, this.genForm)
        if (!payload.periodo) payload.periodo = String(payload.anio)
        var r = await this.$http.post('/marketing/mkt03/grids/generate', payload)
        this.$message.success('Grilla(s) generada(s): ' + (r.data.items_count || 0) + ' items totales')
        this.showGenerateDialog = false
        await this.reloadAll()
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error generando'
        this.$message.error(msg)
      } finally {
        this.generating = false
      }
    },

    openExtend(grid) {
      this.extendTarget = grid
      this.extendForm = { fecha_desde: '', fecha_hasta: '' }
      this.showExtendDialog = true
    },

    async openExtendDialog() {
      this.extendToolbarForm = { grid_id: null, fecha_desde: '', fecha_hasta: '' }
      this.showExtendDialogToolbar = true
      await this.loadExpandableGrids()
    },

    async loadExpandableGrids() {
      this.loadingGrids = true
      try {
        var r = await this.$http.get('/marketing/mkt03/grids', { params: { page: 1, per_page: 100 } })
        var grids = r.data.grids
        if (grids && !Array.isArray(grids)) grids = grids.data
        this.grids = Array.isArray(grids) ? grids : []
      } catch (e) {
        this.$message.error('Error cargando grillas')
      } finally {
        this.loadingGrids = false
      }
    },

    async extendFromToolbar() {
      var form = this.extendToolbarForm
      if (!form.grid_id) {
        this.$message.warning('Selecciona la grilla a ampliar')
        return
      }
      if (!form.fecha_desde || !form.fecha_hasta) {
        this.$message.warning('Completa ambas fechas')
        return
      }
      if (form.fecha_desde > form.fecha_hasta) {
        this.$message.warning('La fecha desde no puede ser posterior a la fecha hasta')
        return
      }
      this.extendingToolbar = true
      try {
        var r = await this.$http.post(
          '/marketing/mkt03/grids/' + form.grid_id + '/extend',
          { fecha_desde: form.fecha_desde, fecha_hasta: form.fecha_hasta }
        )
        this.$message.success('Grilla ampliada: ' + (r.data.items_added || 0) + ' items agregados')
        this.showExtendDialogToolbar = false
        await this.reloadAll()
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error ampliando'
        this.$message.error(msg)
      } finally {
        this.extendingToolbar = false
      }
    },

    async extend() {
      if (!this.extendForm.fecha_desde || !this.extendForm.fecha_hasta) {
        this.$message.warning('Completa ambas fechas')
        return
      }
      this.extending = true
      try {
        var r = await this.$http.post(
          '/marketing/mkt03/grids/' + this.extendTarget.id + '/extend',
          this.extendForm
        )
        this.$message.success('Grilla ampliada: ' + r.data.items_added + ' items agregados')
        this.showExtendDialog = false
        await this.reloadAll()
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error ampliando'
        this.$message.error(msg)
      } finally {
        this.extending = false
      }
    },

    async changeGridStatus(grid, estado) {
      try {
        await this.$http.put('/marketing/mkt03/grids/' + grid.id + '/status', { estado: estado })
        this.$message.success('Estado cambiado a: ' + estado)
        await this.loadGrids()
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error cambiando estado'
        this.$message.error(msg)
      }
    },

    openItemEditor(item) {
      if (Number(item.pieces_count) > 0) return
      this.openItemDialog(item, false)
    },

    openItemViewer(item) {
      this.openItemDialog(item, true)
    },

    openItemDialog(item, readOnly) {
      this.editingItem = item
      this.itemDialogReadOnly = readOnly
      this.itemEditForm = {
        tematica: item.tematica || '',
        red_social: item.red_social || '',
        linea_comercial: item.linea_comercial || '',
        tipo_contenido: item.tipo_contenido || '',
        objetivo: item.objetivo || '',
        estado_pieza: item.current_piece ? item.current_piece.estado_pieza : 'pendiente',
      }
      this.showItemEditor = true
    },

    async saveItem() {
      if (!this.editingItem || this.itemDialogReadOnly || Number(this.editingItem.pieces_count) > 0) return

      this.savingItem = true
      try {
        var payload = {
          tematica: this.itemEditForm.tematica,
          red_social: this.itemEditForm.red_social,
          linea_comercial: this.itemEditForm.linea_comercial,
          tipo_contenido: this.itemEditForm.tipo_contenido,
          objetivo: this.itemEditForm.objetivo,
        }
        if (this.editingItem.current_piece) {
          payload.estado_pieza = this.itemEditForm.estado_pieza
        }

        var r = await this.$http.put(
          '/marketing/mkt03/grids/' + this.editingItem.grid_id + '/items/' + this.editingItem.id,
          payload
        )
        var index = this.items.findIndex(function (item) {
          return String(item.id) === String(this.editingItem.id)
        }, this)
        if (index !== -1 && r.data.item) {
          var current = this.items[index]
          this.$set(this.items, index, Object.assign({}, current, r.data.item, {
            pieces_count: current.pieces_count,
          }))
        }
        this.showItemEditor = false
        this.$message.success('Ítem actualizado')
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error actualizando ítem'
        this.$message.error(msg)
      } finally {
        this.savingItem = false
      }
    },

    async producePiece(item) {
      this.producingId = item.id
      try {
        var r = await this.$http.post('/marketing/mkt03/items/' + item.id + '/produce', {
          generate_image: false,
        })
        this.$set(item, 'pieces_count', Math.max(Number(item.pieces_count) || 0, 1))
        this.$set(item, 'current_piece', r.data.piece)
        this.$message.success('Pieza producida (ID: ' + r.data.piece.id + ')')
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error produciendo pieza'
        this.$message.error(msg)
      } finally {
        this.producingId = null
      }
    },

    estadoType(estado) {
      var map = {
        'borrador': 'info', 'por_revisar': 'warning', 'aprobado': 'primary',
        'activa': 'success', 'cerrada': 'info',
      }
      return map[estado] || 'info'
    },

    pieceStateType(estado) {
      var map = {
        pendiente: 'info',
        por_revisar: 'warning',
        aprobado: 'primary',
        publicada: 'success',
        observada: 'warning',
        programado: 'info',
      }
      return map[estado] || 'info'
    },

    formatDate(fecha) {
      if (!fecha) return ''
      var parts = String(fecha).substring(0, 10).split('-')
      return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : ''
    },

    estadoLabel(estado) {
      var labels = {
        pendiente: 'Programado',
        por_revisar: 'En producción',
        aprobado: 'Lista para publicar',
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
.grid-item-dialog >>> .el-dialog {
  border-radius: 12px;
}

.grid-item-dialog >>> .el-dialog__header {
  padding: 18px 24px;
  border-bottom: 1px solid #e8ebf0;
}

.grid-item-dialog-title {
  color: #23395d;
  font-size: 16px;
  font-weight: 600;
}

.grid-item-dialog >>> .el-dialog__body {
  padding: 22px 24px 8px;
}

.grid-item-dialog-content {
  min-height: 250px;
}

.grid-item-summary {
  display: grid;
  grid-template-columns: 1fr 1.7fr 1fr;
  align-items: center;
  padding: 15px 20px;
  margin-bottom: 22px;
  background: #f7f8fa;
  border: 1px solid #e6e9ef;
  border-radius: 9px;
}

.grid-item-summary-cell {
  display: flex;
  min-width: 0;
  flex-direction: column;
  align-items: flex-start;
  gap: 7px;
}

.grid-item-summary-cell + .grid-item-summary-cell {
  padding-left: 20px;
  border-left: 1px solid #dfe3e9;
}

.grid-item-summary-cell small {
  color: #667085;
  font-size: 10px;
  letter-spacing: .04em;
}

.grid-item-summary-cell strong {
  color: #203047;
  font-size: 14px;
}

.grid-item-summary-cell:last-child {
  align-items: flex-end;
}

.grid-item-edit-form >>> .el-form-item {
  margin-bottom: 16px;
}

.grid-item-edit-form >>> .el-form-item__label {
  padding-bottom: 5px;
  color: #475569;
  font-size: 12px;
  line-height: 18px;
}

.grid-item-edit-form >>> .el-input__inner,
.grid-item-edit-form >>> .el-select .el-input__inner {
  height: 40px;
  background: #f8f9fb;
  border-color: #e0e4eb;
  border-radius: 7px;
}

.grid-item-dialog >>> .el-dialog__footer {
  padding: 0 24px 20px;
}

.grid-items-pagination {
  margin: 16px 0 24px;
  text-align: right;
}

.grid-items-table >>> .el-table__header-wrapper .el-table__cell .cell,
.grid-items-table >>> .el-table__body-wrapper .el-table__cell .cell {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  word-break: normal;
}

.grid-items-table >>> .grid-items-actions {
  position: sticky;
  right: 0;
  z-index: 2;
  background: #fff;
  box-shadow: -5px 0 12px rgba(31, 58, 91, .08);
}

.grid-items-table >>> th.grid-items-actions {
  z-index: 3;
}

.grid-items-table >>> .el-table__row--striped td.grid-items-actions {
  background: #fafafa;
}

.grid-items-table >>> .grid-items-actions .cell {
  white-space: nowrap;
}

.grid-item-action-button + .grid-item-action-button {
  margin-left: 4px;
}

.grid-manager {
  margin-top: 20px;
}

.grid-toolbar-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  justify-content: flex-end;
}

@media (max-width: 640px) {
  .grid-toolbar-actions {
    justify-content: flex-start;
  }
}

.extend-toolbar-dialog >>> .el-dialog__header {
  padding: 18px 24px;
  border-bottom: 1px solid #edf0f5;
}

.extend-toolbar-dialog >>> .el-dialog__body {
  padding: 20px 24px 6px;
}

.extend-toolbar-dialog-heading {
  display: flex;
  align-items: center;
}

.extend-toolbar-dialog-heading-copy strong {
  display: block;
  color: #182338;
  font-size: 17px;
  line-height: 1.3;
}

.extend-toolbar-dialog-heading-copy small {
  display: block;
  margin-top: 4px;
  color: #7b8798;
  font-size: 12px;
  font-weight: 400;
}

.extend-toolbar-option {
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  width: 100%;
}

.extend-toolbar-option-title small {
  margin-left: 6px;
  color: #8792a2;
}

.extend-toolbar-target {
  display: flex;
  gap: 16px;
  margin: 12px 0 16px;
  padding: 12px 16px;
  background: #f7f8fa;
  border: 1px solid #e6e9ef;
  border-radius: 9px;
}

.extend-toolbar-target-cell small {
  display: block;
  margin-bottom: 4px;
  color: #667085;
  font-size: 10px;
  letter-spacing: .04em;
}

.extend-toolbar-target-cell strong {
  color: #203047;
  font-size: 14px;
}

.extend-toolbar-hint {
  margin: 2px 0 12px;
  color: #8792a2;
  font-size: 11px;
  line-height: 1.5;
}

.extend-toolbar-dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  padding: 0 24px 18px;
}

.status-edit-hint {
  display: block;
  margin-top: 5px;
  color: #909399;
  line-height: 1.4;
}

.generate-grid-dialog >>> .el-dialog {
  display: flex;
  max-height: 92vh;
  flex-direction: column;
  overflow: hidden;
  border-radius: 16px;
  box-shadow: 0 24px 70px rgba(15, 23, 42, .24);
}

.generate-grid-dialog >>> .el-dialog__header {
  padding: 21px 26px;
  border-bottom: 1px solid #edf0f5;
}

.generate-grid-dialog >>> .el-dialog__headerbtn {
  top: 24px;
  right: 24px;
  font-size: 18px;
}

.generate-grid-dialog >>> .el-dialog__body {
  display: flex;
  min-height: 0;
  flex: 1;
  padding: 0;
  overflow: hidden;
}

.generate-grid-dialog >>> .el-dialog__footer {
  padding: 0;
  border-top: 1px solid #edf0f5;
}

.generate-dialog-heading {
  display: flex;
  align-items: center;
  gap: 13px;
}

.generate-intro-icon,
.generate-section-number {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
}

.generate-heading-copy {
  padding-right: 28px;
}

.generate-heading-copy strong,
.generate-heading-copy small,
.generate-intro strong,
.generate-intro small,
.generate-section-heading strong,
.generate-section-heading small {
  display: block;
}

.generate-heading-copy strong {
  color: #182338;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.3;
}

.generate-heading-copy small {
  margin-top: 4px;
  color: #7b8798;
  font-size: 12px;
  font-weight: 400;
}

.generate-dialog-content {
  width: 100%;
  overflow-y: auto;
  padding: 22px 26px;
  background: #f8fafc;
}

.generate-intro {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  margin-bottom: 18px;
  background: linear-gradient(110deg, #eef2ff, #eff6ff);
  border: 1px solid #e0e7ff;
  border-radius: 11px;
}

.generate-intro-icon {
  width: 36px;
  height: 36px;
  color: #4f46e5;
  background: rgba(255, 255, 255, .8);
  border-radius: 10px;
  font-size: 17px;
}

.generate-intro strong {
  color: #28375a;
  font-size: 13px;
  font-weight: 650;
}

.generate-intro small {
  margin-top: 3px;
  color: #64748b;
  font-size: 12px;
  line-height: 1.45;
}

.generate-grid-form {
  display: block;
}

.generate-form-section {
  padding: 17px 18px 4px;
  margin-bottom: 13px;
  background: #fff;
  border: 1px solid #e7ebf2;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(15, 23, 42, .025);
}

.generate-section-heading {
  display: flex;
  align-items: center;
  gap: 11px;
  margin-bottom: 16px;
}

.generate-section-number {
  width: 32px;
  height: 32px;
  color: #4f46e5;
  background: #eef2ff;
  border-radius: 9px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: .03em;
}

.generate-section-heading strong {
  color: #263449;
  font-size: 13px;
  font-weight: 650;
}

.generate-section-heading small {
  margin-top: 3px;
  color: #8792a2;
  font-size: 11px;
}

.generate-grid-form >>> .el-form-item {
  margin-bottom: 13px;
}

.generate-grid-form >>> .el-form-item__label {
  display: block;
  width: 100%;
  max-width: 100%;
  padding-bottom: 5px;
  color: #475569;
  font-size: 12px;
  font-weight: 600;
  line-height: 19px;
  white-space: normal;
  word-break: normal;
}

.generate-grid-form >>> .el-input-number,
.generate-grid-form >>> .el-select,
.generate-grid-form >>> .el-date-editor {
  width: 100%;
}

.generate-grid-form >>> .el-input__inner,
.generate-grid-form >>> .el-textarea__inner {
  color: #263449;
  background: #fbfcfe;
  border-color: #dce3ed;
  border-radius: 8px;
  transition: border-color .2s ease, box-shadow .2s ease, background .2s ease;
}

.generate-grid-form >>> .el-input__inner:hover,
.generate-grid-form >>> .el-textarea__inner:hover {
  border-color: #aab9ce;
}

.generate-grid-form >>> .el-input__inner:focus,
.generate-grid-form >>> .el-textarea__inner:focus {
  background: #fff;
  border-color: #5283e8;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
}

.generate-field-hint {
  margin: -5px 0 11px;
  color: #8792a2;
  font-size: 11px;
}

.generate-field-hint i {
  margin-right: 3px;
  color: #6885c4;
}

.generate-network-section {
  padding-bottom: 3px;
}

.generate-network-options {
  display: flex;
  flex-wrap: wrap;
  gap: 9px;
}

.generate-network-options >>> .el-checkbox.is-bordered {
  display: inline-flex;
  height: 38px;
  align-items: center;
  margin: 0;
  padding: 0 12px;
  background: #fbfcfe;
  border-color: #dce3ed;
  border-radius: 8px;
  transition: border-color .2s ease, background .2s ease, box-shadow .2s ease;
}

.generate-network-options >>> .el-checkbox.is-bordered:hover {
  border-color: #aab9ce;
}

.generate-network-options >>> .el-checkbox.is-bordered.is-checked {
  background: #eff6ff;
  border-color: #7ca1eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, .07);
}

.generate-network-options >>> .el-checkbox__label {
  color: #475569;
  font-size: 12px;
}

.generate-network-options >>> .el-checkbox.is-checked .el-checkbox__label,
.generate-network-options >>> .el-checkbox.is-checked i {
  color: #315fb7;
}

.generate-network-options >>> i {
  margin-right: 4px;
}

.generate-title-section {
  margin-bottom: 0;
}

.generate-dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 9px;
  padding: 15px 26px;
  background: #fff;
}

.generate-dialog-footer >>> .el-button {
  min-width: 112px;
  height: 36px;
  border-radius: 8px;
  font-weight: 600;
}

.generate-submit-button {
  background: linear-gradient(135deg, #4f46e5, #2563eb);
  border-color: #3b5fda;
  box-shadow: 0 4px 10px rgba(59, 95, 218, .18);
}

.generate-submit-button:hover,
.generate-submit-button:focus {
  background: linear-gradient(135deg, #4338ca, #1d4ed8);
  border-color: #3153c8;
}

@media (max-width: 640px) {
  .grid-item-dialog >>> .el-dialog__body {
    padding: 16px 18px 4px;
  }

  .grid-item-summary {
    grid-template-columns: 1fr 1fr 1fr;
    padding: 12px;
  }

  .grid-item-summary-cell + .grid-item-summary-cell {
    padding-left: 10px;
  }

  .generate-grid-dialog >>> .el-dialog__header {
    padding: 17px 18px;
  }

  .generate-grid-dialog >>> .el-dialog__headerbtn {
    top: 19px;
    right: 16px;
  }

  .generate-dialog-content {
    padding: 16px;
  }

  .generate-form-section {
    padding: 14px 13px 3px;
  }

  .generate-network-options {
    gap: 7px;
  }

  .generate-network-options >>> .el-checkbox.is-bordered {
    padding: 0 9px;
  }

  .generate-dialog-footer {
    padding: 12px 16px;
  }

  .generate-dialog-footer >>> .el-button {
    flex: 1;
    min-width: 0;
  }
}
</style>