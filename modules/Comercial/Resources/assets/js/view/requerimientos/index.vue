<template>
  <div class="req-page">
    <!-- Encabezado -->
    <el-row type="flex" justify="space-between" align="middle" class="req-header">
      <el-col>
        <h1 class="req-title">Requerimientos</h1>
      </el-col>
      <el-col>
        <div class="req-header-actions">
          <el-button size="mini" @click="goKanban">Ver Kanban</el-button>
          <el-button type="primary" size="mini" @click="onNuevo">Nuevo Requerimiento</el-button>
        </div>
      </el-col>
    </el-row>

    <!-- KPIs -->
    <el-row :gutter="12" class="req-kpis">
      <el-col :xs="24" :sm="12" :md="6">
        <el-card shadow="never" class="req-kpi-card">
          <div class="req-kpi-label">Pendientes hoy</div>
          <div class="req-kpi-value">
            {{ kpis.pendientes_hoy != null ? kpis.pendientes_hoy : '—' }}
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card shadow="never" class="req-kpi-card">
          <div class="req-kpi-label">3+ días sin respuesta</div>
          <div class="req-kpi-value">
            {{ kpis.sin_respuesta_3dias != null ? kpis.sin_respuesta_3dias : '—' }}
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card shadow="never" class="req-kpi-card">
          <div class="req-kpi-label">Muy alto</div>
          <div class="req-kpi-value">
            {{ kpis.muy_alto != null ? kpis.muy_alto : '—' }}
          </div>
        </el-card>
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <el-card shadow="never" class="req-kpi-card">
          <div class="req-kpi-label">% aprobadas sin proyecto</div>
          <div class="req-kpi-value">
            {{ kpis.aprobadas_sin_proyecto_porcentaje != null ? kpis.aprobadas_sin_proyecto_porcentaje + '%' : '—' }}
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!--  NUEVO: Barra de herramientas con botones -->
    <div class="req-toolbar">
      <el-button 
        size="mini" 
        @click="showFilters = !showFilters"
        :icon="showFilters ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"
      >
        {{ showFilters ? 'Ocultar filtros' : 'Mostrar filtros' }}
      </el-button>
      
      <el-dropdown :hide-on-click="false" class="req-columns-dropdown">
        <el-button size="mini">
          Mostrar/Ocultar columnas
          <i class="el-icon-arrow-down el-icon--right"></i>
        </el-button>
        <el-dropdown-menu slot="dropdown">
          <el-dropdown-item v-for="(col, key) in columns" :key="key">
            <el-checkbox v-model="col.visible">
              {{ col.title }}
            </el-checkbox>
          </el-dropdown-item>
        </el-dropdown-menu>
      </el-dropdown>
    </div>

    <!-- Filtros (ahora con v-show para ocultar/mostrar) -->
    <el-card shadow="never" class="req-filters" v-show="showFilters">
      <el-row :gutter="10" type="flex" align="middle" class="req-filters-row">
        <el-col :xs="24" :sm="12" :md="6">
          <label class="req-filter-label">Etapa</label>
          <el-select
            v-model="filters.etapa"
            size="mini"
            clearable
            placeholder="Todos"
            @change="fetchRequerimientos"
            class="req-filter-input"
          >
            <el-option label="Todos" value="Todos" />
            <el-option label="Pendiente atender" value="Pendiente atender" />
            <el-option label="En cotización" value="En cotización" />
            <el-option label="Cotización enviada" value="Cotización enviada" />
            <el-option label="En Re-cotización" value="En Re-cotización" />
            <el-option label="Cotización caída" value="Cotización caída" />
            <el-option label="Cotización aprobada" value="Cotización aprobada" />
            <el-option label="No cotizado" value="No cotizado" />
            <el-option label="Convertido a proyecto" value="Convertido a proyecto" />
          </el-select>
        </el-col>

        <el-col :xs="24" :sm="12" :md="6">
          <label class="req-filter-label">Prioridad</label>
          <el-select
            v-model="filters.prioridad"
            size="mini"
            clearable
            placeholder="Todos"
            @change="fetchRequerimientos"
            class="req-filter-input"
          >
            <el-option label="Todos" value="Todos" />
            <el-option label="Muy alto" value="Muy alto" />
            <el-option label="Alto" value="Alto" />
            <el-option label="Medio" value="Medio" />
            <el-option label="Bajo" value="Bajo" />
          </el-select>
        </el-col>

        <el-col :xs="24" :sm="12" :md="6">
          <label class="req-filter-label">Origen</label>
          <el-select
            v-model="filters.origen"
            size="mini"
            clearable
            placeholder="Todos"
            @change="fetchRequerimientos"
            class="req-filter-input"
          >
            <el-option label="Todos" value="Todos" />
            <el-option label="Web" value="Web" />
            <el-option label="Email" value="Email" />
            <el-option label="WhatsApp" value="WhatsApp" />
            <el-option label="Llamada" value="Llamada" />
          </el-select>
        </el-col>

        <el-col :xs="24" :sm="12" :md="6">
          <div class="req-search">
            <el-select v-model="filters.searchBy" size="mini" class="req-search-select">
              <el-option label="Cliente" value="empresa_razon_social" />
              <el-option label="Código" value="codigo" />
              <el-option label="Contacto" value="lead_contacto" />
              <el-option label="Descripción" value="descripcion" />
            </el-select>
            <el-input
              v-model="filters.search"
              size="mini"
              placeholder="Buscar..."
              clearable
              @input="onSearchChange"
              class="req-search-input"
            />
          </div>
        </el-col>
      </el-row>
      <div class="req-count">Mostrando {{ filteredList.length }} registros</div>
    </el-card>

    <!-- Tabla -->
    <el-card shadow="never" class="req-table-card">
      <el-table
        :data="filteredList"
        v-loading="loadingList"
        border
        size="mini"
        style="width: 100%"
        @row-dblclick="openDetailFromRow"
      >
        <!-- COLUMNAS SIEMPRE VISIBLES -->
        <el-table-column label="ID" prop="id" width="60" />
        <el-table-column label="N. PROYECTO" prop="titulo" width="160" show-overflow-tooltip />
        
        <!-- Descripción -->
        <el-table-column
          v-if="columns.descripcion.visible"
          label="DESCRIPCIÓN"
          width="160"
          show-overflow-tooltip
        >
          <template slot-scope="scope">
            {{ scope.row.descripcion || '—' }}
          </template>
        </el-table-column>
        
        <!-- RUC (oculto por defecto) -->
        <el-table-column 
          v-if="columns.ruc.visible" 
          label="RUC" 
          prop="empresa_ruc" 
          min-width="110" 
        />
        
        <!-- Empresa (siempre visible) -->
        <!-- Agosto 2026 (punto 1): se muestra el Nombre Comercial, que es como el
             equipo reconoce al cliente. Si no lo tiene cargado, cae a la Razón Social. -->
        <el-table-column label="EMPRESA" width="160" show-overflow-tooltip>
          <template slot-scope="scope">{{ nombreEmpresa(scope.row) }}</template>
        </el-table-column>
        
        <!-- Tipo Empresa (oculto por defecto) -->
        <el-table-column 
          v-if="columns.tipo_empresa.visible" 
          label="TIPO EMPRESA" 
          prop="empresa_tipo" 
          min-width="120" 
        />
        
        <!-- Contacto -->
        <el-table-column 
          v-if="columns.contacto.visible" 
          label="CONTACTO" 
          prop="contacto_nombre" 
          width="140" 
          show-overflow-tooltip 
        />
        
        <!-- Cargo (oculto por defecto) -->
        <el-table-column 
          v-if="columns.cargo.visible" 
          label="CARGO" 
          prop="contacto_cargo" 
          min-width="100"
          show-overflow-tooltip
        />
        
        <!-- Teléfono (oculto por defecto) -->
        <el-table-column 
          v-if="columns.telefono.visible" 
          label="TELÉFONO" 
          prop="contacto_telefono" 
          min-width="110"
          show-overflow-tooltip
        />
        
        <!-- Email (oculto por defecto) -->
        <el-table-column 
          v-if="columns.email.visible" 
          label="EMAIL" 
          prop="contacto_email" 
          min-width="160" 
          show-overflow-tooltip 
        />
        
        <!-- Origen -->
        <el-table-column 
          v-if="columns.origen.visible" 
          label="ORIGEN" 
          prop="origen" 
          min-width="90" 
        />
        
        <!-- Prioridad (siempre visible) -->
        <el-table-column label="PRIORIDAD" min-width="100">
          <template slot-scope="scope">
            <el-tag
              size="mini"
              :type="priorityTagType(scope.row.nivel_prioridad)"
              effect="plain"
            >
              {{ scope.row.nivel_prioridad || '—' }}
            </el-tag>
          </template>
        </el-table-column>
        
        <!-- Tipo condición proyecto (oculto por defecto) -->
        <el-table-column 
          v-if="columns.tipo_condicion_proyecto.visible" 
          label="TIPO CONDICIÓN" 
          prop="tipo_condicion_proyecto" 
          min-width="130"
          show-overflow-tooltip
        />
        
        <!-- Motivo compra (oculto por defecto) -->
        <el-table-column 
          v-if="columns.motivo_compra.visible" 
          label="MOTIVO COMPRA" 
          prop="motivo_compra" 
          min-width="130" 
          show-overflow-tooltip 
        />
        
        <!-- Forma de pago (oculto por defecto) -->
        <el-table-column 
          v-if="columns.forma_pago.visible" 
          label="FORMA PAGO" 
          prop="forma_pago" 
          min-width="110" 
        />
        
        <!-- Nivel tentativa (oculto por defecto) -->
        <el-table-column 
          v-if="columns.nivel_tentativo.visible" 
          label="NIVEL TENTATIVA" 
          prop="nivel_tentativo" 
          min-width="120"
          show-overflow-tooltip
        />
        
        <!-- Categoría productos (oculto por defecto) -->
        <el-table-column 
          v-if="columns.categoria_productos.visible" 
          label="CATEGORÍA PROD." 
          prop="categoria_productos" 
          min-width="130"
          show-overflow-tooltip
        />
        
        <!-- Usuario apoyo (oculto por defecto) -->
        <el-table-column 
          v-if="columns.usuario_apoyo.visible" 
          label="USUARIO APOYO" 
          prop="usuario_apoyo_nombre" 
          min-width="130"
          show-overflow-tooltip
        />
        
        <!-- Lead -->
        <el-table-column 
          v-if="columns.lead_id.visible" 
          label="LEAD" 
          prop="lead_id" 
          min-width="70" 
        />
        
        <!-- Monto Estimado -->
        <el-table-column 
          v-if="columns.monto_estimado.visible" 
          label="MONTO EST." 
          min-width="110"
        >
          <template slot-scope="scope">
            {{ formatMoney(scope.row.monto_estimado) }}
          </template>
        </el-table-column>
        
        <!-- Monto Real -->
        <el-table-column 
          v-if="columns.monto_real.visible" 
          label="MONTO REAL" 
          min-width="110"
        >
          <template slot-scope="scope">
            {{ formatMoney(scope.row.monto_real) }}
          </template>
        </el-table-column>
        
        <!-- Fecha Requerida -->
        <el-table-column 
          v-if="columns.fecha_requerida.visible" 
          label="FECHA REQ." 
          prop="fecha_requerida" 
          min-width="100" 
        />
        
        <!-- Actualizado -->
        <el-table-column 
          v-if="columns.updated_at.visible" 
          label="ACTUALIZADO" 
          prop="updated_at" 
          min-width="130"
          show-overflow-tooltip
        />
        
        <!-- Usuario (última interacción) -->
        <el-table-column 
          v-if="columns.usuario_nombre.visible" 
          label="USUARIO" 
          prop="usuario_nombre" 
          min-width="120"
          show-overflow-tooltip
        />
        
        <!-- Etapa (siempre visible) -->
        <el-table-column label="ETAPA" prop="etapa" min-width="140" />
        
        <!-- ACCIONES (siempre visible, fixed right) -->
        <el-table-column label="ACCIONES" fixed="right" min-width="160">
          <template slot-scope="scope">
            <el-button type="text" size="mini" @click="openDetail(scope.row.id)">
              <i class="el-icon-view"></i> Ver
            </el-button>
            <el-button type="text" size="mini" @click="openInteraccionRapida(scope.row)">
              <i class="el-icon-chat-line-round"></i>
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- MODAL NUEVO REQUERIMIENTO -->
    <el-dialog
      :visible.sync="newDialog.visible"
      width="720px"
      top="10vh"
      :close-on-click-modal="false"
    >
      <span slot="title">Nuevo Requerimiento</span>

      <el-form :model="newForm" label-position="top" size="mini" @submit.native.prevent>
        <el-row :gutter="10">

          <el-col :xs="24" :sm="12">
            <el-form-item label="Empresa *">
              <el-select
                v-model="newForm.empresa_id"
                placeholder="Seleccione empresa"
                style="width:100%"
                :loading="loadingEmpresas"
              >
                <el-option
                  v-for="e in empresasOptions"
                  :key="e.id"
                  :label="`${e.razon_social} (${e.ruc})`"
                  :value="Number(e.id)"
                />
              </el-select>
            </el-form-item>
          </el-col>

          <el-col :xs="24" :sm="12">
            <el-form-item label="Lead ID (opcional)">
              <el-input v-model="newForm.lead_id" placeholder="ID de lead (si aplica)" />
            </el-form-item>
          </el-col>
        </el-row>

        <el-form-item label="Título *">
          <el-input v-model="newForm.titulo" placeholder="Ej: Cotización de equipos / Servicio X" />
        </el-form-item>

        <el-form-item label="Descripción (opcional)">
          <el-input
            type="textarea"
            :autosize="{minRows: 3, maxRows: 7}"
            v-model="newForm.descripcion"
            placeholder="Detalle del requerimiento..."
          />
        </el-form-item>

        <el-row :gutter="10">
          <el-col :xs="24" :sm="12">
            <el-form-item label="Etapa">
              <el-select v-model="newForm.etapa" placeholder="Seleccione" style="width:100%">
                <el-option label="Pendiente atender" value="Pendiente atender" />
                <el-option label="En cotización" value="En cotización" />
              </el-select>
              <div style="font-size:11px;color:#909399;line-height:1.35;margin-top:3px;">
                Las etapas de cotización se alcanzan al avanzar en el flujo (cotizar, aprobar) o desde el tablero.
              </div>
            </el-form-item>
          </el-col>

          <el-col :xs="24" :sm="12">
            <el-form-item label="Fecha requerida (opcional)">
              <el-date-picker
                v-model="newForm.fecha_requerida"
                type="date"
                placeholder="YYYY-MM-DD"
                format="yyyy-MM-dd"
                value-format="yyyy-MM-dd"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="10">
          <el-col :xs="24" :sm="12">
            <el-form-item label="Monto estimado (S/) (opcional)">
              <el-input v-model="newForm.monto_estimado" type="number" step="0.01" placeholder="0.00" />
            </el-form-item>
          </el-col>

          <el-col :xs="24" :sm="12">
            <el-form-item label="Estado">
              <el-select v-model="newForm.estado" placeholder="abierto" style="width:100%">
                <el-option label="abierto" value="abierto" />
                <el-option label="cerrado" value="cerrado" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

        <el-row :gutter="10">
          <el-col :xs="24" :sm="12">
            <el-form-item label="Origen">
              <el-select v-model="newForm.origen" placeholder="Seleccione" style="width:100%">
                <el-option label="FIDELIZADO" value="FIDELIZADO" />
                <el-option label="REFERIDO" value="REFERIDO" />
                <el-option label="OP. COMERCIAL" value="OP. COMERCIAL" />
                <el-option label="MKTG - RRSS" value="MKTG - RRSS" />
                <el-option label="MKTG - MAIL" value="MKTG - MAIL" />
                <el-option label="MKTG - C.PAGO" value="MKTG - C.PAGO" />
                <el-option label="MKTG - WSP PAGADO" value="MKTG - WSP PAGADO" />
                <el-option label="MKTG - WSP MANUAL" value="MKTG - WSP MANUAL" />
                <el-option label="OTROS" value="OTROS" />
              </el-select>
            </el-form-item>
          </el-col>

          <el-col :xs="24" :sm="12">
            <el-form-item label="Prioridad">
              <el-select v-model="newForm.nivel_prioridad" placeholder="Medio" style="width:100%">
                <el-option label="Muy alto" value="Muy alto" />
                <el-option label="Alto" value="Alto" />
                <el-option label="Medio" value="Medio" />
                <el-option label="Bajo" value="Bajo" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>

      </el-form>

      <span slot="footer" class="dialog-footer">
        <el-button size="mini" @click="newDialog.visible = false">Cancelar</el-button>
        <el-button type="primary" size="mini" :loading="newDialog.submitting" @click="submitNuevo">
          Crear
        </el-button>
      </span>
    </el-dialog>



    <!-- MODAL DETALLE (tabs) -->
    <el-dialog
      :visible.sync="detailDialog.visible"
      width="96%"
      top="3vh"
      :close-on-click-modal="false"
      :before-close="beforeDetailDialogClose"
      class="req-dialog"
    >
      <span slot="title" class="req-dialog-title">
        {{ selectedId ? (detail.empresa_razon_social || 'Requerimiento') : 'Nuevo Requerimiento' }}
        <small v-if="detail.codigo" class="req-dialog-subtitle"> · {{ detail.codigo }}</small>
      </span>

      <div class="req-dialog-body" v-loading="detailDialog.loading">
        <el-row :gutter="16" class="req-dialog-layout">
          <!-- Columna izquierda: tabs.
               En las pestañas de cotización (pre-cotizacion, proveedores,
               cotizacion-final, cotizaciones) ocupa el ancho completo (md=24)
               porque la Bitácora se oculta para dejar espacio horizontal. -->
          <el-col :xs="24" :md="leftColSpan">
            <el-tabs v-model="detailDialog.activeTab" type="card" :before-leave="beforeDetailTabLeave" @tab-click="onDetailTabClick">
              <!-- Resumen -->
              <!-- ✅ PESTAÑA DATOS (antes "Resumen") -->
              <el-tab-pane label="Datos" name="datos">
                <TabDatos
                  ref="tabDatos"
                  :requerimiento="detail"
                  :isNewRecord="!selectedId"
                  :solo-lectura="!!detail.escalado_a_proyecto"
                  @updated="onRequerimientoUpdated"
                  @created="onRequerimientoCreado"
                  @show-nueva-empresa-dialog="showNuevaEmpresaDialog"
                  @show-nuevo-contacto-dialog="showNuevoContactoDialog"
                />
              </el-tab-pane>


              <!-- Interacciones -->
              <!-- ✅ PESTAÑA INTERACCIONES (rediseñada) -->
              <el-tab-pane label="Interacciones" name="interacciones">
                <TabInteracciones
                  :requerimientoId="detail.id"
                  @interaccion-added="onInteraccionAdded"
                />
              </el-tab-pane>


              <!-- Pre-Cotización (COM-02) -->
              <el-tab-pane label="Pre-Cotización" name="pre-cotizacion">
                <TabPreCotizacion
                  v-if="detailDialog.activeTab === 'pre-cotizacion'"
                  :requerimientoId="detail.id"
                  @precotizacion-added="loadCotizaciones"
                />
              </el-tab-pane>

              <!-- [NUEVO FLUJO 2026-10-20] Proveedores OCULTA temporalmente.
                   La asignación de proveedores ahora se maneja desde la
                   comparativa dentro de Pre-Cotización (acordeón del ítem).
                   El código se conserva por si se necesita reactivar. -->
              <el-tab-pane label="Proveedores" name="proveedores" v-if="false">
                <TabProveedores
                  v-if="detailDialog.activeTab === 'proveedores'"
                  :requerimientoId="detail.id"
                />
              </el-tab-pane>

              <!-- Preview editable (COM-02 — detalles del bloque editables inline) -->
              <el-tab-pane label="Preview" name="preview">
                <TabPreviewEditable
                  v-if="detailDialog.activeTab === 'preview'"
                  :requerimientoId="detail.id"
                />
              </el-tab-pane>

              <!-- Cotización Final (COM-02 — Salida Comercial Derivada) -->
              <el-tab-pane label="Cotización Final" name="cotizacion-final">
                <TabCotizacionFinal
                  v-if="detailDialog.activeTab === 'cotizacion-final'"
                  :requerimientoId="detail.id"
                  :contacto-nombre="detail.contacto_nombre"
                  :contacto-cargo="detail.contacto_cargo"
                  :contacto-email="detail.contacto_email"
                />
              </el-tab-pane>

              <!-- Cotizaciones -->
              <!-- ✅ PESTAÑA COTIZACIONES (rediseñada) -->
              <el-tab-pane label="Cotizaciones" name="cotizaciones">
                <TabCotizaciones
                  :requerimientoId="detail.id"
                  @cotizacion-added="onCotizacionAdded"
                />
              </el-tab-pane>


              <!-- Actividad / Flujo -->
              <!-- ❌ PESTAÑA "Actividad / Flujo" ELIMINADA -->

            </el-tabs>
          </el-col>

          <!-- Columna derecha: Bitácora de cambios.
               Oculta en las pestañas de cotización porque necesitan todo el
               ancho horizontal (tablas con muchas columnas). -->
          <el-col :xs="24" :md="8" v-if="bitacoraVisible">
            <el-card shadow="never" class="req-sidebar-card bitacora-card">
              <div slot="header" class="bitacora-header">
                <i class="el-icon-refresh"></i>
                <span class="bitacora-title">Bitácora de cambios</span>
              </div>

              <!-- Requerimiento convertido a proyecto: solo consulta + acceso al proyecto -->
              <div class="req-sidebar-section" v-if="detail.escalado_a_proyecto">
                <el-alert
                  :title="detail.proyecto_codigo ? ('Convertido al proyecto ' + detail.proyecto_codigo) : 'Convertido a proyecto'"
                  type="success"
                  description="Este requerimiento es de solo consulta."
                  :closable="false"
                  show-icon
                  style="margin-bottom: 10px;"
                />
                <el-button type="primary" size="mini" plain style="width: 100%" @click="verProyecto">
                  <i class="el-icon-top-right"></i> Ver proyecto{{ detail.proyecto_codigo ? (' ' + detail.proyecto_codigo) : '' }}
                </el-button>
              </div>

              <!-- Botón crear proyecto (si aplica) -->
              <div class="req-sidebar-section" v-if="detail.puede_crear_proyecto">
                <el-button
                  type="success"
                  size="mini"
                  @click="onCrearProyecto"
                  plain
                  style="width: 100%"
                  :disabled="detail.productos_sin_proveedor > 0"
                >
                  Crear proyecto desde requerimiento
                </el-button>
                <el-alert
                  v-if="detail.productos_sin_proveedor > 0"
                  type="warning"
                  :closable="false"
                  show-icon
                  style="margin-top: 8px;"
                >
                  <span slot="title" style="font-size: 12px; line-height: 1.35;">
                    {{ detail.productos_sin_proveedor }} de {{ detail.total_productos }} producto(s) sin proveedor asignado. Asígnalos en la pestaña <strong>Proveedores</strong> y el botón se habilitará automáticamente.
                  </span>
                </el-alert>
              </div>

              <!-- Lista de bitácora -->
              <div class="bitacora-panel" v-loading="logsState.loading">
                <div v-if="logs.length === 0" class="req-empty">
                  No hay cambios registrados.
                </div>

                <div
                  v-for="log in logs"
                  :key="log.id"
                  class="bitacora-item"
                >
                  <div class="bitacora-event">
                    <el-tag size="small" type="warning" effect="light">
                      {{ log.event }}
                    </el-tag>
                    <span v-if="log.tipo" class="bitacora-tipo">{{ log.tipo }}</span>
                  </div>

                  <div class="bitacora-user">
                    <i class="el-icon-user"></i> Usuario: {{ log.usuario }}
                  </div>

                  <div class="bitacora-date">
                    {{ log.created_at }}
                  </div>
                </div>
              </div>
            </el-card>
          </el-col>
        </el-row>
      </div>

      <span slot="footer" class="dialog-footer">
        <el-button size="mini" @click="requestCloseDetailDialog">
          Cerrar
        </el-button>
      </span>
    </el-dialog>

    <!-- MODAL CONFIRMACIÓN CREAR PROYECTO -->
    <el-dialog
      :visible.sync="crearProyectoDialog.visible"
      title="Crear Proyecto desde Requerimiento"
      width="580px"
      top="15vh"
      :close-on-click-modal="false"
    >
      <div style="margin-bottom: 16px;">
        <el-alert
          type="warning"
          :closable="false"
          show-icon
        >
          <span slot="title">Esta acción es irreversible</span>
          <span>El requerimiento dejará de estar activo en el CRM y no podrá convertirse de nuevo.</span>
        </el-alert>
      </div>

      <div style="background: #f9fafb; border-radius: 8px; padding: 16px; font-size: 13px;">
        <div style="font-weight: 600; font-size: 14px; margin-bottom: 12px; color: #1f2937;">
          Datos del proyecto a crear
        </div>
        <table style="width: 100%; border-collapse: collapse;">
          <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 6px 0; color: #6b7280; width: 140px;">Requerimiento</td>
            <td style="padding: 6px 0; font-weight: 500;">{{ detail.codigo }}</td>
          </tr>
          <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 6px 0; color: #6b7280;">Empresa</td>
            <td style="padding: 6px 0; font-weight: 500;">{{ detail.empresa_razon_social || '—' }}</td>
          </tr>
          <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 6px 0; color: #6b7280;">Contacto</td>
            <td style="padding: 6px 0; font-weight: 500;">{{ detail.contacto_nombre || '—' }}</td>
          </tr>
          <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 6px 0; color: #6b7280;">Nombre proyecto</td>
            <td style="padding: 6px 0; font-weight: 500;">{{ detail.titulo || '—' }}</td>
          </tr>
          <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 6px 0; color: #6b7280;">Monto estimado</td>
            <td style="padding: 6px 0; font-weight: 500;">S/ {{ formatMoney(detail.monto_estimado) }}</td>
          </tr>
          <tr style="border-bottom: 1px solid #e5e7eb;">
            <td style="padding: 6px 0; color: #6b7280;">Fecha requerida</td>
            <td style="padding: 6px 0; font-weight: 500;">{{ detail.fecha_requerida || '—' }}</td>
          </tr>
          <tr>
            <td style="padding: 6px 0; color: #6b7280;">Estado inicial</td>
            <td style="padding: 6px 0;">
              <el-tag size="mini" type="success">Nuevo Proyecto</el-tag>
            </td>
          </tr>
        </table>
      </div>

      <span slot="footer">
        <el-button size="small" @click="crearProyectoDialog.visible = false">Cancelar</el-button>
        <el-button type="success" size="small" :loading="crearProyectoDialog.loading" @click="confirmarCrearProyecto">
          <i class="el-icon-check"></i> Confirmar y crear proyecto
        </el-button>
      </span>
    </el-dialog>

    <!-- Modal Nueva Empresa (reutilizado desde módulo Empresas) -->
    <Empresas-form
      v-if="showEmpresaDialog"
      :recordId.sync="empresaRecordId"
      :showDialog.sync="showEmpresaDialog"
      :external="true"
    ></Empresas-form>

    <!-- Modal Nuevo Contacto (reutilizado desde módulo Contactos) -->
    <Contactos-form
      v-if="showContactoDialog"
      :key="'contacto-nuevo-' + (nuevoContactoEmpresaId || 'none')"
      :recordId.sync="contactoRecordId"
      :showDialog.sync="showContactoDialog"
      :empresaId="nuevoContactoEmpresaId"
      context="empresas"
      @contactSaved="handleContactoCreado"
    ></Contactos-form>
  </div>
</template>

<script>
import axios from 'axios';
// ✅ IMPORTAR LOS 3 COMPONENTES NUEVOS
import TabDatos from './TabDatos.vue';
import TabInteracciones from './TabInteracciones.vue';
import TabCotizaciones from './TabCotizaciones.vue';
import TabPreCotizacion from './TabPreCotizacion.vue';
import TabProveedores from './TabProveedores.vue';
import TabPreviewEditable from './TabPreviewEditable.vue';
import TabCotizacionFinal from './TabCotizacionFinal.vue';
// ✅ REUTILIZADOS desde módulos Empresas y Contactos
import EmpresasForm from '../../components/Empresasform.vue';
import ContactosForm from '../../components/Contactosform.vue';


export default {
  name: 'RequerimientosIndex',

  // ✅ REGISTRAR LOS COMPONENTES
  components: {
    TabDatos,
    TabInteracciones,
    TabCotizaciones,
    TabPreCotizacion,
    TabProveedores,
    TabPreviewEditable,
    TabCotizacionFinal,
    EmpresasForm,
    ContactosForm,
  },

  data() {
    return {
      // ✅ NUEVO: Control de visibilidad de filtros
      showFilters: true,
      
      // ✅ NUEVO: Control de columnas visibles
      columns: {
        // Columnas siempre visibles: ID, Título, Empresa, Etapa, Prioridad, Acciones
        // Columnas visibles por defecto:
        descripcion: { title: 'Descripción', visible: true },
        contacto: { title: 'Contacto', visible: true },
        origen: { title: 'Origen', visible: true },
        monto_estimado: { title: 'Monto Estimado', visible: true },
        monto_real: { title: 'Monto Real', visible: true },
        fecha_requerida: { title: 'Fecha Requerida', visible: true },
        updated_at: { title: 'Actualizado', visible: true },
        usuario_nombre: { title: 'Usuario', visible: true },
        lead_id: { title: 'Lead', visible: true },
        
        // Columnas ocultas por defecto (del dropdown):
        ruc: { title: 'RUC', visible: false },
        tipo_condicion_proyecto: { title: 'Tipo condición de proyecto', visible: false },
        motivo_compra: { title: 'Motivo compra', visible: false },
        forma_pago: { title: 'Forma de pago', visible: false },
        nivel_tentativo: { title: 'Nivel de tentativa', visible: false },
        categoria_productos: { title: 'Categoría productos', visible: false },
        usuario_apoyo: { title: 'Usuario apoyo', visible: false },
        tipo_empresa: { title: 'Tipo de Empresa', visible: false },
        cargo: { title: 'Cargo', visible: false },
        telefono: { title: 'Teléfono', visible: false },
        email: { title: 'Email', visible: false },
      },

      loadingList: false,
      list: [],
      filters: {
        etapa: 'Todos',
        prioridad: 'Todos',
        origen: 'Todos',
        searchBy: 'empresa_razon_social',
        search: '',
      },
      kpis: {
        pendientes_hoy: null,
        sin_respuesta_3dias: null,
        muy_alto: null,
        aprobadas_sin_proyecto_porcentaje: null,
      },

      // ✅ opciones de empresas (UI state)
      empresasOptions: [],
      loadingEmpresas: false,

      // Nuevo Requerimiento
      newDialog: {
        visible: false,
        submitting: false,
      },
      newForm: {
        empresa_id: null,
        titulo: '',
        descripcion: '',
        monto_estimado: '',
        fecha_requerida: '',
        etapa: 'nuevo',
        estado: 'abierto',
        origen: '',
        nivel_prioridad: 'Medio',
        lead_id: null,
      },


      // Detalle
      detailDialog: {
        visible: false,
        loading: false,
        activeTab: 'datos'  // ✅ CAMBIAR DE 'resumen' A 'datos',
      },
      selectedId: null,
      detail: {},

      // Interacciones
      interacciones: [],
      interaccionesState: {
        loading: false,
        submitting: false,
      },
      interaccionForm: {
        canal: '',
        resumen: '',
        requiere_seguimiento: false,
        proximo_seguimiento: '',
      },

      // Cotizaciones
      cotizaciones: [],
      cotizacionesState: {
        loading: false,
        submitting: false,
      },
      cotizacionForm: {
        archivo: null,
        monto: '',
        observaciones: '',
      },

      // Historial (legacy)
      historial: [],
      historialState: {
        loading: false,
      },

      // Logs de auditoría
      logs: [],
      logsState: {
        loading: false,
      },

      csrfToken:
        document.head.querySelector('meta[name="csrf-token"]')?.content || '',

      // Dialog de confirmación crear proyecto
      crearProyectoDialog: {
        visible: false,
        loading: false,
      },

      // ✅ Dialogs reutilizados para crear Empresa/Contacto desde TabDatos
      showEmpresaDialog: false,
      empresaRecordId: null,
      showContactoDialog: false,
      contactoRecordId: null,
      nuevoContactoEmpresaId: null,
    };
  },

  computed: {
    filteredList() {
      let rows = this.list.slice();

      // Búsqueda local
      const term = (this.filters.search || '').toLowerCase().trim();
      if (term) {
        const field = this.filters.searchBy;
        rows = rows.filter((r) => {
          // Agosto 2026 (punto 1): "Cliente" busca en los DOS nombres. La columna
          // muestra el Nombre Comercial, así que el usuario va a escribir ese; pero
          // también tiene que poder buscar por la Razón Social, que es el dato formal.
          if (field === 'empresa_razon_social') {
            const comercial = (r.empresa_nombre_comercial || '').toString().toLowerCase();
            const razon = (r.empresa_razon_social || '').toString().toLowerCase();
            return comercial.includes(term) || razon.includes(term);
          }
          const value = (r[field] || '').toString().toLowerCase();
          return value.includes(term);
        });
      }
      return rows;
    },

    // ── Layout del dialog de detalle ──
    // La Bitácora de cambios se oculta en las pestañas de cotización para
    // dejar más espacio horizontal a tablas con muchas columnas. En las
    // pestañas Datos e Interacciones se muestra normal.
    bitacoraVisible() {
      var tabsSinBitacora = ['pre-cotizacion', 'proveedores', 'preview', 'cotizacion-final', 'cotizaciones'];
      var activeTab = this.detailDialog && this.detailDialog.activeTab ? this.detailDialog.activeTab : '';
      return tabsSinBitacora.indexOf(activeTab) === -1;
    },

    // Span de la columna izquierda (tabs). 16 cuando la Bitácora ocupa
    // el resto, 24 (todo el ancho) cuando la Bitácora está oculta.
    leftColSpan() {
      return this.bitacoraVisible ? 16 : 24;
    },
  },

  created() {
    this.fetchKpis();
    this.fetchRequerimientos();
  },

  methods: {
    // Navegación a Kanban
    goKanban() {
      window.location.href = '/comercial/requerimientos/kanban';
    },

    async onNuevo() {
      // Abrir el popup completo en modo creación (sin ID)
      this.selectedId = null;
      this.detail = {};
      this.logs = [];
      this.interacciones = [];
      this.cotizaciones = [];
      this.historial = [];
      this.detailDialog.activeTab = 'datos';
      this.detailDialog.loading = false;
      this.detailDialog.visible = true;
    },


    // ── Nombre de la empresa en el listado (agosto 2026, punto 1) ──
    // Se prioriza el Nombre Comercial porque es el que el equipo reconoce de
    // inmediato. Cae a la Razón Social cuando el comercial no está cargado.
    //
    // El guion se contempla a propósito: como el campo no siempre se puede dejar
    // vacío, es habitual que se cargue un "-" como marcador de "sin dato", y
    // mostrar un guion en la columna no le sirve a nadie.
    nombreEmpresa(row) {
      const comercial = (row.empresa_nombre_comercial || '').toString().trim();
      const soloGuiones = comercial !== '' && comercial.replace(/[-–—\s]/g, '') === '';
      if (comercial === '' || soloGuiones) {
        return (row.empresa_razon_social || '').toString().trim() || '—';
      }
      return comercial;
    },

    async loadEmpresasOptions(q = '') {
      this.loadingEmpresas = true;
      try {
        const { data } = await axios.get('/comercial/empresas/options', {
          params: { q },
          headers: { Accept: 'application/json' },
        });

        this.empresasOptions = (data && data.ok && Array.isArray(data.data)) ? data.data : [];
      } catch (e) {
        console.error('[REQ] error empresas options', e?.response?.data || e);
        this.empresasOptions = [];
        this.$message.error('No se pudieron cargar las empresas.');
      } finally {
        this.loadingEmpresas = false;
      }
    },



    onSearchChange() {
      // Filtro local, no pega a la API
    },

    async fetchKpis() {
      try {
        const { data } = await axios.get('/comercial/requerimientos/kpis');
        if (data && data.ok && data.data) {
          this.kpis = data.data;
        }
      } catch (e) {
        console.error('[REQ] error kpis', e);
      }
    },

    async fetchRequerimientos() {
      this.loadingList = true;
      try {
        const params = {};
        if (this.filters.etapa && this.filters.etapa !== 'Todos') {
          params.etapa = this.filters.etapa;
        }
        if (this.filters.prioridad && this.filters.prioridad !== 'Todos') {
          params.prioridad = this.filters.prioridad;
        }
        if (this.filters.origen && this.filters.origen !== 'Todos') {
          params.origen = this.filters.origen;
        }

        const { data } = await axios.get('/comercial/requerimientos/records', {
          params,
        });

        this.list = Array.isArray(data) ? data : [];
      } catch (e) {
        console.error('[REQ] error list', e);
        this.$message.error('No se pudo cargar la lista de requerimientos.');
      } finally {
        this.loadingList = false;
      }
    },

    openDetailFromRow(row) {
      this.openDetail(row.id);
    },

    async openDetail(id) {
      this.selectedId = id;
      this.detailDialog.visible = true;
      this.detailDialog.loading = true;
      this.detailDialog.activeTab = 'datos';
      this.interacciones = [];
      this.cotizaciones = [];
      this.historial = [];
      this.logs = [];

      try {
        const { data } = await axios.get(`/comercial/requerimientos/${id}`, {
          headers: { Accept: 'application/json' },
        });

        if (!data || !data.ok) {
          this.$message.error(data.msg || 'No se pudo cargar el detalle.');
          return;
        }

        this.detail = data.data || {};
        // Cargar tabs en paralelo
        this.loadInteracciones();
        this.loadCotizaciones();
        this.loadHistorial();
        this.loadLogs();
      } catch (e) {
        console.error('[REQ] error show', e);
        this.$message.error('Error al cargar el detalle.');
      } finally {
        this.detailDialog.loading = false;
      }
    },

    // ── Cambios sin guardar en Datos (TabDatos) ──────────────────────
    // true si el formulario de Datos difiere de lo último guardado.
    tabDatosHasChanges: function() {
      var t = this.$refs.tabDatos;
      return !!(t && typeof t.hasUnsavedChanges === 'function' && t.hasUnsavedChanges());
    },

    discardTabDatosChanges: function() {
      var t = this.$refs.tabDatos;
      if (t && typeof t.discardChanges === 'function') t.discardChanges();
    },

    // Confirmación común. Resuelve si puede continuar, rechaza si cancela.
    confirmDiscardDatosChanges: function(accion) {
      var self = this;
      return new Promise(function(resolve, reject) {
        self.$confirm(
          'Tienes cambios sin guardar en Datos. Si continúas se perderán.',
          'Cambios sin guardar',
          {
            confirmButtonText: accion || 'Descartar y continuar',
            cancelButtonText: 'Seguir editando',
            type: 'warning'
          }
        ).then(function() {
          self.discardTabDatosChanges();
          resolve();
        }).catch(function() {
          reject();
        });
      });
    },

    // El-tabs la llama antes de cambiar: devolver false/Promise rechazada
    // mantiene la pestaña actual.
    beforeDetailTabLeave: function(activeName, oldActiveName) {
      if (oldActiveName !== 'datos' || !this.tabDatosHasChanges()) return true;
      return this.confirmDiscardDatosChanges('Descartar y cambiar');
    },

    // El-dialog la llama al intentar cerrar (X, ESC). done() cierra de verdad.
    beforeDetailDialogClose: function(done) {
      var self = this;
      if (!self.tabDatosHasChanges()) {
        done();
        return;
      }
      self.confirmDiscardDatosChanges('Descartar y cerrar').then(function() {
        done();
      }).catch(function() {
        // El usuario decidió seguir editando: no se cierra.
      });
    },

    // Botón "Cerrar" del footer: pasa por la misma validación.
    requestCloseDetailDialog: function() {
      var self = this;
      self.beforeDetailDialogClose(function() {
        self.detailDialog.visible = false;
      });
    },

    // Refresca el contador de productos sin proveedor al cambiar de pestaña, para
    // que el botón "Crear proyecto" se habilite apenas se asignen los proveedores
    // (en la pestaña Proveedores) sin necesidad de reabrir el requerimiento.
    async onDetailTabClick() {
      if (!this.selectedId) return;
      try {
        const { data } = await axios.get(`/comercial/requerimientos/${this.selectedId}`, {
          headers: { Accept: 'application/json' },
        });
        if (data && data.ok && data.data) {
          this.$set(this.detail, 'productos_sin_proveedor', data.data.productos_sin_proveedor);
          this.$set(this.detail, 'total_productos', data.data.total_productos);
          this.$set(this.detail, 'puede_crear_proyecto', data.data.puede_crear_proyecto);
        }
      } catch (e) {
        // Silencioso: el backend sigue validando al intentar crear el proyecto.
      }
    },

    openInteraccionRapida(row) {
      // Atajo: abre detalle directamente en tab Interacciones
      this.openDetail(row.id);
      this.detailDialog.activeTab = 'interacciones';
    },

    // PRIORIDAD helpers
    priorityTagType(value) {
      const v = (value || '').toLowerCase();
      if (v.includes('muy')) return 'danger';
      if (v === 'alto') return 'warning';
      if (v === 'medio') return 'info';
      return '';
    },

    formatMoney(v) {
      if (v == null || v === '') return '—';
      const num = Number(v);
      if (Number.isNaN(num)) return '—';
      return num.toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },

    formatDate(dateStr) {
      if (!dateStr) return '—';
      return String(dateStr).substr(0, 10);
    },

    formatDateTime(dt) {
      if (!dt) return '—';
      return String(dt).replace('T', ' ').substr(0, 16);
    },

    // === Interacciones ===
    async loadInteracciones() {
      if (!this.selectedId) return;
      this.interaccionesState.loading = true;
      try {
        const { data } = await axios.get(
          `/comercial/requerimientos/${this.selectedId}/interacciones`,
          { headers: { Accept: 'application/json' } }
        );
        if (data && data.ok) {
          this.interacciones = data.data || [];
        } else {
          this.interacciones = [];
        }
      } catch (e) {
        console.error('[REQ] error interacciones', e);
        this.$message.error('No se pudieron cargar las interacciones.');
      } finally {
        this.interaccionesState.loading = false;
      }
    },

    async submitInteraccion() {
      if (!this.selectedId) return;

      if (!this.interaccionForm.resumen.trim()) {
        this.$message.warning('Ingresa un resumen de la interacción.');
        return;
      }

      this.interaccionesState.submitting = true;
      try {
        const payload = {
          canal: this.interaccionForm.canal || 'Otro',
          resumen: this.interaccionForm.resumen,
          requiere_seguimiento: this.interaccionForm.requiere_seguimiento ? 1 : 0,
          proximo_seguimiento: this.interaccionForm.proximo_seguimiento || null,
        };

        const { data } = await axios.post(
          `/comercial/requerimientos/${this.selectedId}/interaccion`,
          payload,
          {
            headers: {
              'X-CSRF-TOKEN': this.csrfToken,
              Accept: 'application/json',
            },
          }
        );

        if (!data || !data.ok) {
          this.$message.error(
            data && data.msg ? data.msg : 'No se pudo registrar la interacción.'
          );
          return;
        }

        this.$message.success('Interacción registrada.');
        // limpiar form
        this.interaccionForm = {
          canal: '',
          resumen: '',
          requiere_seguimiento: false,
          proximo_seguimiento: '',
        };

        // recargar lista
        this.loadInteracciones();
        // también refrescar lista principal para fecha_ultima_interaccion
        this.fetchRequerimientos();
      } catch (e) {
        console.error('[REQ] error crearInteraccion', e);
        this.$message.error('Error al registrar la interacción.');
      } finally {
        this.interaccionesState.submitting = false;
      }
    },

    // === Cotizaciones ===
    async loadCotizaciones() {
      if (!this.selectedId) return;
      this.cotizacionesState.loading = true;
      try {
        const { data } = await axios.get(
          `/comercial/requerimientos/${this.selectedId}/cotizaciones`,
          { headers: { Accept: 'application/json' } }
        );
        if (data && data.ok) {
          this.cotizaciones = data.data || [];
        } else {
          this.cotizaciones = [];
        }
      } catch (e) {
        console.error('[REQ] error cotizaciones', e);
        this.$message.error('No se pudieron cargar las cotizaciones.');
      } finally {
        this.cotizacionesState.loading = false;
      }
    },

    onCotizacionFileChange(e) {
      const file = e.target.files[0];
      this.cotizacionForm.archivo = file || null;
    },

    async submitCotizacion() {
      if (!this.selectedId) return;

      if (!this.cotizacionForm.archivo) {
        this.$message.warning('Selecciona un archivo para la cotización.');
        return;
      }
      if (!this.cotizacionForm.monto) {
        this.$message.warning('Ingresa el monto de la cotización.');
        return;
      }

      this.cotizacionesState.submitting = true;
      try {
        const formData = new FormData();
        formData.append('archivo', this.cotizacionForm.archivo);
        formData.append('monto', this.cotizacionForm.monto);
        if (this.cotizacionForm.observaciones) {
          formData.append('observaciones', this.cotizacionForm.observaciones);
        }

        const { data } = await axios.post(
          `/comercial/requerimientos/${this.selectedId}/cotizacion`,
          formData,
          {
            headers: {
              'X-CSRF-TOKEN': this.csrfToken,
              'Content-Type': 'multipart/form-data',
              Accept: 'application/json',
            },
          }
        );

        if (!data || !data.ok) {
          this.$message.error(
            data && data.msg ? data.msg : 'No se pudo subir la cotización.'
          );
          return;
        }

        this.$message.success('Cotización subida.');
        // limpiar form
        this.cotizacionForm = {
          archivo: null,
          monto: '',
          observaciones: '',
        };
        // recargar lista
        this.loadCotizaciones();
        // refrescar lista principal para monto_estimado / etapa
        this.fetchRequerimientos();
      } catch (e) {
        console.error('[REQ] error subirCotizacion', e);
        this.$message.error('Error al subir la cotización.');
      } finally {
        this.cotizacionesState.submitting = false;
      }
    },

    async submitNuevo() {
      const empresaId = Number(this.newForm.empresa_id);

      if (!empresaId || Number.isNaN(empresaId)) {
        this.$message.warning('Selecciona una empresa.');
        return;
      }

      if (!this.newForm.titulo || !this.newForm.titulo.trim()) {
        this.$message.warning('El título es obligatorio.');
        return;
      }

      const leadIdRaw = this.newForm.lead_id;
      const leadId = leadIdRaw !== '' && leadIdRaw != null ? Number(leadIdRaw) : null;
      if (leadIdRaw !== '' && leadIdRaw != null && Number.isNaN(leadId)) {
        this.$message.warning('Lead inválido.');
        return;
      }

      const monto = this.newForm.monto_estimado !== '' && this.newForm.monto_estimado != null
        ? Number(this.newForm.monto_estimado)
        : null;

      if (monto != null && Number.isNaN(monto)) {
        this.$message.warning('Monto inválido.');
        return;
      }

      this.newDialog.submitting = true;

      try {
        const payload = {
          titulo: this.newForm.titulo.trim(),
          descripcion: this.newForm.descripcion || null,
          monto_estimado: monto,
          fecha_requerida: this.newForm.fecha_requerida || null,

          etapa: this.newForm.etapa || 'nuevo',
          origen: this.newForm.origen || '',
          nivel_prioridad: this.newForm.nivel_prioridad || 'Medio',

          empresa_id: empresaId,
          lead_id: leadId,
        };

        const { data } = await axios.post('/comercial/requerimientos', payload, {
          headers: {
            'X-CSRF-TOKEN': this.csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
          withCredentials: true,
        });

        if (!data || !data.ok) {
          console.error('[REQ] store response', data);
          this.$message.error((data && data.msg) ? data.msg : 'No se pudo crear el requerimiento.');
          return;
        }

        this.$message.success('Requerimiento creado.');
        this.newDialog.visible = false;

        await this.fetchKpis();
        await this.fetchRequerimientos();

        if (data.data && data.data.id) {
          this.openDetail(data.data.id);
        }
        } catch (e) {
          console.error('[REQ] error store', e);

          const status = e?.response?.status;
          const resp = e?.response?.data;

          // Si Laravel devolvió JSON con error real
          if (resp) {
            console.error('[REQ] response', resp);

            // Prioridad: msg / error / errors
            const msg =
              resp.msg ||
              resp.error ||
              (resp.errors ? JSON.stringify(resp.errors) : null) ||
              'Error al crear el requerimiento.';

            this.$message.error(`${status || ''} ${msg}`.trim());
            return;
          }

          this.$message.error('Error al crear el requerimiento.');
        }

    },


    // === Historial (legacy) ===
    async loadHistorial() {
      if (!this.selectedId) return;
      this.historialState.loading = true;
      try {
        const { data } = await axios.get(
          `/comercial/requerimientos/${this.selectedId}/historial`,
          { headers: { Accept: 'application/json' } }
        );
        if (data && data.ok) {
          this.historial = data.data || [];
        } else {
          this.historial = [];
        }
      } catch (e) {
        console.error('[REQ] error historial', e);
        this.$message.error('No se pudo cargar el historial.');
      } finally {
        this.historialState.loading = false;
      }
    },

    // === Logs de auditoría ===
    async loadLogs() {
      if (!this.selectedId) return;
      this.logsState.loading = true;
      try {
        const { data } = await axios.get(
          `/comercial/requerimientos/${this.selectedId}/logs`,
          { headers: { Accept: 'application/json' } }
        );
        if (data && data.ok) {
          this.logs = data.data || [];
        } else {
          this.logs = [];
        }
      } catch (e) {
        console.error('[REQ] error logs', e);
        // No mostrar error si no hay logs aún
        this.logs = [];
      } finally {
        this.logsState.loading = false;
      }
    },

    // Crear proyecto (solo hook; el endpoint ya existe en backend)
    verProyecto() {
      // Acceso al proyecto generado (requerimiento convertido a proyecto).
      window.location.href = '/comercial/proyectos/listado'
    },

    async onCrearProyecto() {
        if (!this.selectedId) return;
        this.crearProyectoDialog.visible = true;
    },

    async confirmarCrearProyecto() {
        if (!this.selectedId) return;
        this.crearProyectoDialog.loading = true;
        try {
            const { data } = await axios.post(
                `/comercial/requerimientos/${this.selectedId}/crear-proyecto`,
                {},
                {
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        Accept: 'application/json',
                    },
                }
            );

            if (!data || !data.ok) {
                this.$message.error(data && data.msg ? data.msg : 'No se pudo crear el proyecto.');
                return;
            }

            this.$message.success(data.msg || 'Proyecto creado exitosamente.');
            this.crearProyectoDialog.visible = false;
            this.detailDialog.visible = false;
            this.fetchRequerimientos();
            this.fetchKpis();

            if (data.redirect) {
                window.location.href = data.redirect;
            }
        } catch (e) {
            console.error('[REQ] error crearProyecto', e);
            var msg = (e.response && e.response.data && e.response.data.msg)
                ? e.response.data.msg
                : 'Error al crear el proyecto.';
            this.$message.error(msg);
        } finally {
            this.crearProyectoDialog.loading = false;
        }
    },

    // ✅ MÉTODOS PARA LOS EVENTOS DE LOS COMPONENTES TAB
    onRequerimientoCreado(data) {
      // Cuando TabDatos crea un nuevo requerimiento, convertir el dialog a modo edición
      if (data && data.id) {
        this.selectedId = data.id;
        this.detail = data;
        this.fetchKpis();
        this.fetchRequerimientos();
        this.loadLogs();
      }
    },

    onRequerimientoUpdated(data) {
      // Actualizar el detalle con los datos nuevos
      this.detail = { ...this.detail, ...data };
      
      // Refrescar la lista
      this.fetchRequerimientos();
      
      // Mensaje opcional (el componente ya muestra uno)
      // this.$message.success('Requerimiento actualizado');
    },

    onInteraccionAdded() {
      // Recargar el detalle para actualizar fecha_ultima_interaccion
      if (this.detail && this.detail.id) {
        this.openDetail(this.detail.id);
      }

      // Refrescar lista
      this.fetchRequerimientos();
    },

    onCotizacionAdded() {
      // Recargar el detalle para actualizar monto_estimado
      if (this.detail && this.detail.id) {
        this.openDetail(this.detail.id);
      }

      // Refrescar lista
      this.fetchRequerimientos();

      // Refrescar KPIs
      this.fetchKpis();
    },

    showNuevaEmpresaDialog() {
      this.empresaRecordId = null;
      this._expectingEmpresaCreate = true;
      this.showEmpresaDialog = true;
    },

    showNuevoContactoDialog(empresaId) {
      if (!empresaId) {
        this.$message.warning('Seleccione una empresa primero');
        return;
      }
      this.contactoRecordId = null;
      this.nuevoContactoEmpresaId = empresaId;
      this.showContactoDialog = true;
    },

    // Handler cuando Empresasform.vue emite reloadDataPersons (con external=true)
    handleEmpresaCreada(empresaId) {
      // Solo actuar si nosotros abrimos el diálogo (evita colisiones con otros flujos)
      if (!this._expectingEmpresaCreate || !empresaId) return;
      this._expectingEmpresaCreate = false;

      if (this.$refs.tabDatos) {
        this.$refs.tabDatos.setEmpresaId(Number(empresaId));
      }
    },

    // Handler cuando Contactosform.vue emite contactSaved
    handleContactoCreado(empresaId) {
      var self = this;
      // Buscar el contacto más reciente de esa empresa y asignarlo
      if (!empresaId || !this.$refs.tabDatos) return;

      axios.get('/comercial/contactos/empresa/' + empresaId, {
        headers: { 'Accept': 'application/json' }
      })
      .then(function(response) {
        var data = response.data;
        var contactos = Array.isArray(data) ? data : (data && data.data ? data.data : []);
        if (contactos.length === 0) return;

        // El más reciente suele ser el de mayor id
        var masReciente = contactos.reduce(function(acc, c) {
          return (!acc || c.id > acc.id) ? c : acc;
        }, null);

        if (masReciente) {
          self.$refs.tabDatos.setContactoId(masReciente.id, empresaId);
        }
      })
      .catch(function(err) {
        console.error('Error al obtener el contacto recién creado:', err);
      });
    },
  },

  mounted() {
    // Escuchar evento emitido por Empresasform.vue cuando se crea con external=true
    this.$eventHub.$on('reloadDataPersons', this.handleEmpresaCreada);
  },

  beforeDestroy() {
    this.$eventHub.$off('reloadDataPersons', this.handleEmpresaCreada);
  },
};
</script>

<style scoped>
.req-page {
  background: #ffffff;
  padding: 16px 16px 24px;
  color: #0b1220;
}

/* ✅ NUEVO: Toolbar con botones */
.req-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 12px;
  padding: 8px 0;
}

.req-columns-dropdown {
  margin-left: auto;
}

.req-columns-dropdown >>> .el-dropdown-menu {
  max-height: 300px;
  overflow-y: auto;
}

.req-columns-dropdown >>> .el-dropdown-item {
  padding: 5px 20px;
}

.req-columns-dropdown >>> .el-checkbox {
  display: block;
  width: 100%;
}

.req-header {
  margin-bottom: 10px;
}

.req-title {
  margin: 0;
  font-size: 22px;
  font-weight: 800;
  letter-spacing: 0.2px;
}

.req-header-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

.req-kpis {
  margin-top: 8px;
}

.req-kpi-card {
  border-radius: 12px;
}

.req-kpi-label {
  font-size: 12px;
  color: #6b7280;
}

.req-kpi-value {
  font-size: 20px;
  font-weight: 700;
  margin-top: 4px;
}

.req-filters {
  margin-top: 12px;
  border-radius: 12px;
}

.req-filters-row {
  margin-bottom: 4px;
}

.req-filter-label {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 2px;
}

.req-filter-input {
  width: 100%;
}

.req-search {
  display: flex;
  gap: 6px;
  align-items: center;
}

.req-search-select {
  width: 45%;
}

.req-search-input {
  flex: 1;
}

.req-count {
  font-size: 12px;
  color: #6b7280;
  margin-top: 6px;
  text-align: right;
}

.req-table-card {
  margin-top: 12px;
  border-radius: 12px;
}

/* Dialog */
.req-dialog >>> .el-dialog__header {
  background-color: #0f172a;
  color: #f9fafb;
  border-bottom: 1px solid #0f172a;
}
.req-dialog >>> .el-dialog__title {
  color: #f9fafb;
}
.req-dialog >>> .el-dialog__headerbtn .el-dialog__close {
  color: #e5e7eb;
}
.req-dialog-title {
  font-weight: 700;
}
.req-dialog-subtitle {
  font-weight: 400;
  font-size: 12px;
  color: #e5e7eb;
}
.req-dialog >>> .el-dialog {
  max-width: 1760px;
  margin-top: 3vh;
  margin-bottom: 3vh;
  border-radius: 12px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 6vh);
}
.req-dialog >>> .el-dialog__body {
  padding: 16px 20px;
  overflow-y: auto;
  flex: 1;
  min-height: 0;
}
.req-dialog-body {
  min-height: 0;
  max-height: none;
  overflow: visible;
}
.req-dialog-layout {
  margin-top: 0;
}
.req-dialog-layout > .el-col {
  min-width: 0;
}
/* Letra general del modal a 10.5px (los tamaños propios de cada
 * componente, si los fijan, se conservan). */
.req-dialog >>> .el-dialog__body {
  font-size: 10.5px;
}
.req-dialog >>> .el-table,
.req-dialog >>> .el-input__inner,
.req-dialog >>> .el-textarea__inner,
.req-dialog >>> .el-button,
.req-dialog >>> .el-tag,
.req-dialog >>> .el-tabs__item,
.req-dialog >>> .el-checkbox__label,
.req-dialog >>> .el-alert__title,
.req-dialog >>> .el-collapse-item__header,
.req-dialog >>> .el-form-item__label,
.req-dialog >>> .el-form-item__content {
  font-size: 10.5px;
}
@media (max-width: 768px) {
  .req-dialog >>> .el-dialog {
    width: 98% !important;
    max-width: none;
  }
  .req-dialog >>> .el-dialog__body {
    padding: 12px;
  }
}

/* Resumen */
.req-resumen {
  padding: 4px 0;
}
.req-resumen-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 4px;
}
.req-resumen-descr {
  font-size: 13px;
  color: #4b5563;
  margin-bottom: 8px;
}
.req-chip-row {
  margin-bottom: 6px;
}
.req-chip {
  border-radius: 999px;
  padding: 4px 10px;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  width: 100%;
  justify-content: space-between;
}
.req-chip-label {
  font-size: 12px;
  color: #6b7280;
}
.req-chip-value {
  font-size: 12px;
  font-weight: 500;
}

/* Tabs split */
.req-tab-split {
  display: flex;
  flex-direction: column;
}
@media (min-width: 900px) {
  .req-tab-split {
    flex-direction: row;
    gap: 10px;
  }
}
.req-tab-list {
  flex: 1;
  padding-right: 4px;
}
.req-tab-form {
  flex: 1;
  margin-top: 10px;
}
@media (min-width: 900px) {
  .req-tab-form {
    margin-top: 0;
  }
}
.req-tab-form-title {
  font-size: 13px;
  font-weight: 600;
  margin: 0 0 6px;
}
.req-tab-form-actions {
  text-align: right;
}

/* Interacciones cards */
.req-card-interaccion {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px;
  margin-bottom: 8px;
  font-size: 12px;
}
.req-card-interaccion-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
}
.req-card-interaccion-canal {
  font-weight: 600;
}
.req-card-interaccion-date {
  color: #6b7280;
}
.req-card-interaccion-prox {
  color: #6b7280;
  margin-bottom: 4px;
}
.req-card-interaccion-body {
  white-space: pre-wrap;
}
.req-card-interaccion-file a {
  font-size: 12px;
  color: #2563eb;
}
.req-card-interaccion-user {
  margin-top: 4px;
  color: #6b7280;
}

/* Cotizaciones */
.req-card-cotizacion {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px;
  margin-bottom: 8px;
  font-size: 12px;
}
.req-card-cotizacion-header {
  display: flex;
  justify-content: space-between;
  margin-bottom: 4px;
}
.req-card-cotizacion-version {
  font-weight: 600;
}
.req-card-cotizacion-date {
  color: #6b7280;
}
.req-cotizacion-obs {
  margin-top: 4px;
  margin-bottom: 4px;
}
.req-card-cotizacion-file a {
  font-size: 12px;
  color: #2563eb;
}
.req-card-cotizacion-user {
  margin-top: 4px;
  color: #6b7280;
}

/* Historial */
.req-historial {
  padding-top: 2px;
}
.req-hist-item {
  border-left: 2px solid #e5e7eb;
  padding-left: 8px;
  margin-bottom: 10px;
}
.req-hist-head {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
}
.req-hist-type {
  font-weight: 600;
}
.req-hist-date {
  color: #6b7280;
}
.req-hist-detail {
  font-size: 12px;
  margin-top: 2px;
}
.req-hist-meta {
  font-size: 11px;
  color: #6b7280;
  margin-top: 2px;
}
.req-hist-user {
  margin-left: 4px;
}

/* Sidebar */
.req-sidebar-card {
  border-radius: 10px;
}
.req-sidebar-card >>> .el-card__header {
  padding: 10px 15px;
  background: #f8fafc;
  border-bottom: 1px solid #e5e7eb;
}
.req-sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.req-sidebar-title {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}
.req-sidebar-section {
  margin-bottom: 12px;
}
.req-sidebar-label {
  font-size: 11px;
  color: #6b7280;
}
.req-sidebar-value {
  font-size: 13px;
  font-weight: 500;
}

/* Bitácora de cambios */
.bitacora-card {
  border-radius: 12px;
}
.bitacora-card >>> .el-card__header {
  padding: 12px 16px;
  background: #fff;
  border-bottom: 1px solid #ebeef5;
}
.bitacora-header {
  display: flex;
  align-items: center;
  gap: 8px;
}
.bitacora-header i {
  font-size: 18px;
  color: #e6a23c;
}
.bitacora-title {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
}
.bitacora-panel {
  max-height: 450px;
  overflow-y: auto;
  padding: 4px;
}
.bitacora-item {
  padding: 12px 0;
  border-bottom: 1px solid #f2f6fc;
}
.bitacora-item:last-child {
  border-bottom: none;
}
.bitacora-event {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}
.bitacora-tipo {
  font-size: 11px;
  color: #909399;
  background: #f4f4f5;
  padding: 2px 6px;
  border-radius: 3px;
}
.bitacora-user {
  font-size: 13px;
  color: #606266;
  margin-bottom: 4px;
}
.bitacora-user i {
  color: #909399;
  margin-right: 4px;
}
.bitacora-date {
  font-size: 12px;
  color: #909399;
}

/* Generic */
.req-empty {
  font-size: 12px;
  color: #6b7280;
  padding: 6px 0;
}

/* ✅ CORRECCIÓN: Forzar truncado en celdas de tabla */
.req-table-card >>> .el-table {
  table-layout: fixed;
}

.req-table-card >>> .el-table .cell {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  word-break: normal;
}

.req-table-card >>> .el-table td,
.req-table-card >>> .el-table th {
  overflow: hidden;
}

.req-table-card >>> .el-table--border td:first-child .cell,
.req-table-card >>> .el-table--border th:first-child .cell {
  padding-left: 10px;
}
</style>
