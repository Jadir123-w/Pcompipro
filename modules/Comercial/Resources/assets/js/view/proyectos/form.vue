<template>
  <el-dialog
    :visible.sync="dialogVisible"
    :title="isEdit ? `Proyecto: ${form.codigo}` : 'Nuevo Proyecto'"
    width="90%"
    top="2vh"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <!-- Header Stats (solo en edición) -->
    <div v-if="isEdit" class="stats-row mb-3">
      <div class="stat-box">
        <div class="stat-value">S/ {{ formatMonto(montoTotalProyecto) }}</div>
        <div class="stat-label">Monto Total</div>
      </div>
      <div class="stat-box">
        <div class="stat-value">{{ form.porcentaje_avance || 0 }}%</div>
        <div class="stat-label">Avance</div>
        <el-progress :percentage="Number(form.porcentaje_avance) || 0" :stroke-width="6" :show-text="false" />
      </div>
      <div class="stat-box">
        <div class="stat-value">{{ form.porcentaje_facturado || 0 }}%</div>
        <div class="stat-label">Facturado</div>
        <el-progress :percentage="Number(form.porcentaje_facturado) || 0" :stroke-width="6" :show-text="false" status="success" />
      </div>
      <div class="stat-box">
        <div class="stat-value">{{ form.porcentaje_cobrado || 0 }}%</div>
        <div class="stat-label">Cobrado</div>
        <el-progress :percentage="Number(form.porcentaje_cobrado) || 0" :stroke-width="6" :show-text="false" status="warning" />
      </div>
    </div>

    <!-- Tabs -->
    <el-tabs v-model="activeTab" type="card">
      <!-- Tab: Datos Generales -->
      <el-tab-pane label="Datos Generales" name="datos">
        <div class="tab-content-inner">
          <h4 class="section-title">Datos Generales del Proyecto</h4>
          
          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label>Código</label>
                <el-input v-model="form.codigo" disabled placeholder="Autogenerado" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label>Empresa <span class="required">*</span></label>
                <!-- Solo lectura en edición -->
                <el-input v-if="isEdit" v-model="form.empresa_razon_social" disabled />
                <el-select v-else v-model="form.empresa_id" filterable placeholder="Seleccionar" @change="onEmpresaChange">
                  <el-option v-for="e in tables.empresas" :key="e.id" :label="e.razon_social" :value="e.id" />
                </el-select>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label>Contacto</label>
                <!-- Solo lectura en edición -->
                <el-input v-if="isEdit" v-model="form.contacto_nombre" disabled placeholder="Sin contacto" />
                <el-select v-else v-model="form.contacto_id" filterable clearable placeholder="Seleccionar">
                  <el-option v-for="c in contactos" :key="c.id" :label="c.nombre" :value="c.id" />
                </el-select>
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="16">
              <div class="form-group">
                <!-- Agosto 2026 (punto 15): el equipo necesita que el nombre siga
                     un formato uniforme, para poder identificar y buscar proyectos. -->
                <label class="lbl-help" title="Ingrese el nombre con este formato: Proyecto N.°_Cliente_Cantidad y producto(s). Ej.: Proyecto 2_AUNA_250 bolsas_500 llaveros.">Nombre del Proyecto <span class="required">*</span> <i class="el-icon-info field-tip-icon"></i></label>
                <el-input v-model="form.nombre" placeholder="Ej.: Proyecto 2_AUNA_250 bolsas_500 llaveros" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <!-- Agosto 2026 (punto 16): antes mostraba el código del
                     requerimiento. Ahora muestra el código MAP de la cotización que
                     el cliente aprobó, que es la que dio origen al proyecto, con un
                     botón para abrir su PDF.
                     Prioriza la aprobada por el cliente; si el proyecto se creó sin
                     pasar por ese estado, cae a la más avanzada que no esté
                     rechazada. Vacío solo si el requerimiento no tiene ninguna. -->
                <label class="lbl-help" title="Código MAP de la cotización que el cliente aprobó y dio origen a este proyecto.">Cotización de Origen <i class="el-icon-info field-tip-icon"></i></label>
                <el-input v-model="form.cotizacion_origen_codigo" disabled placeholder="Sin cotización aprobada">
                  <el-button slot="append" icon="el-icon-document"
                             :disabled="!form.cotizacion_origen_pdf_url"
                             @click="verPdfCotizacionOrigen"
                             :title="form.cotizacion_origen_pdf_url ? 'Ver el PDF de la cotización' : 'La cotización aprobada no tiene PDF generado'">
                    Ver PDF
                  </el-button>
                </el-input>
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <!-- Agosto 2026 (punto 17): texto libre, porque el coordinador puede
                     ser alguien que no tiene usuario en el sistema. -->
                <label class="lbl-help" title="Persona a cargo de coordinar y hacer seguimiento de este proyecto.">Coordinador del Proyecto <i class="el-icon-info field-tip-icon"></i></label>
                <el-input v-model="form.coordinador" placeholder="Nombre del coordinador" />
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="24">
              <div class="form-group">
                <label>Descripción</label>
                <el-input type="textarea" v-model="form.descripcion" :autosize="{minRows: 2, maxRows: 6}" placeholder="Descripción del proyecto" />
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Categoría del proyecto según el catálogo de Compipro.">Categoría <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.tipo" clearable placeholder="Seleccionar">
                  <el-option v-for="t in tables.tipos_proyecto" :key="t" :label="t" :value="t" />
                </el-select>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Condición o prioridad del proyecto (estándar, importante, urgente).">Tipo condición <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.prioridad" placeholder="Seleccionar">
                  <el-option v-for="p in tables.prioridades" :key="p" :label="p" :value="p" />
                </el-select>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label>Ejecutivo</label>
                <el-select v-model="form.ejecutivo_id" filterable clearable placeholder="Seleccionar">
                  <el-option v-for="e in tables.ejecutivos" :key="e.id" :label="e.name" :value="e.id" />
                </el-select>
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Gastos reales del proyecto, calculados desde la pestaña Productos (proveedores + costos operativos + IGV).">Gastos del proyecto (S/) <i class="el-icon-info field-tip-icon"></i></label>
                <el-input :value="'S/ ' + formatMonto(montoTotalProyecto)" disabled />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Monto de la cotización aprobada SIN IGV (es el Subtotal de la pre-cotización). Ojo: el cuadre de facturación por razón social se sigue haciendo contra el monto CON IGV, que es lo que realmente se factura.">Monto Real de cotización sin IGV (S/) <i class="el-icon-info field-tip-icon"></i></label>
                <el-input v-if="form.requerimiento_id"
                  :value="montoSinIgvPre !== null ? 'S/ ' + formatMonto(montoSinIgvPre) : 'Sin cotización'" disabled />
                <el-input-number v-else v-model="form.monto_costo" :precision="2" :min="0" :controls="false" style="width: 100%" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Margen Real de la pre-cotización del requerimiento origen (el mismo que se ve en la pestaña Pre-Cotización). En proyectos sin requerimiento, se calcula entre el monto y el costo.">Margen Real de Cotización (%) <i class="el-icon-info field-tip-icon"></i></label>
                <el-input :value="margenMostrado" disabled />
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label>Fecha Inicio</label>
                <el-date-picker v-model="form.fecha_inicio" type="date" format="dd/MM/yyyy" value-format="yyyy-MM-dd" style="width: 100%" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label>Fecha Entrega</label>
                <el-date-picker v-model="form.fecha_fin" type="date" format="dd/MM/yyyy" value-format="yyyy-MM-dd" style="width: 100%" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Indicador visual del estado de avance del proyecto. Lo gestiona Operaciones.">Semáforo (Solo Operaciones) <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.semaforo" placeholder="Seleccionar">
                  <el-option label="🟢 Verde" value="Verde" />
                  <el-option label="🟡 Amarillo" value="Amarillo" />
                  <el-option label="🔴 Rojo" value="Rojo" />
                </el-select>
              </div>
            </el-col>
          </el-row>

          <!-- Subsección: O/C -->
          <h5 class="subsection-title mt-4">Orden de Compra</h5>
          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Número de la orden de compra emitida por el cliente.">Número O/C <i class="el-icon-info field-tip-icon"></i></label>
                <el-input v-model="form.oc_numero" placeholder="OC-XXXX" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Fecha de la orden de compra del cliente.">Fecha O/C <i class="el-icon-info field-tip-icon"></i></label>
                <el-date-picker v-model="form.oc_fecha" type="date" format="dd/MM/yyyy" value-format="yyyy-MM-dd" style="width: 100%" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Etapa actual del proyecto dentro de su ciclo.">Etapa <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.etapa" placeholder="Seleccionar">
                  <el-option v-for="e in tables.etapas" :key="e" :label="e" :value="e" />
                </el-select>
              </div>
            </el-col>
          </el-row>
        </div>
      </el-tab-pane>

      <!-- Tab: Criterios -->
      <el-tab-pane label="Criterios" name="criterios">
        <div class="tab-content-inner">
          <h4 class="section-title">Criterios a Considerar</h4>
          
          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Plazo total estimado de ejecución del proyecto.">Tiempo del Proyecto <i class="el-icon-info field-tip-icon"></i></label>
                <el-input v-model="form.tiempo_proyecto" placeholder="Ej: 12 a 15 días útiles" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Define si aplica un cargo adicional por plazos de entrega muy cortos.">Política Tiempos Cortos <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.politica_tiempos_cortos" clearable placeholder="Seleccionar">
                  <el-option label="No Aplica" value="No Aplica" />
                  <el-option label="Si Aplica" value="Si Aplica" />
                </el-select>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Indica si el cliente ya había comprado este producto antes.">¿Es Recompra? <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.es_recompra" clearable placeholder="Seleccionar">
                  <el-option label="No" value="No" />
                  <el-option label="Si" value="Si" />
                </el-select>
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Clasificación del cliente por su frecuencia de compra.">Tipo Cliente (Frecuencia) <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.tipo_cliente_1" clearable placeholder="Seleccionar">
                  <el-option label="Nuevo" value="Nuevo" />
                  <el-option label="Ocasional" value="Ocasional" />
                  <el-option label="Frecuente" value="Frecuente" />
                  <el-option label="Recurrente" value="Recurrente" />
                </el-select>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Indica si el cliente es consumidor final o intermediario.">Tipo Cliente (Relación) <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.tipo_cliente_2" clearable placeholder="Seleccionar">
                  <el-option label="Final" value="Final" />
                  <el-option label="Intermediario" value="Intermediario" />
                </el-select>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <!-- Agosto 2026 (punto 18): además de la Orden de Compra se contempla
                     la Orden de Servicio, y se distingue si es interna o formal. -->
                <label class="lbl-help" title="Indica si el cliente ya entregó la orden de compra o de servicio, y si es interna o formal.">¿Tiene O/C o OS? <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.tiene_oc" clearable placeholder="Seleccionar">
                  <el-option v-for="o in opcionesOrdenCompra" :key="o" :label="o" :value="o" />
                </el-select>
              </div>
            </el-col>
          </el-row>
        </div>
      </el-tab-pane>

      <!-- Tab: Facturación -->
      <el-tab-pane label="Facturación" name="facturacion">
        <div class="tab-content-inner">
          <h4 class="section-title">Información de Facturación</h4>
          
          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Indica si el servicio del proyecto se factura o no aplica.">Servicio Facturado <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.servicio_facturado" clearable placeholder="Seleccionar">
                  <el-option label="Sí aplica" value="Sí aplica" />
                  <el-option label="No aplica" value="No aplica" />
                </el-select>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Número de empresas (RUC) a las que se emitirá factura por este proyecto.">Cantidad Razones Sociales <i class="el-icon-info field-tip-icon"></i></label>
                <el-input-number v-model="form.cantidad_razones_sociales" :min="1" :max="10" style="width: 100%" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label>Forma de Pago</label>
                <el-select v-model="form.forma_pago" filterable clearable
                           placeholder="Seleccionar"
                           @change="onFormaPagoChange">
                  <el-option v-for="f in tables.formas_pago" :key="f" :label="f" :value="f" />
                </el-select>
              </div>
            </el-col>
          </el-row>

          <!-- Fila condicional: Días de crédito (solo para formas mixtas adelanto + crédito) -->
          <el-row :gutter="20" v-if="requiereDiasCredito">
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Plazo de crédito, en días, para el pago de la factura.">
                  Días de Crédito <span class="text-danger">*</span> <i class="el-icon-info field-tip-icon"></i>
                </label>
                <el-select v-model="form.dias_credito"
                           placeholder="Seleccionar días"
                           style="width: 100%">
                  <el-option v-for="d in (tables.dias_credito_opciones || [5,7,9,15])"
                             :key="d" :label="'Crédito ' + d + ' días'" :value="d" />
                </el-select>
                <small class="field-hint">
                  Obligatorio cuando la forma de pago es mixta (adelanto + crédito).
                </small>
              </div>
            </el-col>
            <el-col :span="16">
              <div class="form-group">
                <label>Observaciones de pago</label>
                <el-input v-model="form.metodo_politica_pago"
                          placeholder="Ej: Pagos programados entre miércoles y viernes"
                          maxlength="255" />
              </div>
            </el-col>
          </el-row>

          <!-- Observaciones de pago siempre visibles (cuando NO es 50/50 mixto) -->
          <el-row :gutter="20" v-else-if="form.forma_pago">
            <el-col :span="24">
              <div class="form-group">
                <label>Observaciones de pago</label>
                <el-input v-model="form.metodo_politica_pago"
                          placeholder="Notas sobre la política o plazos de pago"
                          maxlength="255" />
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="24">
              <div class="checkbox-group">
                <el-checkbox v-model="form.aplica_detraccion" title="Marca si la operación está sujeta a detracción del IGV.">Aplica Detracción</el-checkbox>
                <el-checkbox v-model="form.aplica_retencion" title="Marca si el cliente es agente de retención del IGV.">Aplica Retención</el-checkbox>
                <el-checkbox v-model="form.pago_directo" title="Marca si el pago se realiza directamente, sin intermediarios.">Pago Directo</el-checkbox>
                <el-checkbox v-model="form.requiere_formato_especial" title="Marca si el cliente exige un formato de factura especial.">Formato Especial</el-checkbox>
                <el-checkbox v-model="form.cada_rs_con_oc" title="Marca si cada razón social requiere su propia orden de compra.">Cada R.S. con O/C</el-checkbox>
              </div>
            </el-col>
          </el-row>

          <!-- Subsección: Contactos de Facturación (seleccionables de los contactos de la empresa) -->
          <h5 class="subsection-title mt-4">Contactos de Facturación</h5>
          <div class="mb-2">
            <el-button size="small" type="primary" icon="el-icon-plus"
                       :disabled="!form.empresa_id"
                       @click="openContactosFacturacionPicker">
              Agregar contacto de facturación
            </el-button>
            <small v-if="!form.empresa_id" class="field-hint" style="margin-left: 10px">
              Primero selecciona una empresa.
            </small>
          </div>
          <el-table :data="form.contactos_facturacion" size="small" border empty-text="Sin contactos">
            <el-table-column prop="nombre" label="Nombre" min-width="150" />
            <el-table-column prop="cargo" label="Cargo" width="140" />
            <el-table-column prop="telefono" label="Teléfono" width="130" />
            <el-table-column prop="correo" label="Correo" min-width="180" />
            <el-table-column width="60" align="center" label="Quitar">
              <template slot-scope="scope">
                <el-button type="danger" icon="el-icon-delete" size="mini" circle
                           @click="removeContactoFacturacion(scope.$index)" />
              </template>
            </el-table-column>
          </el-table>

          <!-- Modal selector de contactos de la empresa -->
          <el-dialog :visible.sync="picker.visible" title="Seleccionar contactos de facturación"
                     width="720px" append-to-body :close-on-click-modal="false">
            <el-input v-model="picker.search" placeholder="Buscar por nombre, cargo o correo"
                      prefix-icon="el-icon-search" size="small" clearable style="margin-bottom: 10px" />
            <el-table :data="pickerFiltered" size="small" border height="350"
                      @selection-change="picker.selected = $event"
                      v-loading="picker.loading"
                      empty-text="La empresa no tiene contactos registrados">
              <el-table-column type="selection" width="45" />
              <el-table-column prop="nombre" label="Nombre" min-width="140" />
              <el-table-column prop="cargo" label="Cargo" width="130" />
              <el-table-column prop="telefono" label="Teléfono" width="120" />
              <el-table-column prop="correo" label="Correo" min-width="180" />
            </el-table>
            <small class="field-hint">
              Marca 1 o más contactos. Los que ya estén agregados no se duplicarán.
            </small>
            <span slot="footer">
              <el-button size="small" @click="picker.visible = false">Cancelar</el-button>
              <el-button type="primary" size="small"
                         :disabled="picker.selected.length === 0"
                         @click="confirmContactosFacturacion">
                Agregar {{ picker.selected.length || '' }}
              </el-button>
            </span>
          </el-dialog>

          <el-row :gutter="20" class="mt-3">
            <el-col :span="8">
              <div class="form-group">
                <label>Correo Principal Factura</label>
                <el-input v-model="form.correo_factura" placeholder="facturacion@empresa.com" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Indica si el cliente requiere la factura impresa en físico.">Factura Física <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.entrega_factura_fisica" clearable placeholder="Seleccionar">
                  <el-option label="No Aplica" value="No Aplica" />
                  <el-option label="Si Aplica" value="Si Aplica" />
                </el-select>
              </div>
            </el-col>
            <el-col :span="8" v-if="form.entrega_factura_fisica === 'Si Aplica'">
              <div class="form-group">
                <label>Dirección Entrega Factura</label>
                <el-input v-model="form.direccion_entrega_factura" placeholder="Dirección completa" />
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="24">
              <div class="form-group">
                <label>Comentarios</label>
                <el-input type="textarea" v-model="form.comentario_facturacion" :autosize="{minRows: 2, maxRows: 6}" placeholder="Notas adicionales..." />
              </div>
            </el-col>
          </el-row>

          <!-- Subsección: Razones Sociales -->
          <h5 class="subsection-title mt-4">Razones Sociales</h5>
          <el-table :data="form.razones_sociales" size="small" border empty-text="Sin razones sociales">
            <el-table-column label="Razón Social" min-width="240">
              <template slot-scope="scope">
                <el-select v-model="scope.row.empresa_busqueda"
                           filterable remote
                           :remote-method="(q) => searchEmpresasRS(q, scope.$index)"
                           :loading="scope.row._loading"
                           size="small"
                           placeholder="Buscar empresa por razón social o RUC"
                           style="width: 100%"
                           @change="(val) => onRazonSocialChange(scope.$index, val)">
                  <el-option v-for="e in (scope.row._options || [])"
                             :key="e.id"
                             :label="e.razon_social + ' (' + e.ruc + ')'"
                             :value="e.id" />
                </el-select>
                <small v-if="scope.row.razon_social" class="field-hint" style="font-size: 11px">
                  {{ scope.row.razon_social }}
                </small>
              </template>
            </el-table-column>
            <el-table-column prop="ruc" label="RUC" width="110" />
            <el-table-column label="Monto" width="120">
              <template slot-scope="scope">
                <el-input-number v-model="scope.row.monto_facturar" size="small"
                                 :precision="2" :controls="false" style="width: 100%" />
              </template>
            </el-table-column>
            <el-table-column label="Se tiene OC" width="130">
              <template slot-scope="scope">
                <el-select v-model="scope.row.tiene_oc" size="small" style="width: 100%">
                  <el-option label="Pendiente" value="Pendiente" />
                  <el-option label="No Aplica" value="No Aplica" />
                  <el-option label="Se tiene" value="Se tiene" />
                </el-select>
              </template>
            </el-table-column>
            <el-table-column label="N° O/C" width="120">
              <template slot-scope="scope">
                <el-input v-model="scope.row.oc_numero" size="small"
                          :disabled="scope.row.tiene_oc !== 'Se tiene'" />
              </template>
            </el-table-column>
            <el-table-column label="PDF OC" width="140" align="center">
              <template slot-scope="scope">
                <!-- Archivo subido: mostrar Ver / Quitar -->
                <div v-if="scope.row.oc_archivo">
                  <el-button size="mini" type="text" @click="downloadOcPdf(scope.row)" title="Descargar">
                    <i class="el-icon-view"></i> Ver
                  </el-button>
                  <el-button size="mini" type="text" class="text-danger"
                             @click="deleteOcPdf(scope.row, scope.$index)" title="Quitar archivo">
                    <i class="el-icon-delete"></i>
                  </el-button>
                </div>
                <!-- Sin archivo + tiene_oc = Se tiene -> botón Subir -->
                <el-upload
                  v-else-if="scope.row.tiene_oc === 'Se tiene' && scope.row.id"
                  :show-file-list="false"
                  accept="application/pdf"
                  :before-upload="(file) => beforeUploadOc(file, scope.row, scope.$index)"
                  :http-request="() => {}"
                  action="#">
                  <el-button size="mini" type="primary" plain icon="el-icon-upload">Subir</el-button>
                </el-upload>
                <small v-else-if="scope.row.tiene_oc === 'Se tiene' && !scope.row.id"
                       class="field-hint" style="font-size: 10px">
                  Guarda el proyecto primero
                </small>
                <span v-else style="color:#909399">—</span>
              </template>
            </el-table-column>
            <el-table-column width="60" align="center" label="Quitar">
              <template slot-scope="scope">
                <el-button type="danger" icon="el-icon-delete" size="mini" circle
                           @click="removeRazonSocial(scope.$index)" />
              </template>
            </el-table-column>
          </el-table>
          <el-button size="small" class="mt-2" @click="addRazonSocial"
                     :disabled="form.razones_sociales.length >= (form.cantidad_razones_sociales || 1)">+ Agregar razón social</el-button>
          <!-- P7: control de cuadre de los montos por razón social vs Monto Real Cotización -->
          <div v-if="form.razones_sociales.length" class="mt-2" style="font-size: 13px;">
            <span :style="{ color: montosRSCuadran ? '#67C23A' : '#F56C6C', 'font-weight': 600 }">
              <i :class="montosRSCuadran ? 'el-icon-success' : 'el-icon-warning'"></i>
              Suma a facturar: S/ {{ formatMonto(sumaMontosRS) }} &nbsp;/&nbsp; Monto Real Cotización: S/ {{ formatMonto(montoRealEfectivo) }}
            </span>
          </div>
        </div>
      </el-tab-pane>

      <!-- Tab: Muestras -->
      <el-tab-pane label="Muestras" name="muestras">
        <div class="tab-content-inner">
          <h4 class="section-title">Aprobación de Muestras</h4>

          <el-row :gutter="20">
            <el-col :span="24">
              <div class="checkbox-group">
                <el-checkbox v-model="form.muestra_virtual">Muestra Virtual</el-checkbox>
                <el-checkbox v-model="form.muestra_fisica">Muestra Física</el-checkbox>
                <el-checkbox v-model="form.prueba_color">Prueba de Color</el-checkbox>
              </div>
            </el-col>
          </el-row>

          <h5 class="subsection-title mt-4">Aprobación de Arte</h5>
          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Fecha en que el cliente aprobó el arte.">Fecha Aprobación <i class="el-icon-info field-tip-icon"></i></label>
                <el-date-picker v-model="form.arte_aprobado_at" type="datetime" format="dd/MM/yyyy HH:mm" value-format="yyyy-MM-dd HH:mm:ss" style="width: 100%" />
              </div>
            </el-col>
          </el-row>
        </div>
      </el-tab-pane>

      <!-- Tab: Productos -->
      <el-tab-pane label="Productos" name="productos">
        <div class="tab-content-inner">
          <h4 class="section-title">Productos del Proyecto</h4>

          <el-alert type="info" :closable="false" show-icon style="margin-bottom:10px;">
            <template v-if="productosBloqueados">
              Los datos del producto (descripción, cantidad) provienen de la cotización aprobada y no se editan.
              Sí puedes gestionar sus <strong>proveedores</strong>, <strong>costos operativos</strong> y <strong>observaciones</strong> (despliega cada producto con la flecha de la izquierda). Al cambiar un proveedor, el anterior queda visible como historial.
            </template>
            <template v-else>
              Cada producto puede tener uno o varios proveedores. Despliega cada producto (flecha de la izquierda) para gestionar sus proveedores, costos y observaciones. Al cambiar un proveedor, el anterior queda visible como historial.
            </template>
          </el-alert>

          <el-table :data="form.productos" class="productos-tabla" size="small" border>
            <el-table-column type="expand">
              <template slot-scope="props">
                <div class="producto-specs">
                  <div class="producto-specs__title">Detalle técnico (de la cotización · solo lectura)</div>
                  <!-- COM-02 · Código con el que se cotizó el producto. Lo resuelve el
                       backend contra la pre-cotización (manual de la cotización o, si no
                       tiene, el del catálogo). -->
                  <el-row :gutter="16">
                    <el-col :span="8"><span class="spec-label">Cód. Artíc.:</span> {{ props.row.codigo_articulo || '—' }}</el-col>
                  </el-row>
                  <el-row :gutter="16" style="margin-top:6px;">
                    <el-col :span="8"><span class="spec-label">Material:</span> {{ props.row.material || '—' }}</el-col>
                    <el-col :span="8"><span class="spec-label">Medida:</span> {{ props.row.medida || '—' }}</el-col>
                    <el-col :span="8"><span class="spec-label">Técnica:</span> {{ props.row.tecnica_impresion || '—' }}</el-col>
                  </el-row>
                  <el-row :gutter="16" style="margin-top:6px;">
                    <el-col :span="8"><span class="spec-label">N° Colores:</span> {{ props.row.cantidad_colores || '—' }}</el-col>
                    <el-col :span="8"><span class="spec-label">N° Lados:</span> {{ props.row.cantidad_lados || '—' }}</el-col>
                    <el-col :span="8"><span class="spec-label">Empaque:</span> {{ props.row.empaque || '—' }}</el-col>
                  </el-row>
                  <el-row :gutter="16" style="margin-top:6px;">
                    <el-col :span="24"><span class="spec-label">Personalización:</span> {{ props.row.personalizacion || '—' }}</el-col>
                  </el-row>

                  <!-- Costos operativos del producto (alimentan el resumen del proyecto) -->
                  <div class="producto-specs__title" style="margin-top:14px;">Costos operativos del producto (S/)</div>
                  <el-row :gutter="16">
                    <el-col :span="8">
                      <span class="spec-label">Empaque:</span>
                      <el-input-number v-model="props.row.costo_empaque" size="mini" :precision="2" :min="0" :controls="false" :disabled="productosBloqueados" style="width:110px" @change="recalcProducto(props.row)" />
                    </el-col>
                    <el-col :span="8">
                      <span class="spec-label">Movilidad:</span>
                      <el-input-number v-model="props.row.costo_movilidad" size="mini" :precision="2" :min="0" :controls="false" :disabled="productosBloqueados" style="width:110px" @change="recalcProducto(props.row)" />
                    </el-col>
                    <el-col :span="8">
                      <span class="spec-label">SCTR:</span>
                      <el-input-number v-model="props.row.costo_sctr" size="mini" :precision="2" :min="0" :controls="false" :disabled="productosBloqueados" style="width:110px" @change="recalcProducto(props.row)" />
                    </el-col>
                  </el-row>

                  <!-- Proveedores y costos del producto (M2) -->
                  <div class="proveedores-head">
                    <div>
                      <div class="producto-specs__title" style="margin:0;">Proveedores y costos del producto</div>
                      <div class="proveedores-hint">El concepto describe qué producto o servicio realiza cada proveedor. Al cambiar un proveedor, el anterior queda como historial (atenuado).</div>
                    </div>
                    <el-button type="primary" size="mini" icon="el-icon-plus" @click="addProveedor(props.row)">Agregar proveedor</el-button>
                  </div>

                  <el-table :data="props.row.proveedores" size="mini" border class="proveedores-tabla" :row-class-name="proveedorRowClass">
                    <el-table-column label="Proveedor" min-width="190">
                      <template slot-scope="s">
                        <el-select v-if="s.row.activo" v-model="s.row.supplier_id" size="mini" filterable remote clearable
                                   :remote-method="(q) => searchProveedoresProd(q, s.row)"
                                   :loading="s.row._loading"
                                   placeholder="Buscar proveedor"
                                   style="width:100%"
                                   @change="(val) => onProveedorProdChange(s.row, val)">
                          <el-option v-for="op in (s.row._options || [])" :key="op.id" :label="op.razon_social" :value="op.id">
                            <span>{{ op.razon_social }}</span>
                            <span v-if="op.ruc" style="color:#8492a6;font-size:12px;"> · {{ op.ruc }}</span>
                          </el-option>
                        </el-select>
                        <span v-else class="prov-historial">{{ s.row.supplier_name || '—' }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column label="Concepto / servicio" min-width="190">
                      <template slot-scope="s">
                        <el-input v-if="s.row.activo" v-model="s.row.concepto" size="mini" type="textarea" :autosize="{minRows:1,maxRows:3}" placeholder="Ej.: Impresión serigráfica de un logo a un color" />
                        <span v-else class="prov-historial">{{ s.row.concepto || '—' }}</span>
                      </template>
                    </el-table-column>
                    <!-- Agosto 2026 (punto 20): tiempo que tarda el proveedor en
                         entregarle el producto a COMPIPRO. Arranca heredando el de
                         la Pre-Cotización y se ajusta cuando el proveedor confirma
                         un plazo distinto.
                         NO confundir con el plazo que se le promete al CLIENTE: ese
                         es otro y vive en el Preview de la cotización (punto 9). -->
                    <el-table-column width="150">
                      <template slot="header">
                        <span title="Tiempo que tarda el proveedor en entregar el producto a COMPIPRO. Arranca heredando el de la Pre-Cotización y se puede ajustar. Editarlo aquí no modifica la Pre-Cotización.">Tiempo de Producción</span>
                      </template>
                      <template slot-scope="s">
                        <el-input v-if="s.row.activo" v-model="s.row.tiempo_produccion" size="mini"
                                  placeholder="Ej. 19 días útiles" />
                        <span v-else class="prov-historial">{{ s.row.tiempo_produccion || '—' }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column label="Cantidad" width="88" align="center">
                      <template slot-scope="s">
                        <el-input-number v-if="s.row.activo" v-model="s.row.cantidad" size="mini" :min="0" :controls="false" style="width:78px" @change="recalcProducto(props.row)" />
                        <span v-else class="prov-historial">{{ s.row.cantidad }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column label="P. unit. informado" width="120" align="right">
                      <template slot-scope="s">
                        <el-input-number v-if="s.row.activo" v-model="s.row.precio_unitario_informado" size="mini" :precision="2" :min="0" :controls="false" style="width:108px" @change="recalcProducto(props.row)" />
                        <span v-else class="prov-historial">S/ {{ formatMonto(s.row.precio_unitario_informado) }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column label="Condición IGV" width="135">
                      <template slot-scope="s">
                        <el-select v-if="s.row.activo" v-model="s.row.condicion_igv" size="mini" style="width:100%" @change="recalcProducto(props.row)">
                          <el-option label="Incluye IGV" value="included" />
                          <el-option label="No incluye IGV" value="excluded" />
                          <el-option label="No aplica IGV" value="exempt" />
                          <el-option label="Por confirmar" value="pending" />
                        </el-select>
                        <span v-else class="prov-historial">{{ condicionIgvLabel(s.row.condicion_igv) }}</span>
                      </template>
                    </el-table-column>
                    <el-table-column label="Subtotal s/IGV" width="110" align="right">
                      <template slot-scope="s">
                        S/ {{ formatMonto(montosProveedor(s.row).subtotalNeto) }}
                      </template>
                    </el-table-column>
                    <el-table-column label="Total a pagar" width="115" align="right">
                      <template slot-scope="s">
                        <span v-if="s.row.condicion_igv === 'pending'" class="prov-pending">Por confirmar</span>
                        <strong v-else>S/ {{ formatMonto(montosProveedor(s.row).total) }}</strong>
                      </template>
                    </el-table-column>
                    <el-table-column width="120" align="center">
                      <template slot-scope="s">
                        <template v-if="s.row.activo">
                          <el-button size="mini" type="text" title="Cambiar proveedor (conserva el anterior como historial)" @click="cambiarProveedor(props.row, s.row, s.$index)">Cambiar</el-button>
                          <el-button size="mini" type="text" icon="el-icon-close" title="Quitar proveedor" @click="removeProveedor(props.row, s.$index)"></el-button>
                        </template>
                        <el-tag v-else size="mini" type="info">historial</el-tag>
                      </template>
                    </el-table-column>
                  </el-table>

                  <div class="producto-totales">
                    <span>Costo unitario acumulado: <strong>S/ {{ formatMonto(props.row.precio_unitario) }}</strong></span>
                    <span>Costo total del producto: <strong>S/ {{ formatMonto(props.row.subtotal) }}</strong></span>
                  </div>

                  <!-- Agosto 2026 (punto 19): el campo llega con una plantilla
                       precargada cuando está vacío, para trasladar de forma ordenada
                       lo que dijo el cliente a quienes ejecutan el proyecto.
                       Los títulos se muestran en negrita y cada uno con su propio
                       espacio para escribir, como pide el documento del cliente; el
                       texto sigue guardándose igual que siempre. -->
                  <div class="producto-specs__title" style="margin-top:14px;">Observaciones / Preocupaciones del Cliente</div>
                  <el-row :gutter="16">
                    <el-col :span="24">
                      <observaciones-producto v-model="props.row.observaciones"></observaciones-producto>
                      <div class="obs-ayuda">
                        Escribe debajo de cada título. Los títulos son fijos y lo que escribas
                        queda siempre en texto normal.
                      </div>
                    </el-col>
                  </el-row>

                  <!-- Imagen del producto -->
                  <div class="producto-specs__title" style="margin-top:14px;">Imagen del producto</div>
                  <template v-if="props.row.id">
                    <div class="producto-imagen">
                      <div v-if="props.row.imagen" class="producto-imagen__thumb-wrap">
                        <img
                          class="producto-imagen__thumb"
                          :src="'/comercial/proyecto-productos/' + props.row.id + '/imagen?v=' + imgVersion"
                          alt="Imagen del producto"
                        />
                      </div>
                      <div v-else class="producto-imagen__empty">Sin imagen</div>
                      <div class="producto-imagen__actions">
                        <el-button
                          size="mini"
                          icon="el-icon-upload2"
                          :loading="uploadingProductoImageId === props.row.id"
                          @click="triggerProductoImageUpload(props.row)"
                        >{{ props.row.imagen ? 'Cambiar' : 'Subir' }}</el-button>
                        <el-button
                          v-if="props.row.imagen"
                          size="mini"
                          type="danger"
                          plain
                          icon="el-icon-delete"
                          @click="deleteProductoImagen(props.row)"
                        >Quitar</el-button>
                      </div>
                    </div>
                    <div class="producto-imagen__hint">Formatos: JPG, PNG, WEBP o GIF. Máximo 5 MB.</div>
                  </template>
                  <div v-else class="producto-imagen__note">
                    Guarda el proyecto para gestionar la imagen.
                  </div>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="descripcion" label="Descripción" min-width="220">
              <template slot-scope="scope">
                <el-input v-model="scope.row.descripcion" size="small" placeholder="Descripción del producto" :disabled="productosBloqueados" />
              </template>
            </el-table-column>
            <el-table-column label="Proveedores" width="105" align="center">
              <template slot-scope="scope">
                <el-tag size="mini" type="success" effect="plain">{{ contarProveedoresActivos(scope.row) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="cantidad" label="Cantidad" width="95" align="center">
              <template slot-scope="scope">
                <el-input-number v-model="scope.row.cantidad" size="small" :min="1" :controls="false" :disabled="productosBloqueados" />
              </template>
            </el-table-column>
            <el-table-column prop="muestras" label="Muestras" width="90" align="center">
              <template slot-scope="scope">
                <el-input-number v-model="scope.row.muestras" size="small" :min="0" :step="1" :precision="0" :controls="false" :disabled="productosBloqueados" />
              </template>
            </el-table-column>
            <el-table-column label="Costo unit. acum." width="125" align="right">
              <template slot-scope="scope">
                S/ {{ formatMonto(scope.row.precio_unitario) }}
              </template>
            </el-table-column>
            <el-table-column label="Costo total" width="125" align="right">
              <template slot-scope="scope">
                <strong>S/ {{ formatMonto(scope.row.subtotal) }}</strong>
              </template>
            </el-table-column>
            <el-table-column v-if="!productosBloqueados" width="55" align="center">
              <template slot-scope="scope">
                <el-button type="danger" icon="el-icon-delete" size="mini" circle @click="removeProducto(scope.$index)" />
              </template>
            </el-table-column>
          </el-table>
          <el-button v-if="!productosBloqueados" size="small" class="mt-2" @click="addProducto">+ Agregar Producto</el-button>

          <!-- Input de archivo compartido para subir/cambiar la imagen de un producto -->
          <input
            ref="productoImageInput"
            type="file"
            accept="image/jpeg,image/png,image/webp,image/gif"
            style="display:none"
            @change="onProductoImageSelected"
          />

          <!-- Resumen de costos del proyecto (M4) -->
          <div class="resumen-proyecto">
            <div class="resumen-proyecto__title">Resumen de costos del proyecto</div>
            <div class="resumen-proyecto__line"><span>Costo total de empaque (S/)</span><strong>S/ {{ formatMonto(resumenProyecto.empaque) }}</strong></div>
            <div class="resumen-proyecto__line"><span>Costo total de SCTR (S/)</span><strong>S/ {{ formatMonto(resumenProyecto.sctr) }}</strong></div>
            <div class="resumen-proyecto__line"><span>Costo total de movilidad (S/)</span><strong>S/ {{ formatMonto(resumenProyecto.movilidad) }}</strong></div>
            <div class="resumen-proyecto__line"><span>Subtotal del proyecto</span><strong>S/ {{ formatMonto(resumenProyecto.subtotal) }}</strong></div>
            <div class="resumen-proyecto__line"><span>IGV total del proyecto</span><strong>S/ {{ formatMonto(resumenProyecto.igv) }}</strong></div>
            <div class="resumen-proyecto__line resumen-proyecto__total"><span>Costo total del proyecto</span><strong>S/ {{ formatMonto(resumenProyecto.total) }}</strong></div>
          </div>
        </div>
      </el-tab-pane>

      <!-- Tab: Logística -->
      <el-tab-pane label="Logística" name="logistica">
        <div class="tab-content-inner">
          <h4 class="section-title">Logística y Embalaje</h4>
          
          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label>Tipo de Embalaje</label>
                <el-select v-model="form.tipo_embalaje" clearable placeholder="Seleccionar">
                  <el-option label="Embalaje propuesta compina (sugerido)" value="Embalaje propuesta compina (sugerido)" />
                  <el-option label="Embalaje tercerista" value="Embalaje tercerista" />
                  <el-option label="Embalaje especial" value="Embalaje especial" />
                  <el-option label="Embalaje muy especial" value="Embalaje muy especial" />
                  <el-option label="Embalaje para cliente final" value="Embalaje para cliente final" />
                </el-select>
              </div>
            </el-col>
            <el-col :span="16">
              <div class="form-group">
                <label class="lbl-help" title="Descripción del embalaje agrupado de varios productos.">Embalaje Grupal <i class="el-icon-info field-tip-icon"></i></label>
                <el-input v-model="form.embalaje_grupal" placeholder="Ej: Embalaje propuesta Compipro" />
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="24">
              <div class="checkbox-group">
                <el-checkbox v-model="form.rotulo_especial">Rótulo Especial</el-checkbox>
                <el-checkbox v-model="form.sticker_compipro">Sticker Compipro</el-checkbox>
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20" class="mt-3" v-if="form.sticker_compipro">
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Cantidad de stickers (adhesivos) Compipro para este pedido.">Cantidad Sticker (adhesivo) Compipro <i class="el-icon-info field-tip-icon"></i></label>
                <el-input v-model="form.cantidad_sticker" placeholder="Ej: 55, media plancha, personalizado..." maxlength="100" />
              </div>
            </el-col>
          </el-row>
        </div>
      </el-tab-pane>

      <!-- Tab: Despacho -->
      <el-tab-pane label="Despacho" name="despacho">
        <div class="tab-content-inner">
          <h4 class="section-title">Información de Despacho</h4>
          
          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label>Responsable de recepción de mercancía (Cliente)</label>
                <el-select v-model="form.contacto_despacho" filterable allow-create default-first-option clearable
                           placeholder="Elige un contacto o escribe el nombre completo">
                  <el-option v-for="c in contactos" :key="c.id" :label="c.nombre" :value="c.nombre" />
                </el-select>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label>Tipo Entrega</label>
                <el-select v-model="form.tipo_entrega" clearable placeholder="Seleccionar">
                  <el-option v-for="t in tables.tipos_entrega" :key="t" :label="t" :value="t" />
                </el-select>
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Tamaño del vehículo necesario para la entrega.">Tamaño Movilidad <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.tamano_movilidad" clearable placeholder="Seleccionar">
                  <el-option v-for="t in tables.tamanos_movilidad" :key="t" :label="t" :value="t" />
                </el-select>
              </div>
            </el-col>
          </el-row>

          <!-- Datos del Responsable de Despacho (solo lectura, tomados del contacto elegido) -->
          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label>Cargo / Área</label>
                <el-input :value="contactoDespachoInfo ? (contactoDespachoInfo.cargo || '') : ''" readonly placeholder="—" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label>Teléfono Responsable</label>
                <el-input :value="contactoDespachoInfo ? (contactoDespachoInfo.telefono || '') : ''" readonly placeholder="—" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label>Correo Responsable</label>
                <el-input :value="contactoDespachoInfo ? (contactoDespachoInfo.correo || '') : ''" readonly placeholder="—" />
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="16">
              <div class="form-group">
                <label>Dirección Entrega</label>
                <el-input v-model="form.direccion_entrega" placeholder="Dirección completa" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label>Horario Atención</label>
                <el-input v-model="form.horario_atencion_despacho" placeholder="Ej: 8am a 5pm" />
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="24">
              <div class="checkbox-group">
                <el-checkbox v-model="form.requiere_programacion_despacho" title="Marca si la entrega requiere programación previa con el cliente.">Requiere Programación</el-checkbox>
                <el-checkbox v-model="form.confirmacion_ingreso" title="Marca si se requiere confirmar el ingreso de la mercadería en destino.">Confirmación Ingreso</el-checkbox>
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20" class="mt-3">
            <el-col :span="24">
              <div class="form-group">
                <label>Documentación Adicional</label>
                <el-input type="textarea" v-model="form.documentacion_adicional_despacho" :autosize="{minRows: 2, maxRows: 6}" placeholder="Guía de remisión, DNI, etc." />
              </div>
            </el-col>
          </el-row>
        </div>
      </el-tab-pane>

      <!-- Tab: Seguridad -->
      <el-tab-pane label="Seguridad" name="seguridad">
        <div class="tab-content-inner">
          <h4 class="section-title">Implementos de Seguridad</h4>
          
          <h5 class="subsection-title">Personal de Logística</h5>
          <el-row :gutter="20">
            <el-col :span="24">
              <div class="checkbox-group">
                <el-checkbox v-model="form.sctr_personal_logistica" title="SCTR: Seguro Complementario de Trabajo de Riesgo del personal de logística.">SCTR Personal Logística</el-checkbox>
                <el-checkbox v-model="form.epps_logistica" title="EPP: Equipos de Protección Personal del personal de logística.">EPPs Logística</el-checkbox>
              </div>
            </el-col>
          </el-row>

          <h5 class="subsection-title mt-4">Conductor / Transportista</h5>
          <el-row :gutter="20">
            <el-col :span="24">
              <div class="checkbox-group">
                <el-checkbox v-model="form.sctr_conductor" title="SCTR: Seguro Complementario de Trabajo de Riesgo del conductor.">SCTR Conductor</el-checkbox>
                <el-checkbox v-model="form.epps_conductor" title="EPP: Equipos de Protección Personal del conductor.">EPPs Conductor</el-checkbox>
                <el-checkbox v-model="form.documentacion_transportista" title="Documentación requerida del transportista (licencia, SOAT, etc.).">Documentación Transportista</el-checkbox>
              </div>
            </el-col>
          </el-row>
        </div>
      </el-tab-pane>

      <!-- Tab: Post Venta -->
      <el-tab-pane label="Post Venta" name="postventa">
        <div class="tab-content-inner">
          <h4 class="section-title">Post Venta</h4>
          
          <el-row :gutter="20">
            <el-col :span="24">
              <div class="checkbox-group">
                <el-checkbox v-model="form.carta_conformidad" title="Marca si el cliente firmó la carta de conformidad del servicio.">Carta de Conformidad</el-checkbox>
                <el-checkbox v-model="form.solicitud_promocion" title="Marca si el cliente solicitó material promocional.">Solicitud de Promoción</el-checkbox>
                <el-checkbox v-model="form.acepta_solicitud_promocion" title="Marca si el cliente acepta recibir promociones.">Acepta Solicitud Promoción</el-checkbox>
                <el-checkbox v-model="form.tiene_datos_cumpleanos" title="Marca si se registraron las fechas de cumpleaños del contacto.">Tiene Datos Cumpleaños</el-checkbox>
              </div>
            </el-col>
          </el-row>

          <h5 class="subsection-title mt-4">Estatus de Proveedores</h5>
          <el-row :gutter="20">
            <el-col :span="24">
              <div class="form-group">
                <el-input type="textarea" v-model="form.estatus_proveedores" :autosize="{minRows: 3, maxRows: 7}" placeholder="Detalle del estatus..." />
              </div>
            </el-col>
          </el-row>

          <!-- Evaluación de Proveedores (rating post-venta) -->
          <h5 class="subsection-title mt-4">Evaluación de Proveedores</h5>
          <el-table :data="form.evaluaciones_proveedor" size="small" border empty-text="Sin evaluaciones">
            <el-table-column label="Proveedor" min-width="260">
              <template slot-scope="scope">
                <el-select v-model="scope.row.proveedor_id"
                           filterable remote
                           :remote-method="(q) => searchProveedoresEval(q, scope.$index)"
                           :loading="scope.row._loading"
                           placeholder="Buscar proveedor por razón social o RUC"
                           size="small" style="width: 100%"
                           @change="(val) => onProveedorEvalChange(scope.$index, val)">
                  <el-option v-for="p in (scope.row._options || [])"
                             :key="p.id"
                             :label="p.razon_social + ' (' + (p.ruc || '') + ')'"
                             :value="p.id" />
                </el-select>
                <small v-if="scope.row.proveedor_nombre" class="field-hint" style="font-size: 11px">
                  {{ scope.row.proveedor_nombre }}
                </small>
              </template>
            </el-table-column>

            <el-table-column label="Calificación" width="220" align="center">
              <template slot-scope="scope">
                <el-rate v-model="scope.row.estrellas"
                         show-text
                         :texts="['Malo', 'Regular', 'Regular', 'Bueno', 'Bueno']"
                         @change="(val) => onEstrellasChange(scope.$index, val)" />
              </template>
            </el-table-column>

            <el-table-column label="Comentario / Observación" min-width="280">
              <template slot-scope="scope">
                <el-input v-model="scope.row.comentario"
                          type="textarea" :autosize="{minRows: 2, maxRows: 6}"
                          size="small"
                          placeholder="Respuesta, tiempos, calidad, etc." />
              </template>
            </el-table-column>

            <el-table-column width="60" align="center" label="Quitar">
              <template slot-scope="scope">
                <el-button type="danger" icon="el-icon-delete" size="mini" circle
                           @click="removeEvaluacionProveedor(scope.$index)" />
              </template>
            </el-table-column>
          </el-table>
          <el-button size="small" class="mt-2" type="primary" plain icon="el-icon-download"
                     @click="cargarProveedoresDelProyecto">
            Cargar proveedores del proyecto
          </el-button>
          <el-button size="small" class="mt-2" icon="el-icon-plus"
                     @click="addEvaluacionProveedor">
            Agregar evaluación
          </el-button>

          <h5 class="subsection-title mt-4">Resumen del Proyecto</h5>
          <el-row :gutter="20">
            <el-col :span="24">
              <div class="form-group">
                <el-input type="textarea" v-model="form.resumen_proyecto" :autosize="{minRows: 3, maxRows: 7}" placeholder="Resumen general..." />
              </div>
            </el-col>
          </el-row>
        </div>
      </el-tab-pane>

      <!-- Tab: Cobranza -->
      <el-tab-pane label="Cobranza" name="cobranza">
        <div class="tab-content-inner">
          <h4 class="section-title">Cobranza</h4>
          
          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Fecha en que se envió la factura al cliente.">Fecha Envío Factura <i class="el-icon-info field-tip-icon"></i></label>
                <el-date-picker v-model="form.fecha_envio_factura" type="date" format="dd/MM/yyyy" value-format="yyyy-MM-dd" style="width: 100%" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Fecha en que el cliente registró el ingreso de la factura.">Fecha Ingreso Factura <i class="el-icon-info field-tip-icon"></i></label>
                <el-date-picker v-model="form.fecha_ingreso_factura" type="date" format="dd/MM/yyyy" value-format="yyyy-MM-dd" style="width: 100%" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Fecha estimada en que el cliente realizará el pago.">Fecha Tentativa Pago <i class="el-icon-info field-tip-icon"></i></label>
                <el-date-picker v-model="form.fecha_tentativa_pago" type="date" format="dd/MM/yyyy" value-format="yyyy-MM-dd" style="width: 100%" />
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="16">
              <div class="form-group">
                <label>Método / Política Pago</label>
                <el-input v-model="form.metodo_politica_pago" placeholder="Ej: Solo los Viernes" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Indica si el pago recibido del cliente es conforme.">Pago Conforme <i class="el-icon-info field-tip-icon"></i></label>
                <el-select v-model="form.pago_conforme" clearable placeholder="Seleccionar">
                  <el-option label="Pendiente" :value="null" />
                  <el-option label="Sí" :value="true" />
                  <el-option label="No" :value="false" />
                </el-select>
              </div>
            </el-col>
          </el-row>

          <el-row :gutter="20">
            <el-col :span="24">
              <div class="form-group">
                <label>Observaciones</label>
                <el-input type="textarea" v-model="form.observaciones_cobranza" :autosize="{minRows: 2, maxRows: 6}" placeholder="Notas sobre cobranza..." />
              </div>
            </el-col>
          </el-row>

          <h5 class="subsection-title mt-4">Adelanto Recibido</h5>
          <el-row :gutter="20">
            <el-col :span="8">
              <div class="form-group">
                <label>Monto Adelanto (S/)</label>
                <el-input-number v-model="form.adelanto_monto" :precision="2" :min="0" :controls="false" style="width: 100%" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label>Fecha Adelanto</label>
                <el-date-picker v-model="form.adelanto_fecha" type="date" format="dd/MM/yyyy" value-format="yyyy-MM-dd" style="width: 100%" />
              </div>
            </el-col>
            <el-col :span="8">
              <div class="form-group">
                <label class="lbl-help" title="Porcentaje que representa el adelanto sobre el monto total.">% del Total <i class="el-icon-info field-tip-icon"></i></label>
                <el-input :value="calcularPorcentajeAdelanto" disabled />
              </div>
            </el-col>
          </el-row>
        </div>
      </el-tab-pane>

      <!-- Tab: Bitácora (solo edición) -->
      <el-tab-pane v-if="isEdit" label="Bitácora" name="bitacora">
        <div class="tab-content-inner">
          <!-- M3: Cambios e indicaciones (editable) -->
          <h4 class="section-title">Cambios e indicaciones</h4>
          <p class="field-hint" style="margin-top:-4px;margin-bottom:10px;">
            Registra cambios, indicaciones importantes o situaciones imprevistas durante la ejecución. Cada registro guarda automáticamente quién y cuándo.
          </p>

          <el-input
            v-model="nuevoComentario"
            type="textarea"
            :autosize="{minRows:2,maxRows:6}"
            placeholder="Escribe un cambio o indicación..."
          />
          <div class="mt-2" style="text-align:right;">
            <el-button type="primary" size="small" icon="el-icon-plus"
                       :loading="guardandoBitacora"
                       :disabled="!nuevoComentario || !nuevoComentario.trim()"
                       @click="agregarBitacora">Agregar</el-button>
          </div>

          <div v-loading="loadingBitacora" class="bitacora-lista">
            <div v-if="!bitacora.length" class="bitacora-vacia">Sin registros todavía.</div>
            <div v-for="entry in bitacora" :key="entry.id" class="bitacora-item">
              <template v-if="editandoBitacoraId === entry.id">
                <el-input v-model="edicionContenido" type="textarea" :autosize="{minRows:2,maxRows:6}" />
                <div class="mt-2" style="text-align:right;">
                  <el-button size="mini" @click="cancelarEdicionBitacora">Cancelar</el-button>
                  <el-button type="primary" size="mini" :loading="guardandoBitacora"
                             :disabled="!edicionContenido || !edicionContenido.trim()"
                             @click="guardarEdicionBitacora(entry)">Guardar</el-button>
                </div>
              </template>
              <template v-else>
                <div class="bitacora-item__contenido">{{ entry.contenido }}</div>
                <div class="bitacora-item__meta">
                  <span>{{ entry.autor || 'Usuario' }} · {{ entry.created_at }}</span>
                  <span v-if="entry.edited_at" class="bitacora-item__editado">· editado {{ entry.edited_at }}<span v-if="entry.editor"> por {{ entry.editor }}</span></span>
                  <el-button type="text" size="mini" class="bitacora-item__editar" @click="iniciarEdicionBitacora(entry)">Editar</el-button>
                </div>
              </template>
            </div>
          </div>

          <h4 class="section-title mt-4">Historial del sistema (automático)</h4>
          
          <el-table :data="logs" size="small" v-loading="loadingLogs">
            <el-table-column prop="created_at" label="Fecha" width="150" />
            <el-table-column prop="user" label="Usuario" width="150" />
            <el-table-column prop="event" label="Acción" width="100">
              <template slot-scope="scope">
                <el-tag :type="getEventType(scope.row.event)" size="small">{{ scope.row.event }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="Cambios" min-width="300">
              <template slot-scope="scope">
                <div v-for="(value, key) in scope.row.new_values" :key="key" class="change-item">
                  <strong>{{ formatFieldName(key) }}:</strong>
                  <span class="old-value" v-if="scope.row.old_values[key]">{{ scope.row.old_values[key] }}</span>
                  <span v-if="scope.row.old_values[key]"> → </span>
                  <span class="new-value">{{ value }}</span>
                </div>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-tab-pane>
    </el-tabs>

    <!-- Footer -->
    <div slot="footer" class="dialog-footer">
      <span class="footer-info" v-if="isEdit">
        Última actualización: {{ form.updated_at }}
      </span>
      <el-button @click="handleClose">Cancelar</el-button>
      <el-button type="primary" :loading="saving" @click="save">Guardar</el-button>
    </div>
  </el-dialog>
</template>

<script>
// COM-02 · Cálculo del Margen Real de la pre-cotización (mismo helper que usa,
// como fuente, la pestaña Pre-Cotización) para mostrarlo en el campo Margen (%).
import { margenRealDeItems, sctrCotizacionDe } from '../../lib/costeoReal'
// Agosto 2026 (punto 19) · Bloque de Observaciones con los títulos en negrita.
import ObservacionesProducto from './ObservacionesProducto'

export default {
  components: { ObservacionesProducto },
  props: {
    visible: {
      type: Boolean,
      default: false
    },
    proyectoId: {
      type: [Number, String],
      default: null
    }
  },
  data() {
    return {
      dialogVisible: this.visible,
      activeTab: 'datos',
      saving: false,
      loadingLogs: false,
      form: this.getEmptyForm(),
      // COM-02 · Margen Real (%) traído de la pre-cotización del requerimiento
      // origen. null = todavía no cargado o el proyecto no viene de un
      // requerimiento con pre-cotización (ahí el campo cae al cálculo histórico).
      margenRealPre: null,
      // COM-02 · Monto de la cotización SIN IGV = Subtotal de la pre-cotización.
      // La cotización formal solo guarda el total CON IGV, por eso el sin-IGV se
      // toma del Subtotal de la pre-cotización (es exactamente el mismo importe).
      montoSinIgvPre: null,
      contactos: [],
      logs: [],
      // M3: bitácora "Cambios e indicaciones"
      loadingBitacora: false,
      guardandoBitacora: false,
      bitacora: [],
      nuevoComentario: '',
      editandoBitacoraId: null,
      edicionContenido: '',

      // Imagen por producto (subir/cambiar/quitar)
      uploadingProductoImageId: null,   // id del producto cuya imagen se está subiendo
      imgVersion: Date.now(),           // cache-bust para refrescar la miniatura
      productoImageRow: null,           // fila objetivo del input de archivo compartido

      // Estado del modal selector de contactos de facturación
      picker: {
        visible: false,
        loading: false,
        disponibles: [],     // contactos de la empresa (cargados cuando se abre el modal)
        selected: [],        // seleccionados por el usuario (checkbox)
        search: '',
      },
      tables: {
        empresas: [],
        ejecutivos: [],
        etapas: [],
        tipos_proyecto: [],
        prioridades: [],
        formas_pago: [],
        dias_credito_opciones: [5, 7, 9, 15],
        tipos_entrega: [],
        tamanos_movilidad: [],
      }
    };
  },
  computed: {
    isEdit() {
      return !!this.proyectoId;
    },

    // Opciones de "¿Tiene O/C o OS?" (agosto 2026, punto 18).
    //
    // Los proyectos anteriores guardaron "Interna" o "Formal", sin distinguir
    // entre orden de compra y orden de servicio. Si el proyecto abierto tiene
    // uno de esos valores viejos, se agrega al final de la lista: así la ficha
    // muestra lo que realmente tiene guardado en lugar de aparecer vacía, y el
    // usuario elige la opción nueva cuando le corresponda actualizarlo.
    opcionesOrdenCompra() {
      var opciones = ['Pendiente', 'Interna - OC', 'Formal - OC', 'Interna - OS', 'Formal - OS'];
      var actual = (this.form.tiene_oc || '').toString().trim();
      if (actual !== '' && opciones.indexOf(actual) === -1) {
        return opciones.concat([actual]);
      }
      return opciones;
    },

    // Datos del contacto elegido como Responsable de Despacho (cargo, telefono, correo).
    // El select guarda el nombre, asi que buscamos el contacto por nombre en el listado cargado.
    contactoDespachoInfo() {
      var nombre = this.form.contacto_despacho;
      if (!nombre || !this.contactos || !this.contactos.length) return null;
      return this.contactos.find(function(c) { return c.nombre === nombre; }) || null;
    },
    calcularMargen() {
      if (!this.form.monto_total || this.form.monto_total === 0) return '0%';
      if (!this.form.monto_costo) return '100%';
      const margen = ((this.form.monto_total - this.form.monto_costo) / this.form.monto_total) * 100;
      return margen.toFixed(1) + '%';
    },
    // COM-02 · Valor que muestra el campo Margen (%).
    // Si el proyecto viene de un requerimiento y ya se trajo el Margen Real de su
    // pre-cotización, se muestra ESE (idéntico al de la pestaña Pre-Cotización,
    // con 2 decimales). Si no (proyecto manual, sin pre-cotización o aún
    // cargando), cae al cálculo histórico entre monto y costo.
    margenMostrado() {
      if (this.form.requerimiento_id && this.margenRealPre !== null && !isNaN(this.margenRealPre)) {
        return Number(this.margenRealPre).toFixed(2) + '%';
      }
      return this.calcularMargen;
    },
    // P7: total a facturar = Monto Real Cotización (con IGV). Si el proyecto viene
    // de un requerimiento es requerimiento_monto_real; si no, el monto_costo manual.
    montoRealEfectivo() {
      var v = this.form.requerimiento_id ? this.form.requerimiento_monto_real : this.form.monto_costo;
      return parseFloat(v) || 0;
    },
    // P7: suma de los montos a facturar de cada razón social.
    sumaMontosRS() {
      return (this.form.razones_sociales || []).reduce(function(acc, rs) {
        return acc + (parseFloat(rs.monto_facturar) || 0);
      }, 0);
    },
    // P7: ¿la suma cuadra con el total a facturar? (tolerancia de 1 céntimo por redondeo).
    montosRSCuadran() {
      return Math.abs(this.sumaMontosRS - this.montoRealEfectivo) <= 0.01;
    },
    calcularPorcentajeAdelanto() {
      if (!this.form.monto_total || this.form.monto_total === 0) return '0%';
      if (!this.form.adelanto_monto) return '0%';
      const porcentaje = (this.form.adelanto_monto / this.form.monto_total) * 100;
      return porcentaje.toFixed(1) + '%';
    },
    calcularTotalProductos() {
      if (!this.form.productos || this.form.productos.length === 0) return 0;
      var total = 0;
      for (var i = 0; i < this.form.productos.length; i++) {
        total += parseFloat(this.form.productos[i].subtotal) || 0;
      }
      return total;
    },
    /**
     * Resumen de costos del proyecto (M4): consolida proveedores (neto + IGV) y los
     * costos operativos (empaque, movilidad, SCTR) + su IGV. Misma fórmula que el
     * backend (recalcularCostoTotalProyecto).
     */
    resumenProyecto() {
      var IGV = 0.18;
      var empaque = 0, sctr = 0, movilidad = 0, provNet = 0, provTotal = 0;
      var self = this;
      (this.form.productos || []).forEach(function(p) {
        empaque += parseFloat(p.costo_empaque) || 0;
        sctr += parseFloat(p.costo_sctr) || 0;
        movilidad += parseFloat(p.costo_movilidad) || 0;
        (p.proveedores || []).forEach(function(pr) {
          if (!pr.activo) return;
          var m = self.montosProveedor(pr);
          provNet += m.subtotalNeto;
          provTotal += m.total;
        });
      });
      var operativo = empaque + sctr + movilidad;
      var subtotal = provNet + operativo;
      var igv = Math.max(provTotal - provNet, 0) + operativo * IGV;
      return {
        empaque: empaque, sctr: sctr, movilidad: movilidad,
        subtotal: subtotal, igv: igv, total: subtotal + igv,
      };
    },
    montoTotalProyecto() {
      return this.resumenProyecto.total;
    },
    /**
     * Los productos del proyecto son un snapshot de la cotizacion aprobada del
     * requerimiento. Cuando el proyecto nace de un requerimiento quedan fijos:
     * no se editan Descripcion/Cantidad/Muestras/P.Unitario ni se agregan/quitan;
     * solo Observaciones/Seguimiento del pedido es editable. Un proyecto creado
     * manualmente (sin requerimiento) si permite edicion completa.
     */
    productosBloqueados() {
      return !!this.form.requerimiento_id;
    },
    /**
     * Forma de pago mixta: requiere que el usuario indique los días de crédito.
     * Aplica a: "50% adelanto 50% crédito".
     * (La regex igual sigue tolerando registros viejos con "50% crédito 50% adelanto").
     */
    requiereDiasCredito() {
      if (!this.form.forma_pago) return false;
      var fp = this.form.forma_pago.toLowerCase();
      // Contiene "50%" y "adelanto" y "crédito" en el texto
      return fp.indexOf('50%') !== -1
          && /adelanto/.test(fp)
          && /crédito|credito/.test(fp);
    },
    /**
     * Filtro en memoria del listado del modal de contactos.
     * Excluye los que ya están agregados y aplica búsqueda por texto.
     */
    pickerFiltered() {
      var term = (this.picker.search || '').toLowerCase().trim();
      var yaAgregados = (this.form.contactos_facturacion || [])
        .map(function(c) { return c.contacto_id; })
        .filter(function(id) { return !!id; });

      return (this.picker.disponibles || []).filter(function(c) {
        if (yaAgregados.indexOf(c.id) !== -1) return false;
        if (!term) return true;
        var haystack = ((c.nombre || '') + ' ' + (c.cargo || '') + ' ' + (c.correo || '')).toLowerCase();
        return haystack.indexOf(term) !== -1;
      });
    }
  },
  watch: {
    visible: {
      immediate: true,
      handler(val) {
        this.dialogVisible = val;
        if (val) {
          this.init();
        }
      }
    },
    dialogVisible(val) {
      this.$emit('update:visible', val);
    },
    'form.etapa': function(newEtapa) {
      // Auto-asignar fecha de inicio cuando el proyecto entra en producción (primera
      // etapa activa "En curso / Pre-producción") y aún está vacía.
      if (newEtapa === 'En curso / Pre-producción' && !this.form.fecha_inicio) {
        this.form.fecha_inicio = new Date().toISOString().substr(0, 10);
      }
    },
    // P7: avisar si se reduce la Cantidad de Razones Sociales por debajo de las ya cargadas.
    'form.cantidad_razones_sociales': function(nuevo) {
      var cargadas = Array.isArray(this.form.razones_sociales) ? this.form.razones_sociales.length : 0;
      if (nuevo && cargadas > nuevo) {
        this.$message.warning('Tienes ' + cargadas + ' razones sociales cargadas pero la cantidad es ' + nuevo + '. Quita las que sobren.');
      }
    }
  },
  methods: {
    async init() {
      this.activeTab = 'datos';
      this.form = this.getEmptyForm();
      this.logs = [];
      this.bitacora = [];
      this.nuevoComentario = '';
      this.editandoBitacoraId = null;
      this.edicionContenido = '';

      await this.loadTables();

      if (this.isEdit) {
        await this.loadProyecto();
        this.loadLogs();
        this.loadBitacora();
      }
    },
    getEmptyForm() {
      return {
        id: null,
        codigo: '',
        empresa_id: null,
        empresa_razon_social: null,
        contacto_id: null,
        contacto_nombre: null,
        requerimiento_id: null,
        requerimiento_codigo: null,
        // Agosto 2026 (punto 16): código MAP de la cotización aprobada que originó
        // el proyecto, y la URL de su PDF. Los dos son de solo lectura: los resuelve
        // el backend contra la propuesta aprobada del requerimiento.
        cotizacion_origen_codigo: null,
        cotizacion_origen_pdf_url: null,
        requerimiento_monto_estimado: null,
        requerimiento_monto_real: null,
        requerimiento_fecha_requerida: null,
        ejecutivo_id: null,
        nombre: '',
        // Agosto 2026 (punto 17): coordinador a cargo del proyecto.
        coordinador: '',
        descripcion: '',
        tipo: null,
        prioridad: 'Estándar',
        etapa: 'Nuevo',
        estado: 'activo',
        semaforo: 'Verde',
        monto_total: null,
        monto_costo: null,
        margen_pct: null,
        fecha_inicio: null,
        fecha_fin: null,
        oc_numero: '',
        oc_fecha: null,
        porcentaje_avance: 0,
        porcentaje_facturado: 0,
        porcentaje_cobrado: 0,
        // Criterios
        tiempo_proyecto: '',
        politica_tiempos_cortos: null,
        es_recompra: null,
        tipo_cliente_1: null,
        tipo_cliente_2: null,
        tiene_oc: null,
        // Facturación
        servicio_facturado: null,
        cantidad_razones_sociales: 1,
        cada_rs_con_oc: false,
        aplica_detraccion: false,
        aplica_retencion: false,
        forma_pago: null,
        dias_credito: null,
        metodo_politica_pago: '',
        pago_directo: true,
        contacto_facturacion: '',
        contactos_facturacion: [],
        correo_factura: '',
        requiere_formato_especial: false,
        entrega_factura_fisica: null,
        direccion_entrega_factura: '',
        documento_adicional_factura: '',
        comentario_facturacion: '',
        // Muestras
        muestra_virtual: false,
        muestra_fisica: false,
        prueba_color: false,
        arte_aprobado_at: null,
        // Logística
        tipo_embalaje: '',
        embalaje_grupal: null,
        rotulo_especial: false,
        sticker_compipro: false,
        cantidad_sticker: null,
        // Despacho
        contacto_despacho: '',
        tipo_entrega: null,
        direccion_entrega: '',
        horario_atencion_despacho: '',
        documentacion_adicional_despacho: '',
        tamano_movilidad: null,
        requiere_programacion_despacho: false,
        confirmacion_ingreso: false,
        // Seguridad
        sctr_personal_logistica: false,
        epps_logistica: false,
        sctr_conductor: false,
        epps_conductor: false,
        documentacion_transportista: false,
        // Post Venta
        evaluaciones_proveedor: [],
        carta_conformidad: false,
        solicitud_promocion: false,
        acepta_solicitud_promocion: false,
        tiene_datos_cumpleanos: false,
        estatus_proveedores: '',
        resumen_proyecto: '',
        // Cobranza
        fecha_envio_factura: null,
        fecha_ingreso_factura: null,
        fecha_tentativa_pago: null,
        metodo_politica_pago: '',
        pago_conforme: null,
        observaciones_cobranza: '',
        adelanto_monto: null,
        adelanto_fecha: null,
        // Relaciones
        razones_sociales: [],
        productos: [],
        // Meta
        updated_at: null,
      };
    },
    async loadTables() {
      try {
        const response = await this.$http.get('/comercial/proyectos/tables');
        this.tables = response.data;
      } catch (error) {
        console.error('Error cargando tablas:', error);
      }
    },
    async loadProyecto() {
      try {
        const response = await this.$http.get(`/comercial/proyectos/record/${this.proyectoId}`);
        if (response.data.success) {
          this.form = { ...this.form, ...response.data.data };

          // Pre-llenar fecha entrega desde el requerimiento si está vacía
          if (!this.form.fecha_fin && this.form.requerimiento_fecha_requerida) {
            this.form.fecha_fin = this.form.requerimiento_fecha_requerida;
          }

          if (this.form.empresa_id) {
            this.loadContactos(this.form.empresa_id);
          }

          // Inicializar campos helper de razones_sociales para el autocompletar
          if (Array.isArray(this.form.razones_sociales)) {
            this.form.razones_sociales = this.form.razones_sociales.map(function(rs) {
              // Preservar tiene_oc como string (si venía como booleano viejo, normalizar)
              var tiene = rs.tiene_oc;
              if (typeof tiene === 'boolean') tiene = tiene ? 'Se tiene' : 'Pendiente';
              if (tiene === null || tiene === undefined || tiene === '') tiene = 'Pendiente';

              // Pre-poblar options del autocomplete con la empresa ya asociada
              var opciones = [];
              if (rs.razon_social) {
                opciones.push({
                  id: rs.empresa_id || ('rs-' + rs.id),
                  razon_social: rs.razon_social,
                  ruc: rs.ruc || ''
                });
              }
              return Object.assign({}, rs, {
                tiene_oc: tiene,
                empresa_busqueda: rs.empresa_id || null,
                _options: opciones,
                _loading: false,
              });
            });
          }

          // Inicializar campos helper de evaluaciones_proveedor para el autocomplete
          if (Array.isArray(this.form.evaluaciones_proveedor)) {
            this.form.evaluaciones_proveedor = this.form.evaluaciones_proveedor.map(function(ev) {
              var opciones = [];
              if (ev.proveedor_id && ev.proveedor_nombre) {
                opciones.push({
                  id: ev.proveedor_id,
                  razon_social: ev.proveedor_nombre,
                  ruc: ''
                });
              }
              return Object.assign({}, ev, {
                _options: opciones,
                _loading: false,
              });
            });
          }

          // Inicializar productos y sus proveedores (M2): parsear numéricos, sembrar
          // la opción del select con el proveedor actual y recalcular los totales.
          if (Array.isArray(this.form.productos)) {
            this.form.productos = this.form.productos.map(function(p) {
              var proveedores = Array.isArray(p.proveedores) ? p.proveedores : [];
              proveedores = proveedores.map(function(pr) {
                var opciones = [];
                if (pr.supplier_id && pr.supplier_name) {
                  opciones.push({ id: pr.supplier_id, razon_social: pr.supplier_name, ruc: '' });
                }
                return Object.assign({}, pr, {
                  cantidad: parseFloat(pr.cantidad) || 0,
                  precio_unitario_informado: parseFloat(pr.precio_unitario_informado) || 0,
                  condicion_igv: pr.condicion_igv || 'pending',
                  activo: pr.activo === undefined ? true : !!pr.activo,
                  _options: opciones,
                  _loading: false,
                });
              });
              return Object.assign({}, p, {
                costo_empaque: parseFloat(p.costo_empaque) || 0,
                costo_movilidad: parseFloat(p.costo_movilidad) || 0,
                costo_sctr: parseFloat(p.costo_sctr) || 0,
                precio_unitario: parseFloat(p.precio_unitario) || 0,
                subtotal: parseFloat(p.subtotal) || 0,
                proveedores: proveedores,
              });
            });
            var selfProd = this;
            this.form.productos.forEach(function(p) { selfProd.recalcProducto(p); });
          }

          // COM-02 · Traer el Margen Real de la pre-cotización del requerimiento
          // origen para el campo Margen (%). Fire-and-forget: no bloquea la carga.
          this.cargarMargenRealPrecotizacion();
        }
      } catch (error) {
        this.$message.error('Error al cargar el proyecto');
        console.error(error);
      }
    },
    // COM-02 · Trae el Margen Real de la pre-cotización del requerimiento origen y
    // lo deja en `margenRealPre`. Usa el MISMO cálculo que la pestaña
    // Pre-Cotización (helper costeoReal), así el número es idéntico al que ve la
    // ejecutiva. Toma la primera pre-cotización del requerimiento (la misma que
    // muestra esa pestaña). Silencioso: si no hay pre-cotización o falla, el
    // campo Margen (%) cae al cálculo histórico.
    async cargarMargenRealPrecotizacion() {
      this.margenRealPre = null;
      this.montoSinIgvPre = null;
      var reqId = this.form.requerimiento_id;
      if (!reqId) return;
      try {
        var lista = await this.$http.get('/marketing/quotation/prequotes', {
          params: { requerimiento_id: reqId }
        });
        var arr = (lista.data && lista.data.prequotes && lista.data.prequotes.data)
          || (lista.data && lista.data.prequotes) || [];
        if (!arr.length) return;
        var detalle = await this.$http.get('/marketing/quotation/prequotes/' + arr[0].id);
        var pq = detalle.data && detalle.data.prequote;
        if (!pq || !Array.isArray(pq.items) || !pq.items.length) return;
        var resumen = margenRealDeItems(pq.items, sctrCotizacionDe(pq));
        this.margenRealPre = resumen.margenRealPct;
        // El Subtotal de la pre-cotización ES el monto de la cotización sin IGV
        // (verificado: subtotal × 1.18 = el total de la cotización aprobada).
        var subt = parseFloat(pq.subtotal);
        this.montoSinIgvPre = isNaN(subt) ? null : subt;
      } catch (e) {
        this.margenRealPre = null;
      }
    },
    async loadLogs() {
      this.loadingLogs = true;
      try {
        const response = await this.$http.get(`/comercial/proyectos/logs/${this.proyectoId}`);
        if (response.data.success) {
          this.logs = response.data.data;
        }
      } catch (error) {
        console.error('Error cargando logs:', error);
      } finally {
        this.loadingLogs = false;
      }
    },
    // ==== M3: Bitácora "Cambios e indicaciones" ====
    async loadBitacora() {
      if (!this.proyectoId) return;
      this.loadingBitacora = true;
      try {
        var response = await this.$http.get('/comercial/proyectos/record/' + this.proyectoId + '/bitacora');
        if (response.data && response.data.success) {
          this.bitacora = response.data.data || [];
        }
      } catch (error) {
        console.error('Error cargando bitácora:', error);
      } finally {
        this.loadingBitacora = false;
      }
    },
    async agregarBitacora() {
      var contenido = (this.nuevoComentario || '').trim();
      if (!contenido) return;
      this.guardandoBitacora = true;
      try {
        var response = await this.$http.post('/comercial/proyectos/record/' + this.proyectoId + '/bitacora', { contenido: contenido });
        if (response.data && response.data.success) {
          this.bitacora.push(response.data.data);
          this.nuevoComentario = '';
          this.$message.success('Registro agregado');
        }
      } catch (error) {
        this.$message.error('No se pudo agregar el registro');
      } finally {
        this.guardandoBitacora = false;
      }
    },
    iniciarEdicionBitacora(entry) {
      this.editandoBitacoraId = entry.id;
      this.edicionContenido = entry.contenido;
    },
    cancelarEdicionBitacora() {
      this.editandoBitacoraId = null;
      this.edicionContenido = '';
    },
    async guardarEdicionBitacora(entry) {
      var contenido = (this.edicionContenido || '').trim();
      if (!contenido) return;
      this.guardandoBitacora = true;
      try {
        var response = await this.$http.put('/comercial/proyectos/record/' + this.proyectoId + '/bitacora/' + entry.id, { contenido: contenido });
        if (response.data && response.data.success) {
          var idx = this.bitacora.findIndex(function(b) { return b.id === entry.id; });
          if (idx !== -1) this.$set(this.bitacora, idx, response.data.data);
          this.cancelarEdicionBitacora();
          this.$message.success('Registro actualizado');
        }
      } catch (error) {
        this.$message.error('No se pudo actualizar el registro');
      } finally {
        this.guardandoBitacora = false;
      }
    },
    async loadContactos(empresaId) {
      try {
        const response = await this.$http.get(`/comercial/proyectos/contactos-empresa/${empresaId}`);
        this.contactos = response.data;
      } catch (error) {
        console.error('Error cargando contactos:', error);
      }
    },
    onEmpresaChange(empresaId) {
      this.form.contacto_id = null;
      this.contactos = [];
      if (empresaId) {
        this.loadContactos(empresaId);
      }
    },
    // ============================================================
    // FACTURACIÓN - Lógica nueva (2026-04-20)
    // ============================================================

    /**
     * Al cambiar la forma de pago, limpiar días_credito si no aplica.
     */
    onFormaPagoChange(val) {
      if (!this.requiereDiasCredito) {
        this.form.dias_credito = null;
      }
      // Si es "Crédito X días", pre-llenar dias_credito automáticamente (opcional, informativo)
      if (val) {
        var match = /Crédito\s+(\d+)\s+días/i.exec(val);
        if (match && !this.requiereDiasCredito) {
          this.form.dias_credito = parseInt(match[1], 10);
        }
      }
    },

    // ==== Razones Sociales con autocompletar de empresas ====

    addRazonSocial() {
      // P7: no permitir más razones sociales que la "Cantidad Razones Sociales".
      if (this.form.razones_sociales.length >= (this.form.cantidad_razones_sociales || 1)) {
        this.$message.warning('Alcanzaste la Cantidad de Razones Sociales (' + (this.form.cantidad_razones_sociales || 1) + '). Si necesitas más, aumenta ese número primero.');
        return;
      }
      this.form.razones_sociales.push({
        id: null,
        empresa_id: null,
        empresa_busqueda: null,
        razon_social: '',
        ruc: '',
        monto_facturar: null,
        tiene_oc: 'Pendiente',
        oc_numero: '',
        oc_archivo: null,
        oc_archivo_original: null,
        observaciones: '',
        _options: [],
        _loading: false,
      });
    },
    removeRazonSocial(index) {
      this.form.razones_sociales.splice(index, 1);
    },
    searchEmpresasRS(query, rowIndex) {
      var self = this;
      if (!query || query.length < 2) return;
      var row = this.form.razones_sociales[rowIndex];
      if (!row) return;
      this.$set(row, '_loading', true);

      this.$http.get('/comercial/empresas/search', { params: { search: query } })
        .then(function(response) {
          var data = response.data;
          var list = Array.isArray(data) ? data : (data.data || []);
          self.$set(row, '_options', list.filter(function(e) { return e && e.id; }));
        })
        .catch(function() { self.$set(row, '_options', []); })
        .finally(function() { self.$set(row, '_loading', false); });
    },
    onRazonSocialChange(rowIndex, empresaId) {
      var row = this.form.razones_sociales[rowIndex];
      if (!row || !empresaId) return;
      var empresa = (row._options || []).find(function(e) { return e.id === empresaId; });
      if (empresa) {
        this.$set(row, 'empresa_id', empresa.id);
        this.$set(row, 'razon_social', empresa.razon_social);
        this.$set(row, 'ruc', empresa.ruc || '');
      }
    },

    // ==== Contactos de facturación: modal picker ====

    openContactosFacturacionPicker() {
      if (!this.form.empresa_id) {
        this.$message.warning('Primero selecciona una empresa');
        return;
      }
      var self = this;
      this.picker.visible = true;
      this.picker.selected = [];
      this.picker.search = '';
      this.picker.loading = true;

      this.$http.get('/comercial/proyectos/contactos-empresa/' + this.form.empresa_id)
        .then(function(response) {
          var data = response.data;
          var list = Array.isArray(data) ? data : (data.data || []);
          self.picker.disponibles = list.map(function(c) {
            return {
              id: c.id,
              nombre: ((c.nombres || '') + ' ' + (c.apellidos || '')).trim() || c.nombre || 'Sin nombre',
              cargo: c.cargo || '',
              telefono: c.telefono || c.celular || '',
              correo: c.correo || c.email || '',
            };
          });
        })
        .catch(function() {
          self.$message.error('No se pudieron cargar los contactos de la empresa');
          self.picker.disponibles = [];
        })
        .finally(function() { self.picker.loading = false; });
    },
    confirmContactosFacturacion() {
      var self = this;
      (this.picker.selected || []).forEach(function(c) {
        self.form.contactos_facturacion.push({
          contacto_id: c.id,
          nombre: c.nombre,
          cargo: c.cargo,
          telefono: c.telefono,
          correo: c.correo,
        });
      });
      this.picker.visible = false;
      this.picker.selected = [];
    },
    removeContactoFacturacion(index) {
      this.form.contactos_facturacion.splice(index, 1);
    },

    // ==== PDF de Orden de Compra por razón social ====

    beforeUploadOc(file, row, rowIndex) {
      var self = this;
      if (file.type !== 'application/pdf') {
        this.$message.error('Solo se permiten archivos PDF');
        return false;
      }
      if (file.size > 5 * 1024 * 1024) {
        this.$message.error('El archivo no puede superar 5 MB');
        return false;
      }
      if (!row.id) {
        this.$message.warning('Guarda el proyecto primero antes de subir el PDF');
        return false;
      }

      var fd = new FormData();
      fd.append('archivo', file);

      this.$http.post(
        '/comercial/proyectos/record/' + this.form.id + '/razon-social/' + row.id + '/oc-upload',
        fd,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      ).then(function(response) {
        if (response.data.success) {
          self.$message.success(response.data.message);
          self.$set(row, 'oc_archivo', response.data.data.oc_archivo);
          self.$set(row, 'oc_archivo_original', response.data.data.oc_archivo_original);
        } else {
          self.$message.error(response.data.message || 'Error al subir');
        }
      }).catch(function(e) {
        var msg = e.response && e.response.data && e.response.data.message
          ? e.response.data.message : 'Error al subir el PDF';
        self.$message.error(msg);
      });

      return false; // evitamos que el-upload haga su propio request
    },
    downloadOcPdf(row) {
      if (!this.form.id || !row.id) return;
      var url = '/comercial/proyectos/record/' + this.form.id + '/razon-social/' + row.id + '/oc-download';
      window.open(url, '_blank');
    },
    /**
     * Agosto 2026 (punto 16) — Abre el PDF de la cotización aprobada que dio
     * origen al proyecto.
     *
     * En una pestaña nueva a propósito: el usuario está editando un proyecto y
     * puede tener cambios sin guardar; salir de la pantalla se los llevaría.
     * Mismo criterio que downloadOcPdf, acá arriba.
     */
    verPdfCotizacionOrigen() {
      var url = this.form.cotizacion_origen_pdf_url;
      if (!url) {
        this.$message.warning('La cotización aprobada no tiene un PDF generado.');
        return;
      }
      window.open(url, '_blank');
    },
    deleteOcPdf(row, rowIndex) {
      var self = this;
      if (!this.form.id || !row.id) return;
      this.$confirm('¿Eliminar el archivo PDF de esta razón social?', 'Confirmar', {
        type: 'warning', confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar'
      }).then(function() {
        self.$http.delete('/comercial/proyectos/record/' + self.form.id + '/razon-social/' + row.id + '/oc-archivo')
          .then(function(response) {
            if (response.data.success) {
              self.$message.success('Archivo eliminado');
              self.$set(row, 'oc_archivo', null);
              self.$set(row, 'oc_archivo_original', null);
            }
          })
          .catch(function() { self.$message.error('No se pudo eliminar'); });
      }).catch(function() {});
    },
    addProducto() {
      this.form.productos.push({
        descripcion: '',
        supplier_id: null,
        supplier_name: '',
        material: '',
        medida: '',
        tecnica_impresion: '',
        cantidad_colores: null,
        cantidad_lados: null,
        empaque: '',
        personalizacion: '',
        costo_empaque: 0,
        costo_movilidad: 0,
        costo_sctr: 0,
        imagen: null,
        cantidad: 1,
        muestras: 0,
        precio_unitario: 0,
        subtotal: 0,
        observaciones: '',
        proveedores: [{
          id: null, supplier_id: null, supplier_name: '', concepto: '',
          cantidad: 1, precio_unitario_informado: 0, condicion_igv: 'pending',
          activo: true, reemplaza_a_id: null, motivo_reemplazo: null,
          _options: [], _loading: false,
        }],
      });
    },
    removeProducto(index) {
      this.form.productos.splice(index, 1);
    },

    // ==== Imagen por producto ====

    /**
     * Abre el selector de archivo para subir/cambiar la imagen del producto.
     * Guarda la fila objetivo y dispara el click del input compartido.
     */
    triggerProductoImageUpload(row) {
      if (!row || !row.id) {
        this.$message.warning('Guarda el proyecto primero para gestionar la imagen.');
        return;
      }
      this.productoImageRow = row;
      var input = this.$refs.productoImageInput;
      if (input) {
        input.value = ''; // permite volver a elegir el mismo archivo
        input.click();
      }
    },

    /**
     * Maneja el archivo elegido: valida mime + tamaño y lo sube por FormData.
     */
    onProductoImageSelected(event) {
      var self = this;
      var input = event.target;
      var file = input && input.files && input.files.length ? input.files[0] : null;
      var row = this.productoImageRow;

      if (!file) { return; }
      if (!row || !row.id) {
        this.$message.warning('Guarda el proyecto primero para gestionar la imagen.');
        return;
      }

      var allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
      if (allowed.indexOf(file.type) === -1) {
        this.$message.error('Solo se permiten imágenes JPG, PNG, WEBP o GIF.');
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        this.$message.error('La imagen no puede superar 5 MB.');
        return;
      }

      var fd = new FormData();
      fd.append('image', file);

      this.uploadingProductoImageId = row.id;

      this.$http.post(
        '/comercial/proyecto-productos/' + row.id + '/imagen',
        fd,
        { headers: { 'Content-Type': 'multipart/form-data' } }
      ).then(function(response) {
        if (response.data && response.data.ok) {
          self.$set(row, 'imagen', response.data.image ? response.data.image.path : row.imagen);
          self.imgVersion = Date.now(); // refresca la miniatura
          self.$message.success(response.data.message || 'Imagen guardada.');
        } else {
          self.$message.error((response.data && response.data.message) || 'No se pudo subir la imagen.');
        }
      }).catch(function(e) {
        var msg = e.response && e.response.data && e.response.data.message
          ? e.response.data.message : 'No se pudo subir la imagen.';
        self.$message.error(msg);
      }).then(function() {
        self.uploadingProductoImageId = null;
        self.productoImageRow = null;
      });
    },

    /**
     * Quita la imagen del producto (confirm + DELETE).
     */
    deleteProductoImagen(row) {
      var self = this;
      if (!row || !row.id) { return; }

      this.$confirm('¿Quitar la imagen de este producto?', 'Confirmar', {
        type: 'warning', confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar'
      }).then(function() {
        self.$http.delete('/comercial/proyecto-productos/' + row.id + '/imagen')
          .then(function(response) {
            if (response.data && response.data.ok) {
              self.$set(row, 'imagen', null);
              self.imgVersion = Date.now();
              self.$message.success(response.data.message || 'Imagen eliminada.');
            } else {
              self.$message.error((response.data && response.data.message) || 'No se pudo quitar la imagen.');
            }
          })
          .catch(function() { self.$message.error('No se pudo quitar la imagen.'); });
      }).catch(function() {});
    },

    // ============================================================
    // POST VENTA - Evaluación de Proveedores
    // ============================================================

    addEvaluacionProveedor() {
      this.form.evaluaciones_proveedor.push({
        id: null,
        proveedor_id: null,
        proveedor_nombre: '',
        estrellas: 0,
        calificacion_label: null,
        comentario: '',
        _options: [],
        _loading: false,
      });
    },
    removeEvaluacionProveedor(index) {
      this.form.evaluaciones_proveedor.splice(index, 1);
    },
    // Boton de respaldo: agrega a la lista los proveedores de los productos del
    // proyecto que aun no esten presentes, listos para calificar. Idempotente.
    cargarProveedoresDelProyecto() {
      var productos = this.form.productos || [];
      var existentes = (this.form.evaluaciones_proveedor || [])
        .map(function(e) { return e.proveedor_id; })
        .filter(function(id) { return !!id; });
      var vistos = {};
      var agregados = 0;
      var self = this;
      productos.forEach(function(p) {
        var sid = p.supplier_id;
        if (!sid) return;
        if (vistos[sid] || existentes.indexOf(sid) !== -1) return;
        vistos[sid] = true;
        self.form.evaluaciones_proveedor.push({
          id: null,
          proveedor_id: sid,
          proveedor_nombre: p.supplier_name || '',
          estrellas: 0,
          calificacion_label: null,
          comentario: '',
          _options: [{ id: sid, razon_social: p.supplier_name || '', ruc: '' }],
          _loading: false,
        });
        agregados++;
      });
      if (agregados > 0) {
        this.$message.success(agregados + ' proveedor(es) cargado(s). Calificalos y guarda el proyecto.');
      } else {
        this.$message.info('No hay proveedores nuevos para cargar (ya estan en la lista o los productos no tienen proveedor).');
      }
    },
    searchProveedoresEval(query, rowIndex) {
      var self = this;
      if (!query || query.length < 2) return;
      var row = this.form.evaluaciones_proveedor[rowIndex];
      if (!row) return;
      this.$set(row, '_loading', true);

      this.$http.get('/comercial/proyectos/search-proveedores', { params: { q: query } })
        .then(function(response) {
          var data = response.data;
          var list = (data && data.data) ? data.data : [];
          self.$set(row, '_options', list);
        })
        .catch(function() { self.$set(row, '_options', []); })
        .finally(function() { self.$set(row, '_loading', false); });
    },
    onProveedorEvalChange(rowIndex, proveedorId) {
      var row = this.form.evaluaciones_proveedor[rowIndex];
      if (!row || !proveedorId) return;
      var p = (row._options || []).find(function(x) { return x.id === proveedorId; });
      if (p) {
        this.$set(row, 'proveedor_nombre', p.razon_social);
      }
    },
    onEstrellasChange(rowIndex, estrellas) {
      var row = this.form.evaluaciones_proveedor[rowIndex];
      if (!row) return;
      // Auto-derivar la etiqueta textual a partir de las estrellas
      var label = null;
      if (estrellas >= 4) label = 'Bueno';
      else if (estrellas >= 2) label = 'Regular';
      else if (estrellas >= 1) label = 'Malo';
      this.$set(row, 'calificacion_label', label);
    },

    // ==== Proveedores por producto (M2) ====

    /**
     * Calcula los montos derivados de una línea de proveedor según la condición de
     * IGV. Misma lógica que el backend (calcularMontosProveedor).
     */
    montosProveedor(prov) {
      var IGV = 0.18;
      var qty = parseFloat(prov.cantidad) || 0;
      var unit = parseFloat(prov.precio_unitario_informado) || 0;
      var cond = prov.condicion_igv || 'pending';
      var unitNet = unit, subtotalNet = unit * qty, total = subtotalNet;
      if (cond === 'included') {
        unitNet = unit / (1 + IGV);
        subtotalNet = unitNet * qty;
        total = unit * qty;
      } else if (cond === 'excluded') {
        subtotalNet = unit * qty;
        total = subtotalNet * (1 + IGV);
      } else if (cond === 'exempt') {
        subtotalNet = unit * qty;
        total = subtotalNet;
      } else { // pending: aún no se confirma el IGV → no suma al total a pagar
        subtotalNet = unit * qty;
        total = 0;
      }
      // Redondeo a 2 decimales, igual que el backend (calcularMontosProveedor),
      // para que el detalle en vivo y el resumen cuadren con lo que se guarda.
      var r2 = function(n) { return Math.round(n * 100) / 100; };
      return {
        unitNet: r2(unitNet),
        subtotalNeto: r2(subtotalNet),
        igv: r2(Math.max(total - subtotalNet, 0)),
        total: r2(total),
      };
    },
    /**
     * Recalcula los totales del producto = suma de los proveedores ACTIVOS.
     */
    recalcProducto(row) {
      if (!row) return;
      var self = this;
      var sumUnit = 0, sumTotal = 0;
      (row.proveedores || []).forEach(function(pr) {
        if (!pr.activo) return;
        sumUnit += parseFloat(pr.precio_unitario_informado) || 0;
        sumTotal += self.montosProveedor(pr).total;
      });
      row.precio_unitario = Math.round(sumUnit * 100) / 100;
      row.subtotal = Math.round(sumTotal * 100) / 100;
    },
    contarProveedoresActivos(row) {
      return ((row && row.proveedores) || []).filter(function(p) { return p.activo; }).length;
    },
    condicionIgvLabel(cond) {
      var map = { included: 'Incluye IGV', excluded: 'No incluye IGV', exempt: 'No aplica IGV', pending: 'Por confirmar' };
      return map[cond] || cond || '—';
    },
    proveedorRowClass(obj) {
      return (obj && obj.row && !obj.row.activo) ? 'prov-row-historial' : '';
    },
    addProveedor(row) {
      if (!Array.isArray(row.proveedores)) this.$set(row, 'proveedores', []);
      row.proveedores.push({
        id: null, supplier_id: null, supplier_name: '', concepto: '',
        // Agosto 2026 (punto 20). Vacío a propósito: un proveedor agregado a mano
        // no viene del requerimiento, así que no hay plazo que heredar. El usuario
        // lo escribe cuando el proveedor le confirma el suyo.
        tiempo_produccion: '',
        cantidad: parseFloat(row.cantidad) || 1, precio_unitario_informado: 0,
        condicion_igv: 'pending', activo: true, reemplaza_a_id: null, motivo_reemplazo: null,
        _options: [], _loading: false,
      });
    },
    /**
     * Cambia un proveedor conservando el historial: el actual pasa a inactivo
     * (queda visible atenuado) y se agrega uno nuevo activo para elegir el reemplazo.
     */
    cambiarProveedor(row, prov, index) {
      var self = this;
      this.$prompt('Motivo del cambio (opcional):', 'Cambiar proveedor', {
        confirmButtonText: 'Cambiar',
        cancelButtonText: 'Cancelar',
        inputPlaceholder: 'Ej.: no respondió, rechazó el trabajo, no puede continuar...'
      }).then(function(r) {
        var motivo = (r && r.value) ? r.value : null;
        self.$set(prov, 'activo', false);
        var nuevo = {
          id: null, supplier_id: null, supplier_name: '', concepto: prov.concepto || '',
          // Agosto 2026 (punto 20): el plazo NO se arrastra al reemplazo. Ahora que
          // este campo es el tiempo de producción del proveedor, el que entra tiene
          // el suyo propio y hay que preguntárselo — igual que el precio, que
          // también arranca en cero porque tiene que cotizar de nuevo.
          tiempo_produccion: '',
          cantidad: prov.cantidad, precio_unitario_informado: 0, condicion_igv: 'pending',
          activo: true, reemplaza_a_id: prov.id || null, motivo_reemplazo: motivo,
          _options: [], _loading: false,
        };
        row.proveedores.splice(index + 1, 0, nuevo);
        self.recalcProducto(row);
      }).catch(function() {});
    },
    removeProveedor(row, index) {
      var activos = (row.proveedores || []).filter(function(p) { return p.activo; });
      if (activos.length <= 1) {
        this.$message.warning('Debe quedar al menos un proveedor activo en el producto.');
        return;
      }
      row.proveedores.splice(index, 1);
      this.recalcProducto(row);
    },
    searchProveedoresProd(query, prov) {
      var self = this;
      if (!query || query.length < 2) return;
      this.$set(prov, '_loading', true);
      this.$http.get('/comercial/proyectos/search-proveedores', { params: { q: query } })
        .then(function(response) {
          var data = response.data;
          self.$set(prov, '_options', (data && data.data) ? data.data : []);
        })
        .catch(function() { self.$set(prov, '_options', []); })
        .finally(function() { self.$set(prov, '_loading', false); });
    },
    onProveedorProdChange(prov, val) {
      if (!val) { this.$set(prov, 'supplier_name', ''); return; }
      var op = (prov._options || []).find(function(x) { return x.id === val; });
      if (op) this.$set(prov, 'supplier_name', op.razon_social);
    },

    calcularSubtotalProducto(producto) {
      var cantidad = parseFloat(producto.cantidad) || 0;
      var precio = parseFloat(producto.precio_unitario) || 0;
      producto.subtotal = cantidad * precio;
    },
    getProductosSummary(param) {
      var columns = param.columns;
      var data = param.data;
      var sums = [];
      var self = this;

      columns.forEach(function(column, index) {
        if (index === 0) {
          sums[index] = 'Total';
          return;
        }
        if (column.property === 'subtotal') {
          var total = 0;
          for (var i = 0; i < data.length; i++) {
            total += parseFloat(data[i].subtotal) || 0;
          }
          sums[index] = 'S/ ' + self.formatMonto(total);
        } else {
          sums[index] = '';
        }
      });

      return sums;
    },
    async save() {
      if (!this.form.empresa_id) {
        this.$message.error('La empresa es obligatoria');
        this.activeTab = 'datos';
        return;
      }
      if (!this.form.nombre) {
        this.$message.error('El nombre del proyecto es obligatorio');
        this.activeTab = 'datos';
        return;
      }

      // P7: la suma de montos por razón social debe coincidir con el Monto Real Cotización.
      if (this.form.razones_sociales && this.form.razones_sociales.length) {
        if (!this.montosRSCuadran) {
          this.$message.error('La suma de los montos por razón social (S/ ' + this.formatMonto(this.sumaMontosRS) + ') no coincide con el Monto Real Cotización (S/ ' + this.formatMonto(this.montoRealEfectivo) + ').');
          this.activeTab = 'facturacion';
          return;
        }
        // P8: si "Cada R.S. con O/C", cada razón social debe tener su O/C cargada.
        if (this.form.cada_rs_con_oc) {
          var faltaOc = this.form.razones_sociales.some(function(rs) {
            return rs.tiene_oc !== 'Se tiene' || !rs.oc_numero;
          });
          if (faltaOc) {
            this.$message.error('Marcaste "Cada R.S. con O/C": cada razón social debe tener estado "Se tiene" y su N° de O/C.');
            this.activeTab = 'facturacion';
            return;
          }
        }
      }

      this.saving = true;
      try {
        let response;
        if (this.isEdit) {
          response = await this.$http.put(`/comercial/proyectos/record/${this.proyectoId}`, this.form);
        } else {
          response = await this.$http.post('/comercial/proyectos/record', this.form);
        }

        if (response.data.success) {
          this.$message.success(response.data.message);
          this.$emit('saved');
        } else {
          this.$message.error(response.data.message || 'Error al guardar');
        }
      } catch (error) {
        if (error.response && error.response.data && error.response.data.errors) {
          const errors = error.response.data.errors;
          const firstError = Object.values(errors)[0];
          this.$message.error(firstError[0]);
        } else {
          this.$message.error('Error al guardar el proyecto');
        }
        console.error(error);
      } finally {
        this.saving = false;
      }
    },
    handleClose() {
      this.dialogVisible = false;
    },
    formatMonto(value) {
      if (!value) return '0.00';
      return parseFloat(value).toLocaleString('es-PE', { minimumFractionDigits: 2 });
    },
    getEventType(event) {
      const types = {
        'created': 'success',
        'updated': 'warning',
        'deleted': 'danger'
      };
      return types[event] || 'info';
    },
    formatFieldName(field) {
      const names = {
        'nombre': 'Nombre',
        'empresa_id': 'Empresa',
        'etapa': 'Etapa',
        'semaforo': 'Semáforo',
        'monto_total': 'Monto Total',
        'porcentaje_avance': '% Avance',
      };
      return names[field] || field.replace(/_/g, ' ');
    }
  }
};
</script>

<style scoped>
.producto-specs {
  font-size: 13px;
  color: #606266;
  padding: 4px 8px;
}
.producto-specs__title {
  font-weight: 600;
  color: #909399;
  font-size: 12px;
  margin-bottom: 8px;
}
.spec-label {
  font-weight: 600;
  color: #303133;
}

/* ==== M2: proveedores por producto ==== */
.proveedores-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin: 16px 0 8px;
}
.proveedores-hint {
  font-size: 11px;
  color: #909399;
  line-height: 1.4;
  margin-top: 2px;
  max-width: 640px;
}
.proveedores-tabla {
  margin-bottom: 10px;
}
.prov-historial {
  color: #a0a4ab;
  font-size: 12px;
  font-style: italic;
}
.proveedores-tabla >>> .prov-row-historial {
  background: #fafafa;
  color: #a0a4ab;
}
.prov-pending {
  color: #e6a23c;
  font-size: 12px;
  font-weight: 600;
}
.producto-totales {
  display: flex;
  gap: 28px;
  justify-content: flex-end;
  padding: 8px 2px 2px;
  font-size: 13px;
  color: #303133;
}
.resumen-proyecto {
  max-width: 420px;
  margin: 18px 0 4px auto;
  border: 1px solid #ebeef5;
  border-radius: 8px;
  overflow: hidden;
  background: #fbfcff;
}
.resumen-proyecto__title {
  padding: 12px 14px;
  background: #f5f7fa;
  border-bottom: 1px solid #ebeef5;
  font-weight: 700;
  color: #303133;
}
.resumen-proyecto__line {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  padding: 9px 14px;
  border-bottom: 1px solid #ebeef5;
  font-size: 13px;
}
.resumen-proyecto__line:last-child {
  border-bottom: none;
}
.resumen-proyecto__total {
  background: #ecf5ff;
  color: #409EFF;
  font-weight: 800;
  font-size: 14px;
}

/* ==== M3: bitácora "Cambios e indicaciones" ==== */
.bitacora-lista { margin-top: 14px; }
.bitacora-vacia { color: #909399; font-size: 13px; padding: 8px 0; }
.bitacora-item {
  border: 1px solid #ebeef5;
  border-radius: 8px;
  padding: 10px 12px;
  margin-bottom: 8px;
  background: #fbfcff;
}
.bitacora-item__contenido {
  white-space: pre-wrap;
  color: #303133;
  font-size: 13px;
  line-height: 1.5;
}
.bitacora-item__meta {
  margin-top: 6px;
  font-size: 11px;
  color: #909399;
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}
.bitacora-item__editado { color: #b0863c; }
.bitacora-item__editar { margin-left: auto; }
.producto-imagen {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.producto-imagen__thumb-wrap {
  width: 96px;
  height: 96px;
  border: 1px solid #dcdfe6;
  border-radius: 4px;
  overflow: hidden;
  background: #f5f7fa;
  display: flex;
  align-items: center;
  justify-content: center;
}
.producto-imagen__thumb {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  display: block;
}
.producto-imagen__empty {
  width: 96px;
  height: 96px;
  border: 1px dashed #dcdfe6;
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c0c4cc;
  font-size: 12px;
  background: #fafafa;
}
.producto-imagen__actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.producto-imagen__hint {
  font-size: 11px;
  color: #909399;
  margin-top: 6px;
}
.producto-imagen__note {
  font-size: 12px;
  color: #909399;
  font-style: italic;
}
.stats-row {
  display: flex;
  gap: 15px;
}
.stat-box {
  flex: 1;
  padding: 15px;
  border: 1px solid #e0e0e0;
  border-radius: 4px;
  text-align: center;
  background: #fafafa;
}
.stat-value {
  font-size: 20px;
  font-weight: bold;
  color: #333;
}
.stat-label {
  font-size: 12px;
  color: #666;
  margin-top: 4px;
  margin-bottom: 8px;
}
.tab-content-inner {
  padding: 10px 0;
}
.section-title {
  font-size: 16px;
  font-weight: 600;
  margin-bottom: 20px;
  padding-bottom: 10px;
  border-bottom: 1px solid #e0e0e0;
}
.subsection-title {
  font-size: 14px;
  font-weight: 600;
  color: #666;
  margin-bottom: 15px;
}
.form-group {
  margin-bottom: 15px;
}
.form-group label {
  display: block;
  margin-bottom: 5px;
  font-size: 13px;
  color: #606266;
}
.form-group label.lbl-help {
  cursor: help;
}
.form-group label.lbl-help .field-tip-icon {
  color: #909399;
  font-size: 13px;
  margin-left: 3px;
  vertical-align: middle;
  cursor: help;
}
.form-group .required {
  color: #f56c6c;
}
.form-group .el-select,
.form-group .el-input,
.form-group .el-input-number {
  width: 100%;
}
.checkbox-group {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  padding: 12px;
  background: #f5f7fa;
  border-radius: 4px;
}
.dialog-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.footer-info {
  font-size: 12px;
  color: #909399;
}
.change-item {
  font-size: 12px;
  margin-bottom: 4px;
}
.old-value {
  color: #f56c6c;
  text-decoration: line-through;
}
.new-value {
  color: #67c23a;
}

/* Campos deshabilitados (Código, Empresa, Contacto en Datos Generales):
   texto legible en vez del gris muy claro por defecto de Element UI.
   Se usa también -webkit-text-fill-color porque algunos navegadores
   ignoran 'color' en inputs disabled. */
.form-group >>> .el-input.is-disabled .el-input__inner,
.form-group >>> .el-textarea.is-disabled .el-textarea__inner {
  color: #5a5e66;
  -webkit-text-fill-color: #5a5e66;
}

/* P9 — Productos del proyecto en solo lectura (cuando provienen de la cotización
   aprobada del requerimiento): texto legible en vez del gris muy claro de Element UI. */
.productos-tabla >>> .el-input.is-disabled .el-input__inner,
.productos-tabla >>> .el-input-number.is-disabled .el-input__inner {
  color: #5a5e66;
  -webkit-text-fill-color: #5a5e66;
}

/* Agosto 2026 (punto 19): ayuda bajo la plantilla de observaciones. Discreta a
   propósito: explica el marcador sin competir con el contenido del campo. */
.obs-ayuda {
  font-size: 11px;
  color: #909399;
  margin-top: 4px;
  line-height: 1.4;
}
.obs-ayuda code {
  background: #f4f4f5;
  padding: 0 3px;
  border-radius: 2px;
  color: #606266;
}
</style>
