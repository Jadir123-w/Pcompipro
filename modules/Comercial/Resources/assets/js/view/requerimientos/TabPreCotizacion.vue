<template>
  <div v-loading="loading" class="tab-precotizacion">

    <!-- Totales SIEMPRE arriba (barra fija): mismo orden y formato compacto
         de la referencia (Estado → … → TOTAL). -->
    <div v-if="prequote" class="precot-totals">
      <div class="precot-totals__grid">
        <div class="precot-total">
          <span class="precot-total__label">Estado</span>
          <el-tag :type="estadoTagType(prequote.estado)" size="mini">{{ prequote.estado }}</el-tag>
        </div>
        <div class="precot-total">
          <span class="precot-total__label">Movilidad</span>
          <span :title="tituloEnVivo">S/ {{ resumenCostoReal.movilidad.toFixed(2) }}</span>
        </div>
        <div class="precot-total">
          <span class="precot-total__label">Empaque</span>
          <span :title="tituloEnVivo">S/ {{ resumenCostoReal.empaque.toFixed(2) }}</span>
        </div>
        <div class="precot-total">
          <span class="precot-total__label">Seguro</span>
          <span :title="tituloEnVivo">S/ {{ resumenCostoReal.seguro.toFixed(2) }}</span>
        </div>
        <div class="precot-total precot-total--wide">
          <span class="precot-total__label" title="Promedio simple del Margen % ingresado por ítem.">Prom. margen <i class="el-icon-info"></i></span>
          <span>{{ promedioMargenIngresado.toFixed(2) }}%</span>
        </div>
        <div class="precot-total">
          <span class="precot-total__label">Margen ganancia</span>
          <span style="color:#67C23A;">S/ {{ parseFloat(prequote.ganancia_total || 0).toFixed(2) }}</span>
        </div>
        <div class="precot-total">
          <span class="precot-total__label">Utilidad real</span>
          <span :title="tituloEnVivo" style="color:#67C23A;">S/ {{ resumenCostoReal.utilidadReal.toFixed(2) }}</span>
        </div>
        <div class="precot-total">
          <span class="precot-total__label">Margen real</span>
          <span :title="tituloEnVivo">{{ margenRealTotalPct.toFixed(2) }}%</span>
        </div>
        <div class="precot-total">
          <span class="precot-total__label">Subtotal</span>
          <span>S/ {{ parseFloat(prequote.subtotal).toFixed(2) }}</span>
        </div>
        <div class="precot-total">
          <span class="precot-total__label">IGV ({{ igvPct }}%)</span>
          <span>S/ {{ parseFloat(prequote.total_igv).toFixed(2) }}</span>
        </div>
        <div class="precot-total precot-total--total">
          <span class="precot-total__label">Total</span>
          <strong>S/ {{ parseFloat(prequote.total).toFixed(2) }}</strong>
        </div>
      </div>
    </div>

    <!-- ═══ Ingreso: 2 acordeones lado a lado ═══
         Izq: "Referencia del producto" por IA (texto XOR imagen), sin borrar los existentes.
         Der: crear kits (SOLO FRONTAL por ahora; el backend llega después).
         Se muestra en arranque en frío (sin prequote) y con prequote editable. -->
    <el-row :gutter="12" class="mb-2">
      <el-col :xs="24" :md="24" v-if="refReqId && !loading && (!prequote || isEditable)">
        <el-collapse v-model="iaCollapseOpen" class="precot-collapse">
          <el-collapse-item name="ia">
            <template slot="title">
              <span><i class="el-icon-magic-stick" style="color:#4f46e5;"></i> <strong>Ingresar productos con IA</strong></span>
              <small style="color:#909399; margin-left:auto; padding-right:8px;">Texto o imagen → nuevo ítem</small>
            </template>

            <!-- Modo TEXTO (o vacío): textarea + zona de drop de imagen -->
            <div v-if="referenceMode !== 'imagen'"
                 @dragover.prevent @drop.prevent="onReferenceDrop"
                 style="border:2px dashed #c7d2fe; border-radius:6px; padding:10px; background:#f9fafb;">
              <el-input v-model="referenceText" type="textarea" :autosize="{ minRows: 1, maxRows: 6 }"
                        :disabled="interpretingItem"
                        placeholder="Escribe el producto (ej. '50 llaveros metálicos con logo') o arrastra una imagen aquí"
                        @input="onReferenceTextInput"></el-input>
              <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:8px;">
                <small style="color:#6b7280;">También podés arrastrar una imagen sobre este recuadro.</small>
              </div>
            </div>

            <!-- Modo IMAGEN: preview + quitar -->
            <div v-else style="display:flex; align-items:center; gap:14px; border:2px solid #34d399; border-radius:6px; padding:12px; background:#ecfdf5;">
              <img :src="referenceImagePreview" alt="Referencia"
                   style="width:110px; height:82px; object-fit:cover; border-radius:6px; border:1px solid #d1d5db; background:#fff;"/>
              <div style="flex:1;">
                <strong style="color:#065f46;">Imagen cargada como referencia</strong>
                <p style="color:#6b7280; margin:4px 0; font-size:12px;">Se interpretará para generar un nuevo ítem. Para escribir texto, primero quitá la imagen.</p>
                <el-button size="mini" type="danger" plain icon="el-icon-delete"
                           :disabled="interpretingItem" @click="removeReferenceImage">Quitar imagen</el-button>
              </div>
            </div>

            <input ref="referenceImageInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif"
                   style="display:none;" @change="onReferenceImageSelected"/>

            <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:10px;">
              <el-tag size="small" effect="plain"
                      :type="referenceMode === 'texto' ? '' : (referenceMode === 'imagen' ? 'success' : 'info')">
                {{ referenceMode === 'texto' ? 'Referencia detectada: Texto' : (referenceMode === 'imagen' ? 'Referencia detectada: Imagen' : 'Sin referencia') }}
              </el-tag>
              <el-button icon="el-icon-plus" :loading="addingManual"
                         :disabled="addingManual || interpretingItem" @click="addManualItem">
                {{ addingManual ? 'Agregando…' : 'Ítem manual' }}
              </el-button>
              <el-button type="primary" icon="el-icon-plus" :loading="interpretingItem"
                         :disabled="!referenceMode" @click="interpretItem">
                {{ interpretingItem ? 'Interpretando…' : 'Interpretar ítem' }}
              </el-button>
            </div>
          </el-collapse-item>
        </el-collapse>
      </el-col>
      <!-- [NUEVO FLUJO 2026-10-21] Kits ocultados momentaneamente (pedido del jefe).
           El código queda intacto: solo se oculta la UI de creación/agrupación. -->
      <el-col v-if="false" :xs="24" :md="12">
        <el-collapse v-model="kitCollapseOpen" class="precot-collapse">
          <el-collapse-item name="kit">
            <template slot="title">
              <span><i class="el-icon-goods" style="color:#e6a23c;"></i> <strong>Agrupación de kits</strong></span>
              <small style="color:#909399; margin-left:auto; padding-right:8px;">{{ kitSelection.length }} seleccionados</small>
            </template>
            <div class="kit-bar">
              <div class="kit-bar__row">
                <span class="kit-bar__label">Acción</span>
                <el-select v-model="kitAction" size="mini" style="width:130px;">
                  <el-option label="Nuevo kit" value="new"></el-option>
                  <el-option v-for="k in savedKits" :key="k.id" :label="'Agregar a: ' + k.nombre" :value="'add:' + k.id"></el-option>
                </el-select>
                <span class="kit-bar__label">Nombre del kit</span>
                <el-input v-model="kitNameInput" size="mini" placeholder=""
                          style="flex:1;" maxlength="191"></el-input>
                <el-button size="mini" type="primary" icon="el-icon-plus"
                           :loading="kitCreating" :disabled="!isEditable"
                           @click="kitAction === 'new' ? createKitFromSelection() : addSelectionToKit()">
                  {{ kitAction === 'new' ? 'Crear kit' : 'Agregar' }}
                </el-button>
              </div>
              <div class="kit-bar__row kit-bar__sub">
                <span v-if="kitSelection.length">{{ kitSelection.length }} seleccionado(s)</span>
                <span v-else>Marca 2 o más productos (no comisiones) con el check Kit.</span>
                <el-button v-if="kitSelection.length" size="mini" type="text" @click="kitSelection = []">Deseleccionar</el-button>
              </div>
            </div>
          </el-collapse-item>
        </el-collapse>
      </el-col>
    </el-row>

    <!-- Sin precotización: botón para generar -->
    <div v-if="!prequote && !loading" style="text-align:center; padding:30px 0;">
      <p style="color:#888; margin-bottom:15px;">Este requerimiento aún no tiene precotización generada.</p>
      <el-button type="primary" icon="el-icon-magic-stick" @click="generatePrequote" :loading="generating">
        Generar Pre-Cotización con IA
      </el-button>
      <el-button icon="el-icon-plus" @click="addManualItem" :loading="addingManual" style="margin-left:8px;">
        {{ addingManual ? 'Agregando…' : 'Agregar ítem manual' }}
      </el-button>
    </div>

    <!-- Con precotización: editor -->
    <div v-if="prequote">

      <!-- Input file compartido para la imagen POR ÍTEM (mismo patrón que la del requerimiento) -->
      <input ref="itemImageInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif"
             style="display:none;" @change="onItemImageSelected"/>
      <!-- Input file para la foto propia del kit -->
      <input ref="kitPhotoInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif"
             style="display:none;" @change="onKitPhotoSelected"/>

      <!-- Aviso si hay items que necesitan revisión -->
      <el-alert v-if="itemsNeedingReview > 0" type="warning" show-icon :closable="false"
                style="margin-bottom: 12px;">
        <template slot="title">
          <strong>{{ itemsNeedingReview }} producto(s) necesitan tu revisión.</strong>
          La IA encontró varias coincidencias en el catálogo y no pudo elegir con certeza.
          Revisa los ítems marcados con la etiqueta amarilla "Revisar producto" para asociar el correcto.
        </template>
      </el-alert>

      <!-- Aviso: hay ítems con detalles técnicos inferidos por IA -->
      <el-alert v-if="itemsWithInferred > 0" type="info" show-icon :closable="false"
                style="margin-bottom: 12px;" class="ai-inferred-alert">
        <template slot="title">
          <strong>{{ itemsWithInferred }} ítem(s) tienen detalles técnicos inferidos por la IA.</strong>
          Haz click en la flecha <i class="el-icon-arrow-right"></i> al inicio de cada fila
          para revisar los detalles (material, medidas, empaque). Los campos marcados con
          <i class="el-icon-magic-stick" style="color:#f5c842;"></i> son sugerencias del sistema
          y deben confirmarse con el cliente antes de emitir.
        </template>
      </el-alert>

      <!-- Dialog preview imagen -->
      <el-dialog :visible.sync="imagePreviewVisible" width="640px" append-to-body
                 custom-class="image-ref-preview-dialog" title="Imagen del requerimiento">
        <img v-if="prequote && prequote.reference_image_path" :src="referenceImageUrl"
             style="max-width:100%; max-height:70vh; display:block; margin:0 auto;"/>
      </el-dialog>

      <!-- Leyenda de colores por rol de campo -->
      <div class="campos-leyenda">
        <span class="campos-leyenda__item"><span class="campos-leyenda__dot campos-leyenda__dot--precio"></span> Afecta el precio</span>
        <span class="campos-leyenda__item"><span class="campos-leyenda__dot campos-leyenda__dot--descriptivo"></span> Descriptivo</span>
        <span class="campos-leyenda__item"><span class="campos-leyenda__dot campos-leyenda__dot--calculado"></span> Calculado por el sistema</span>
        <span class="campos-leyenda__item"><i class="el-icon-magic-stick" style="color:#f5c842;"></i> Inferido por IA (confirmar)</span>
      </div>

      <!-- SCTR editable de la cotización (COM-02 R3) — se aplica a todos los ítems -->
      <div class="sctr-cotizacion" v-if="prequote">
        <span class="sctr-cotizacion__label" title="Seguro Complementario de Trabajo de Riesgo. Se aplica a toda la cotización; por defecto toma el valor configurado en el cotizador.">
          <i class="el-icon-umbrella"></i> Seguro SCTR (%)
        </span>
        <el-input-number v-model="sctrPct" size="mini" :precision="2" :step="0.5" :min="0" :max="10"
                         controls-position="right" :disabled="!isEditable" @change="saveSctr"
                         style="width:120px;"></el-input-number>
        <span class="sctr-cotizacion__hint">
          Aplica a toda la cotización<template v-if="prequote.sctr_config_default !== null && prequote.sctr_config_default !== undefined"> · por defecto: {{ prequote.sctr_config_default }}%</template>
        </span>
      </div>

      <!-- Tabla de items -->
      <el-table :data="displayRows()" stripe border size="small" class="mb-2 items-table" row-key="displayId">

        <!-- Fila expandible: detalles técnicos del ítem con badge "Inferido por IA" -->
        <el-table-column type="expand" width="40">
          <template slot-scope="props">
            <div v-if="props.row._isKit" class="kit-detail">
              <div class="kit-detail__header">
                <span class="kit-detail__title">
                  <i class="el-icon-goods"></i>
                  <strong>Kit de pre-cotización</strong>
                </span>
                <span>
                  <el-tag v-if="kitIsDirty(props.row.kit)" type="warning" size="mini" effect="plain" style="margin-right:8px;">
                    Sin guardar
                  </el-tag>
                  <el-button v-if="isEditable" size="mini" type="success" icon="el-icon-check"
                             :loading="!!kitSaving[props.row.kit._kid]"
                             @click="saveKit(props.row.kit)">Guardar kit</el-button>
                  <span class="ai-inferred-details__hint" style="margin-left:8px;">Margen % del kit es solo lectura</span>
                </span>
              </div>

              <!-- Métricas consolidadas -->
              <div class="kit-summary">
                <div class="metric"><span>Subtotal kit</span><strong>S/ {{ kitMetrics(props.row.kit).subtotal.toFixed(2) }}</strong></div>
                <div class="metric"><span>Costo Real Total (Q+logística)</span><strong>S/ {{ kitMetrics(props.row.kit).costoReal.toFixed(2) }}</strong></div>
                <div class="metric"><span>Utilidad real</span><strong>S/ {{ kitMetrics(props.row.kit).utilidad.toFixed(2) }}</strong></div>
                <div class="metric"><span>Margen real</span><strong>{{ kitMetrics(props.row.kit).margenPct.toFixed(2) }}%</strong></div>
                <div class="metric"><span>Precio para 40%</span><strong>S/ {{ kitMetrics(props.row.kit).precio40.toFixed(2) }}</strong></div>
                <div class="metric"><span>Cuánto subir</span><strong>S/ {{ kitMetrics(props.row.kit).subir.toFixed(2) }}</strong></div>
                <div class="metric"><span>Unitario 40%</span><strong>S/ {{ kitMetrics(props.row.kit).unitario40.toFixed(2) }} / u</strong></div>
              </div>

              <div class="kit-detail__grid">
                <div class="ai-field">
                  <label class="lbl-help">Nombre comercial del kit</label>
                  <el-input v-model="props.row.kit.nombre" size="mini" :disabled="!isEditable" maxlength="191"></el-input>
                </div>
                <div class="ai-field">
                  <label class="lbl-help" title="Al guardar se aplica a todos los componentes.">Cantidad del kit</label>
                  <el-input-number v-model="props.row.kit.cantidad" size="mini" :min="1" :max="999999"
                                   controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                </div>
                <div class="ai-field">
                  <label class="lbl-help">Empaque / Presentación del kit</label>
                  <el-input v-model="props.row.kit.presentacion" size="mini" :disabled="!isEditable"
                            placeholder="Ej: Caja individual, caja grupal..." maxlength="191"></el-input>
                </div>
                <div class="ai-field">
                  <label class="lbl-help">Descripción general</label>
                  <el-input v-model="props.row.kit.descripcion" size="mini" type="textarea"
                            :autosize="{minRows:2,maxRows:4}" :disabled="!isEditable"></el-input>
                </div>
              </div>

              <!-- Foto propia del kit -->
              <div class="kit-detail__photo">
                <img v-if="kitPhotoUrl(props.row.kit)" :src="kitPhotoUrl(props.row.kit)" alt="foto kit"
                     class="kit-photo-thumb"/>
                <span v-else class="item-foto-empty"><i class="el-icon-picture-outline"></i></span>
                <div>
                  <el-button v-if="isEditable && !props.row.kit._isNew" size="mini" icon="el-icon-upload2"
                             :loading="uploadingKitPhotoId === props.row.kit.id"
                             @click="triggerKitPhotoUpload(props.row.kit)">
                    {{ kitPhotoUrl(props.row.kit) ? 'Cambiar foto' : 'Subir foto' }}
                  </el-button>
                  <small v-if="isEditable && props.row.kit._isNew" style="color:#909399;">Guarda el kit para subirle foto.</small>
                </div>
              </div>

              <!-- Componentes -->
              <div class="kit-detail__subtitle"><strong>Componentes del kit</strong></div>
              <el-table row-key="id" :data="kitMemberItems(props.row.kit)" size="mini" border style="width:100%;">
                <el-table-column type="expand" width="40">
                  <template slot-scope="mscope">
                    <!-- MANTIENE SINCRONÍA: copia del detalle del ítem (ai-inferred-details).
                         memberDetailScope devuelve SIEMPRE el mismo envoltorio por
                         ítem (cacheado): con un literal inline el alias se recreaba
                         en cada render y los inputs perdían el foco al tipear. -->
                    <template v-for="props in memberDetailScope(mscope.row)">
            <div class="ai-inferred-details" :key="'kd-' + mscope.row.id">
              <div class="ai-inferred-details__header">
                <span class="ai-inferred-details__title">
                  <i class="el-icon-document-copy"></i>
                  <strong>Detalles técnicos del producto</strong>
                </span>
                <span v-if="inferredFields(props.row).length > 0"
                      class="ai-inferred-details__warning">
                  <i class="el-icon-warning-outline"></i>
                  {{ inferredFields(props.row).length }} campo(s) inferido(s) por la IA — confirma con el cliente antes de emitir
                </span>
              </div>

              <div class="erp-detail-layout">
                <div class="erp-detail-main">
                  <div class="erp-tech-grid" v-if="!isComision(props.row) && !isService(props.row)">
                    <div class="ai-field">
                      <label class="lbl-help" title="Material del producto. Si lo sugirió la IA, confírmalo con el cliente.">Material <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input v-model="props.row.material" size="mini" :disabled="!isEditable"
                                placeholder="Ej: algodón, dry fit, plástico..."
                                @input="onFieldEdited(props.row, 'material')"
                                :class="{ 'ai-inferred-input': isInferred(props.row, 'material') }">
                        <el-tooltip v-if="isInferred(props.row, 'material')" slot="suffix"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon"></i>
                        </el-tooltip>
                      </el-input>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Medida o tamaño del producto.">Medida / Tamaño <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input v-model="props.row.medida" size="mini" :disabled="!isEditable"
                                placeholder="Ej: 500ml, A5, 30x40cm..."
                                @input="onFieldEdited(props.row, 'medida')"
                                :class="{ 'ai-inferred-input': isInferred(props.row, 'medida') }">
                        <el-tooltip v-if="isInferred(props.row, 'medida')" slot="suffix"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon"></i>
                        </el-tooltip>
                      </el-input>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Detalle de la personalización solicitada (texto, logo, color, etc.).">Personalización <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input v-model="props.row.personalizacion" size="mini" :disabled="!isEditable"
                                placeholder="Ej: logo bordado, texto..."
                                @input="onFieldEdited(props.row, 'personalizacion')"
                                :class="{ 'ai-inferred-input': isInferred(props.row, 'personalizacion') }">
                        <el-tooltip v-if="isInferred(props.row, 'personalizacion')" slot="suffix"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon"></i>
                        </el-tooltip>
                      </el-input>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Técnica de impresión o aplicación (serigrafía, bordado, sublimado, etc.).">Técnica <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input v-model="props.row.tecnica_impresion" size="mini" :disabled="!isEditable"
                                placeholder="Ej: serigrafía, bordado, sublimado..."
                                @input="onFieldEdited(props.row, 'tecnica_impresion')"
                                :class="{ 'ai-inferred-input': isInferred(props.row, 'tecnica_impresion') }">
                        <el-tooltip v-if="isInferred(props.row, 'tecnica_impresion')" slot="suffix"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon"></i>
                        </el-tooltip>
                      </el-input>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Forma de empaque o presentación del producto.">Empaque / Presentación <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input v-model="props.row.empaque" size="mini" :disabled="!isEditable"
                                placeholder="Ej: bolsa individual, caja..."
                                @input="onFieldEdited(props.row, 'empaque')"
                                :class="{ 'ai-inferred-input': isInferred(props.row, 'empaque') }">
                        <el-tooltip v-if="isInferred(props.row, 'empaque')" slot="suffix"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon"></i>
                        </el-tooltip>
                      </el-input>
                    </div>
                    <div class="erp-tech-minirow">
                      <div class="ai-field ai-field--with-outside-icon">
                        <label class="lbl-help" title="Cantidad de colores de la impresión (afecta el costo).">Colores <i class="el-icon-info field-tip-icon"></i></label>
                        <el-input-number v-model="props.row.cantidad_colores" size="mini" :min="1" :max="20"
                                         controls-position="right" :disabled="!isEditable"
                                         @change="onFieldEdited(props.row, 'cantidad_colores')"
                                         :class="{ 'ai-inferred-input': isInferred(props.row, 'cantidad_colores') }">
                        </el-input-number>
                        <el-tooltip v-if="isInferred(props.row, 'cantidad_colores')"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon ai-inferred-icon--outside"></i>
                        </el-tooltip>
                      </div>
                      <div class="ai-field ai-field--with-outside-icon">
                        <label class="lbl-help" title="Cantidad de lados o caras a personalizar.">Lados <i class="el-icon-info field-tip-icon"></i></label>
                        <el-input-number v-model="props.row.cantidad_lados" size="mini" :min="1" :max="6"
                                         controls-position="right" :disabled="!isEditable"
                                         @change="onFieldEdited(props.row, 'cantidad_lados')"
                                         :class="{ 'ai-inferred-input': isInferred(props.row, 'cantidad_lados') }">
                        </el-input-number>
                        <el-tooltip v-if="isInferred(props.row, 'cantidad_lados')"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon ai-inferred-icon--outside"></i>
                        </el-tooltip>
                      </div>
                      <div class="ai-field">
                        <label class="lbl-help" title="Factor manual de la impresión (millares). Multiplica el Costo Total de Impresión: costo × colores × lados × millar. Por defecto 1.">Millar <i class="el-icon-info field-tip-icon"></i></label>
                        <el-input-number v-model="props.row.cantidad_millar" size="mini" :precision="2" :step="1" :min="0"
                                         controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                      </div>
                    </div>
                  </div>
                  <div class="erp-tech-duo">
                      <div class="ai-field">
                        <label class="lbl-help" title="Descripción comercial o especificaciones del producto.">Descripción <i class="el-icon-info field-tip-icon"></i></label>
                        <el-input v-model="props.row.product_description" size="mini" :disabled="!isEditable"
                                  type="textarea" :autosize="{minRows: 2, maxRows: 6}"
                                  placeholder="Descripción comercial o especificaciones del producto..."
                                  @input="onFieldEdited(props.row, 'product_description')"
                                  :class="{ 'ai-inferred-input': isInferred(props.row, 'product_description') }">
                        </el-input>
                      </div>
                      <div class="ai-field erp-obs" v-if="!isComision(props.row)">
                        <label class="lbl-help" title="Notas internas de esta línea; no se muestran al cliente.">Observaciones <i class="el-icon-info field-tip-icon"></i></label>
                        <el-input v-model="props.row.observaciones" size="mini" :disabled="!isEditable"
                                  type="textarea" :autosize="{minRows: 2, maxRows: 6}"
                                  placeholder="Notas internas para esta línea (opcional)">
                        </el-input>
                      </div>
                    </div>

              <!-- Agosto 2026 (punto 2) — kits y servicio de armado.
                   Un kit son varios productos agrupados solo para esta cotización
                   y el armado es un servicio puntual: ninguno es un producto del
                   catálogo. El check "No registrar..." se retiró: esa función vive
                   ahora en el combo Tipo = Servicio (al convertir NO se le crea
                   un producto propio). La línea conserva igual este nombre. -->

              <!-- ───────────── Mi Costeo del ítem (formato mockup) ───────────── -->
              <!-- Producto: costeo completo. Servicio: solo Costo Base, Cantidad y Margen. -->
              <div class="costeo-section erp-cost" v-if="props.row.tipo_linea === 'product'">
                <div class="costeo-section__title">
                  <i class="el-icon-coin"></i> <strong>Mi Costeo del ítem</strong>
                  <span class="costeo-section__hint">Muestra y movilidad se cargan 1 vez por pedido; impresión y empaque pueden ser por unidad. Impresión = costo × colores × lados × millar. El Seguro SCTR normal se mantiene para el Total sin IGV; el Costo Real Total usa una base de costo real propia.</span>
                </div>

                <div class="erp-cost-media-grid">
                  <div class="erp-costgrid">
                    <div class="ai-field">
                      <label class="lbl-help" title="Cantidad de muestras físicas. El costo de muestra se calcula como N° de muestras × precio de la muestra.">N° de muestras <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.numero_muestras" size="mini" :precision="0" :step="1" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>

                      <!-- Agosto 2026 (punto 4) — El precio de la muestra no siempre
                           es el costo base: una muestra suelta suele salir más cara
                           que una unidad de una tirada larga. Marcado, se habilita el
                           campo y el Costo Muestra pasa a N° muestras × Precio x Muestra. -->
                      <el-checkbox v-model="props.row._precio_muestra_distinto" :disabled="!isEditable"
                                   style="margin-top:6px;"
                                   @change="onPrecioMuestraToggle(props.row)">
                        <span style="font-size:11px;">Precio distinto al costo base</span>
                      </el-checkbox>
                      <el-tooltip placement="top" content="Marca si el precio de la muestra es diferente al costo base.">
                        <i class="el-icon-info field-tip-icon" style="margin-left:4px;"></i>
                      </el-tooltip>

                      <div v-if="props.row._precio_muestra_distinto" style="margin-top:4px;">
                        <label class="lbl-help" style="font-size:11px;" title="Precio unitario de la muestra. Déjalo en 0 si las muestras son gratis.">Precio x Muestra (S/)</label>
                        <el-input-number v-model="props.row.precio_muestra" size="mini" :precision="2" :step="1" :min="0"
                                         controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                      </div>

                      <div style="font-size:11px; color:#909399; margin-top:2px; line-height:1.2;">Costo muestra: S/ {{ costoMuestraDerivado(props.row).toFixed(2) }}</div>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Costo de movilidad o envío. Se carga una sola vez por pedido.">Costo movilidad (S/) <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.costo_movilidad" size="mini" :precision="2" :step="1" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Costo de impresión. El Costo Total de Impresión = costo × cant. colores × cant. lados × cantidad por millar. Marca «por unidad» si ese total se cobra por cada unidad del pedido en lugar de una sola vez.">Costo impresión (S/) <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.costo_impresion" size="mini" :precision="2" :step="0.5" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                      <!-- COM-02: el factor "Cant. Millar" se edita arriba, en Detalles técnicos, junto a colores y lados. -->
                      <!-- Agosto 2026 (punto 3): mismo comportamiento que su par de empaque. -->
                      <el-checkbox v-model="props.row.impresion_por_unidad" :disabled="!isEditable"
                                   style="margin-top:2px;">por unidad (× cant.)</el-checkbox>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Costo de empaque. Puede ser fijo o por unidad.">Costo empaque (S/) <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.costo_empaque" size="mini" :precision="2" :step="0.5" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                      <el-checkbox v-model="props.row.empaque_por_unidad" :disabled="!isEditable"
                                   style="margin-top:2px;">por unidad (× cant.)</el-checkbox>
                    </div>
                    <div class="ai-field erp-span2">
                      <label class="lbl-help" title="Seguro SCTR de este producto. Si lo dejas vacío, hereda el % de la cotización.">Seguro SCTR % <i class="el-icon-info field-tip-icon"></i></label>
                      <!-- el-input (no el-input-number): permite dejarlo vacío = heredar.
                           el-input-number coacciona el null a 0 al renderizar. -->
                      <el-input v-model="props.row.sctr_porcentaje" size="mini" :disabled="!isEditable"
                                :placeholder="'Hereda ' + sctrPct + '%'" clearable
                                @change="onSctrItemChange(props.row)" style="width:100%;"></el-input>
                      <div style="font-size:11px; color:#909399; margin-top:2px; line-height:1.2;">
                        Efectivo: {{ sctrEfectivo(props.row) }}%<template v-if="!tieneOverrideSctr(props.row)"> (heredado)</template>
                        <el-button v-if="tieneOverrideSctr(props.row)" type="text" size="mini" style="padding:0; margin-left:6px;" :disabled="!isEditable" @click="heredarSctr(props.row)">heredar</el-button>
                      </div>
                    </div>
                  </div>
                  <div class="detail-photo-drop"
                       @click="triggerItemImageUpload(props.row)"
                       @dragover.prevent @drop.prevent="onItemImageDrop(props.row, $event)"
                       v-loading="uploadingItemImageId === props.row.id"
                       role="button" tabindex="0" title="Click o arrastra una imagen">
                    <button v-if="itemImageUrl(props.row)" class="detail-photo-remove" type="button"
                            title="Quitar imagen" @click.stop="deleteItemImage(props.row)">×</button>
                    <img v-if="itemImageUrl(props.row)" :src="itemImageUrl(props.row)" alt="Foto del producto"/>
                    <div v-else class="detail-photo-copy"><strong>Click o arrastra imagen</strong><span>para cargar la foto del producto</span></div>
                  </div>
                </div>
              </div>

              <!-- Servicio: solo Costo Base, Cantidad y Margen editables -->
              <div class="costeo-section erp-cost" v-if="props.row.tipo_linea === 'service'">
                <div class="costeo-section__title">
                  <i class="el-icon-coin"></i> <strong>Mi Costeo del ítem (Servicio)</strong>
                  <span class="costeo-section__hint">Costo base fijo sin IGV ni SCTR. Solo se editan Costo Base, Cantidad y Margen.</span>
                </div>
                <div class="erp-cost-media-grid">
                  <div class="erp-costgrid">
                    <div class="ai-field">
                      <label class="lbl-help" title="Costo unitario base del servicio.">Costo Base (S/) <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.costo_base" size="mini" :precision="2" :step="0.5" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Cantidad de unidades del servicio.">Cantidad <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.quantity" size="mini" :precision="0" :step="1" :min="1"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Porcentaje de margen sobre el costo base.">Margen % <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.margen_porcentaje" size="mini" :precision="1" :step="5" :min="0" :max="999"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                    </div>
                  </div>
                  <div class="detail-photo-drop" style="display:none;"></div>
                </div>
                <div class="erp-costcard">
                  <div class="costtitle">▣ Mi Resultado del costeo (Servicio)</div>
                  <template v-for="bd in [costeoBreakdown(props.row)]">
                    <div class="costeo-breakdown" :key="'bd-' + props.row.id">
                      <div class="costeo-breakdown__row">
                        <span>Resultado margen unitario</span>
                        <span class="costeo-breakdown__val">S/ {{ bd.resultadoMargenUnit.toFixed(2) }}</span>
                      </div>
                      <div class="costeo-breakdown__row costeo-breakdown__margen">
                        <span>Margen Ganancia</span>
                        <span class="costeo-breakdown__val">S/ {{ bd.ganancia.toFixed(2) }}</span>
                      </div>
                      <div class="costeo-breakdown__row">
                        <span>Total (S/) = Costo Base + Resultado</span>
                        <span class="costeo-breakdown__val">S/ {{ bd.productoUnit.toFixed(2) }}</span>
                      </div>
                      <div class="costeo-breakdown__row costeo-breakdown__unit">
                        <span>Costo unitario (sin IGV)</span>
                        <span class="costeo-breakdown__val">S/ {{ bd.unit.toFixed(2) }} / u</span>
                      </div>
                      <div class="costeo-breakdown__row costeo-breakdown__total">
                        <span>Total sin IGV</span>
                        <span class="costeo-breakdown__val">S/ {{ bd.totalSinIgv.toFixed(2) }}</span>
                      </div>
                    </div>
                  </template>
                </div>
              </div>

                </div><!-- /.erp-detail-main -->
                <div class="erp-costcard">
                  <div class="costtitle">▣ Mi Resultado del costeo</div>
                  <!-- Desglose en vivo: el v-for calcula el desglose UNA sola vez
                       y lo expone como `bd` (truco de Vue 2 para variable local). -->
                  <template v-for="bd in [costeoBreakdown(props.row)]">
                    <div class="costeo-breakdown" :key="'bd-' + props.row.id">
                    <!-- ── Cadena de VENTA (lo que se le cobra al cliente) ── -->
                    <div class="costeo-breakdown__row">
                      <span>Resultado margen unitario</span>
                      <span class="costeo-breakdown__val">S/ {{ bd.resultadoMargenUnit.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__margen">
                      <span>Margen Ganancia <small>(ya incluido en Producto)</small></span>
                      <span class="costeo-breakdown__val">S/ {{ bd.ganancia.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Total (S/) = Costo Base + Resultado</span>
                      <span class="costeo-breakdown__val">S/ {{ bd.productoUnit.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Costo muestra</span><span class="costeo-breakdown__val">S/ {{ bd.muestra.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Total Producto <small>(incluye muestra y movilidad)</small></span>
                      <span class="costeo-breakdown__val">S/ {{ bd.totalProducto.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Total Costo Impresión
                        <small v-if="bd.imprColores > 1 || bd.imprLados > 1 || bd.imprMillar !== 1">(× {{ bd.imprColores }} col × {{ bd.imprLados }} lad × {{ bd.imprMillar }} millar)</small>
                      </span>
                      <span class="costeo-breakdown__val">S/ {{ bd.impresion.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Costo empaque <small v-if="props.row.empaque_por_unidad">(× {{ props.row.quantity }})</small></span>
                      <span class="costeo-breakdown__val">S/ {{ bd.empaque.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>+ Seguro SCTR ({{ bd.sctrPctEfectivo }}%)</span><span class="costeo-breakdown__val">S/ {{ bd.sctr.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__total">
                      <span>= Total sin IGV</span><span class="costeo-breakdown__val">S/ {{ bd.totalSinIgv.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__unit">
                      <span>Costo unitario (sin IGV)</span><span class="costeo-breakdown__val">S/ {{ bd.unit.toFixed(2) }} / u</span>
                    </div>

                    <!-- ── Cadena de COSTO REAL (lo que de verdad cuesta el pedido) ──
                         Solo lectura, derivado en vivo. No altera nada de la cadena de venta. -->
                    <div class="costeo-breakdown__row">
                      <span>Base de costo real</span><span class="costeo-breakdown__val">S/ {{ bd.baseCostoReal.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Seguro SCTR para Costo Real ({{ bd.sctrPctEfectivo }}%)</span>
                      <span class="costeo-breakdown__val">S/ {{ bd.sctrCostoReal.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__total">
                      <span>Costo Real Total (Q+logística)</span>
                      <span class="costeo-breakdown__val">S/ {{ bd.costoRealTotal.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__margen">
                      <span>Utilidad Real</span><span class="costeo-breakdown__val">S/ {{ bd.utilidadReal.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__margen">
                      <span>Margen Real %</span><span class="costeo-breakdown__val">{{ bd.margenRealPct.toFixed(2) }}%</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Precio Necesario para {{ bd.margenObjetivoPct }}%</span>
                      <span class="costeo-breakdown__val">S/ {{ bd.precioNecesario.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Cuánto subir (S/.)</span><span class="costeo-breakdown__val">S/ {{ bd.cuantoSubir.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__unit">
                      <span>Precio Unitario a Vender (p/{{ bd.margenObjetivoPct }}%)</span>
                      <span class="costeo-breakdown__val">S/ {{ bd.precioUnitObjetivo.toFixed(2) }} / u</span>
                    </div>
                    <!-- Agosto 2026 (punto 7): el margen que hay que cargar sobre el
                         producto para que el margen REAL llegue al objetivo, más el
                         botón que lo aplica. Es mayor que el objetivo porque los
                         costos de muestra, movilidad, impresión y empaque entran al
                         precio sin markup. -->
                    <div class="costeo-breakdown__row costeo-breakdown__margen">
                      <span>Margen requerido para {{ bd.margenObjetivoPct }}%</span>
                      <span class="costeo-breakdown__val">
                        {{ bd.margenRequeridoPct === null ? '—' : bd.margenRequeridoPct.toFixed(2) + '%' }}
                      </span>
                    </div>
                    <div v-if="isEditable && bd.margenRequeridoPct !== null" class="costeo-breakdown__accion">
                      <el-button size="mini" type="primary" plain icon="el-icon-magic-stick"
                                 @click="aplicarMargenObjetivo(props.row)">
                        Aplicar margen
                      </el-button>
                      <el-tooltip placement="top"
                                  content="Coloca ese porcentaje en Margen % y guarda el producto. El precio se redondea después, así que el margen real queda igual o apenas por encima del objetivo.">
                        <i class="el-icon-info field-tip-icon" style="margin-left:6px;"></i>
                      </el-tooltip>
                    </div>

                    <div class="costeo-breakdown__note">
                      Vista previa con IGV {{ igvPct }}% y SCTR {{ bd.sctrPctEfectivo }}%. El valor definitivo se recalcula al guardar.
                      El precio unitario se redondea hacia arriba a terminación 0, 5 o 9.
                    </div>
                    </div>
                  </template>
                </div><!-- /.erp-costcard -->
              </div><!-- /.erp-detail-layout -->

              <!-- ─────── Comparativa de proveedores [NUEVO FLUJO 2026-10-20] ───────
                   N cotizaciones por ítem en tabla nueva quotation_items_supplier.
                   Origen SIEMPRE manual (sin match con catálogo).
                   Los cambios NO se guardan hasta presionar "Guardar detalles" del ítem. -->
              <div class="supplier-quotes-section" v-if="!isComision(props.row)">
                <div class="supplier-quotes__title">▣ Comparativa de proveedores</div>
                <div class="supplier-quotes__hint">El proveedor ganador define el Costo Base de esta línea. El origen siempre es manual. Los cambios se guardan al presionar "Guardar detalles".</div>
                <el-table :data="supplierQuotesOf(props.row)" size="mini" border style="width:100%;">
                  <el-table-column label="Ganador" width="110" align="center">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO 2026-10-21] el-radio de Element: el <input type="radio">
                           nativo lo oculta el CSS global (position:absolute; left:-9999px; opacity:0),
                           por eso la columna salía vacía. @input (no @change): el valor
                           emitido es el label (id de la fila) al hacer clic. -->
                      <el-radio :value="qscope.row.seleccionado ? qscope.row.id : null" :label="qscope.row.id"
                                :disabled="!isEditable" @input="setWinnerQuote(props.row, qscope.row)">
                        <span class="supplier-quotes__radio-label">&nbsp;</span>
                      </el-radio>
                      <div v-if="!qscope.row.seleccionado" class="supplier-quotes__respaldo"
                           title="Este proveedor queda como respaldo; el ganador define el Costo Base.">
                        Respaldo
                      </div>
                    </template>
                  </el-table-column>
                  <el-table-column label="Proveedor" min-width="170">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO] Al cambiar proveedor, limpia costo/tiempo/observaciones -->
                      <el-select v-model="qscope.row.supplier_id" size="mini" filterable
                                 placeholder="Proveedor por definir"
                                 :filter-method="filterSuppliers"
                                 :disabled="!isEditable" style="width:100%;"
                                 @change="function (val) { onQuoteSupplierChange(props.row, qscope.row, val) }">
                        <el-option :key="0" :label="'Proveedor por definir'" :value="0"></el-option>
                        <el-option v-for="s in supplierOptionsFor(qscope.row)" :key="s.id"
                                   :label="s.nombre_comercial || s.razon_social" :value="s.id"></el-option>
                      </el-select>
                    </template>
                  </el-table-column>
                  <el-table-column label="Origen" width="90" align="center">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO] Origen siempre manual en esta tabla -->
                      <el-tag size="mini" effect="plain">Manual</el-tag>
                    </template>
                  </el-table-column>
                  <el-table-column label="Producto / referencia cotizada" min-width="200">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO 2026-10-21] Solo lectura: es el MISMO campo
                           "Producto solicitado" de la fila del item (el-input__inner).
                           Se edita desde la fila del item, no desde acá. Antes, al
                           agregar un proveedor, este campo salía en blanco. -->
                      <span class="campo-calculado">{{ qscope.row.product_reference || props.row.product_name }}</span>
                    </template>
                  </el-table-column>
                  <el-table-column label="Costo Base" width="110" align="right">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO 2026-10-21] v-model: el input-number con
                           :value + @change perdía la edición (no se guardaba). -->
                      <el-input-number v-model="qscope.row.costo_base" size="mini" :precision="2" :step="1" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"
                                       @change="markSupplierQuoteDirty(props.row)"></el-input-number>
                    </template>
                  </el-table-column>
                  <el-table-column label="V. Unit." width="86" align="right">
                    <template slot="header">
                      <span class="col-tip" title="Unitario de venta que saldría si este proveedor fuera el ganador (Total sin IGV ÷ cantidad, con muestras, impresión, empaque y SCTR).">V. Unit. <i class="el-icon-info"></i></span>
                    </template>
                    <template slot-scope="qscope">
                      <span class="campo-calculado">{{ quoteUnitValue(props.row, qscope.row) }}</span>
                    </template>
                  </el-table-column>
                  <el-table-column label="Tiempo entrega" width="130">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO 2026-10-21] v-model: editable de verdad. -->
                      <el-input v-model="qscope.row.tiempo_estimado_dias" size="mini" placeholder="Ej: 15 días útiles"
                                :disabled="!isEditable" @input="markSupplierQuoteDirty(props.row)"></el-input>
                    </template>
                  </el-table-column>
                  <el-table-column label="Observaciones" min-width="150">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO 2026-10-21] v-model: editable de verdad. -->
                      <el-input v-model="qscope.row.observaciones" size="mini" placeholder="Observaciones"
                                :disabled="!isEditable" @input="markSupplierQuoteDirty(props.row)"></el-input>
                    </template>
                  </el-table-column>
                  <el-table-column label="Acción" width="60" align="center">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO] Solo botón eliminar con confirmación. Sin "Agregar catálogo". -->
                      <el-button v-if="isEditable" size="mini" type="danger" icon="el-icon-delete" circle
                                 :disabled="supplierQuotesOf(props.row).length <= 1"
                                 @click="removeSupplierQuote(props.row, qscope.row)"></el-button>
                    </template>
                  </el-table-column>
                </el-table>
                <div style="margin-top:8px;">
                  <el-button v-if="isEditable" size="mini" icon="el-icon-plus" @click="addSupplierQuote(props.row)">+ proveedor</el-button>
                </div>
              </div>

              <!-- ─────── Fase C: Escalones de precio por volumen ─────── -->
              <div v-if="!isComision(props.row) && props.row.precios_volumen_disponibles && props.row.precios_volumen_disponibles.length > 0"
                   class="tiers-section">
                <div class="tiers-section__title">
                  <i class="el-icon-sort"></i> <strong>Precios por volumen del catálogo</strong>
                  <span class="tiers-section__hint">
                    Al cambiar la cantidad, el sistema aplica automáticamente el escalón mayorista alcanzado (último ≤ cantidad).
                  </span>
                </div>
                <el-table :data="props.row.precios_volumen_disponibles" border size="mini"
                          row-key="id"
                          :row-class-name="(row) => tierRowClass(row, props.row)"
                          style="max-width:480px;">
                  <el-table-column label="Desde" prop="quantity_unit" width="80" align="right">
                    <template slot-scope="t">{{ formatTierQty(t.row.quantity_unit) }} u</template>
                  </el-table-column>
                  <el-table-column label="Precio unitario" width="130" align="right">
                    <template slot-scope="t">S/ {{ t.row.price1.toFixed(2) }}</template>
                  </el-table-column>
                  <el-table-column label="Descripción" prop="description"></el-table-column>
                  <el-table-column label="" width="50" align="center">
                    <template slot-scope="t">
                      <i v-if="props.row.tier_aplicado && props.row.tier_aplicado.id === t.row.id"
                         class="el-icon-check" style="color:#67c23a; font-weight:bold;"
                         title="Escalón aplicado"></i>
                    </template>
                  </el-table-column>
                </el-table>
              </div>

              <div class="ai-inferred-details__footer">
                <el-button v-if="isEditable" size="mini" type="success" icon="el-icon-check"
                           @click="saveItem(props.row)">
                  Guardar detalles
                </el-button>
                <span class="ai-inferred-details__hint">
                  <i class="el-icon-magic-stick"></i>
                  = campo sugerido por la IA. Al editarlo, la marca desaparece.
                </span>
              </div>
            </div>
                    </template>
                  </template>
                </el-table-column>
                <el-table-column label="Kit" width="70" align="center">
                  <template slot-scope="mscope">
                    <el-button v-if="isEditable" size="mini" type="danger" plain
                               @click="removeKitMember(props.row.kit, mscope.row)">Quitar</el-button>
                  </template>
                </el-table-column>
                <el-table-column label="#" width="40" align="center">
                  <template slot-scope="mscope">{{ mscope.$index + 1 }}</template>
                </el-table-column>
                <el-table-column label="Producto solicitado" min-width="140">
                  <template slot-scope="mscope">{{ mscope.row.product_name }}</template>
                </el-table-column>
                <el-table-column label="Foto" width="60" align="center">
                  <template slot-scope="mscope">
                    <img v-if="itemImageUrl(mscope.row)" :src="itemImageUrl(mscope.row)" alt="foto"
                         class="item-foto-thumb"/>
                    <span v-else class="item-foto-empty"><i class="el-icon-picture-outline"></i></span>
                  </template>
                </el-table-column>
                <el-table-column label="Tipo" width="90" align="center">
                  <template slot-scope="mscope">
                    <span style="font-size:11px;">{{ isComision(mscope.row) ? 'Comisión' : (String(mscope.row.tipo_linea || '').toLowerCase() === 'service' ? 'Servicio' : 'Producto') }}</span>
                  </template>
                </el-table-column>
                <el-table-column label="Producto del catálogo" min-width="150">
                  <template slot-scope="mscope">
                    <div v-if="!isComision(mscope.row)">
                      <el-tag v-if="matchStatus(mscope.row) === 'matched'" size="mini" type="success" effect="plain">
                        <i class="el-icon-success"></i>
                        {{ mscope.row.catalog_item ? mscope.row.catalog_item.name : ('ID ' + mscope.row.item_id) }}
                      </el-tag>
                      <el-tag v-else-if="matchStatus(mscope.row) === 'review'" size="mini" type="warning" effect="dark"
                              style="cursor:pointer;" @click="openMatchDialog(mscope.row)">
                        <i class="el-icon-warning-outline"></i> Revisar producto
                      </el-tag>
                      <el-tag v-else size="mini" type="info" effect="plain"
                              style="cursor:pointer;" @click="openMatchDialog(mscope.row)">
                        <i class="el-icon-search"></i> Sin match — buscar
                      </el-tag>
                    </div>
                    <el-tag v-else size="mini" type="info" effect="plain">Comisión</el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="Cant." width="80" align="center">
                  <template slot-scope="mscope">{{ mscope.row.quantity }}</template>
                </el-table-column>
                <el-table-column label="Costo Base" width="110" align="right">
                  <template slot-scope="mscope">{{ selectedQuoteCost(mscope.row) }}</template>
                </el-table-column>
                <el-table-column label="Margen %" width="90" align="right">
                  <template slot-scope="mscope">{{ parseFloat(mscope.row.margen_porcentaje || 0).toFixed(1) }}%</template>
                </el-table-column>
                <el-table-column label="Margen S/" width="96" align="right">
                  <template slot-scope="mscope">S/ {{ parseFloat(mscope.row.ganancia_absoluta || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column label="V. Unit." width="86" align="right">
                  <template slot-scope="mscope">S/ {{ parseFloat(mscope.row.valor_unitario || 0).toFixed(2) }}</template>
                </el-table-column>
                <el-table-column label="Total" width="110" align="right">
                  <template slot-scope="mscope"><strong>S/ {{ parseFloat(mscope.row.total_item || 0).toFixed(2) }}</strong></template>
                </el-table-column>
              </el-table>
            </div>
            <div v-else class="ai-inferred-details">
              <div class="ai-inferred-details__header">
                <span class="ai-inferred-details__title">
                  <i class="el-icon-document-copy"></i>
                  <strong>Detalles técnicos del producto</strong>
                </span>
                <span v-if="inferredFields(props.row).length > 0"
                      class="ai-inferred-details__warning">
                  <i class="el-icon-warning-outline"></i>
                  {{ inferredFields(props.row).length }} campo(s) inferido(s) por la IA — confirma con el cliente antes de emitir
                </span>
              </div>

              <div class="erp-detail-layout">
                <div class="erp-detail-main">
                  <div class="erp-tech-grid" v-if="!isComision(props.row) && !isService(props.row)">
                    <div class="ai-field">
                      <label class="lbl-help" title="Material del producto. Si lo sugirió la IA, confírmalo con el cliente.">Material <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input v-model="props.row.material" size="mini" :disabled="!isEditable"
                                placeholder="Ej: algodón, dry fit, plástico..."
                                @input="onFieldEdited(props.row, 'material')"
                                :class="{ 'ai-inferred-input': isInferred(props.row, 'material') }">
                        <el-tooltip v-if="isInferred(props.row, 'material')" slot="suffix"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon"></i>
                        </el-tooltip>
                      </el-input>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Medida o tamaño del producto.">Medida / Tamaño <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input v-model="props.row.medida" size="mini" :disabled="!isEditable"
                                placeholder="Ej: 500ml, A5, 30x40cm..."
                                @input="onFieldEdited(props.row, 'medida')"
                                :class="{ 'ai-inferred-input': isInferred(props.row, 'medida') }">
                        <el-tooltip v-if="isInferred(props.row, 'medida')" slot="suffix"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon"></i>
                        </el-tooltip>
                      </el-input>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Detalle de la personalización solicitada (texto, logo, color, etc.).">Personalización <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input v-model="props.row.personalizacion" size="mini" :disabled="!isEditable"
                                placeholder="Ej: logo bordado, texto..."
                                @input="onFieldEdited(props.row, 'personalizacion')"
                                :class="{ 'ai-inferred-input': isInferred(props.row, 'personalizacion') }">
                        <el-tooltip v-if="isInferred(props.row, 'personalizacion')" slot="suffix"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon"></i>
                        </el-tooltip>
                      </el-input>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Técnica de impresión o aplicación (serigrafía, bordado, sublimado, etc.).">Técnica <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input v-model="props.row.tecnica_impresion" size="mini" :disabled="!isEditable"
                                placeholder="Ej: serigrafía, bordado, sublimado..."
                                @input="onFieldEdited(props.row, 'tecnica_impresion')"
                                :class="{ 'ai-inferred-input': isInferred(props.row, 'tecnica_impresion') }">
                        <el-tooltip v-if="isInferred(props.row, 'tecnica_impresion')" slot="suffix"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon"></i>
                        </el-tooltip>
                      </el-input>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Forma de empaque o presentación del producto.">Empaque / Presentación <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input v-model="props.row.empaque" size="mini" :disabled="!isEditable"
                                placeholder="Ej: bolsa individual, caja..."
                                @input="onFieldEdited(props.row, 'empaque')"
                                :class="{ 'ai-inferred-input': isInferred(props.row, 'empaque') }">
                        <el-tooltip v-if="isInferred(props.row, 'empaque')" slot="suffix"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon"></i>
                        </el-tooltip>
                      </el-input>
                    </div>
                    <div class="erp-tech-minirow">
                      <div class="ai-field ai-field--with-outside-icon">
                        <label class="lbl-help" title="Cantidad de colores de la impresión (afecta el costo).">Colores <i class="el-icon-info field-tip-icon"></i></label>
                        <el-input-number v-model="props.row.cantidad_colores" size="mini" :min="1" :max="20"
                                         controls-position="right" :disabled="!isEditable"
                                         @change="onFieldEdited(props.row, 'cantidad_colores')"
                                         :class="{ 'ai-inferred-input': isInferred(props.row, 'cantidad_colores') }">
                        </el-input-number>
                        <el-tooltip v-if="isInferred(props.row, 'cantidad_colores')"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon ai-inferred-icon--outside"></i>
                        </el-tooltip>
                      </div>
                      <div class="ai-field ai-field--with-outside-icon">
                        <label class="lbl-help" title="Cantidad de lados o caras a personalizar.">Lados <i class="el-icon-info field-tip-icon"></i></label>
                        <el-input-number v-model="props.row.cantidad_lados" size="mini" :min="1" :max="6"
                                         controls-position="right" :disabled="!isEditable"
                                         @change="onFieldEdited(props.row, 'cantidad_lados')"
                                         :class="{ 'ai-inferred-input': isInferred(props.row, 'cantidad_lados') }">
                        </el-input-number>
                        <el-tooltip v-if="isInferred(props.row, 'cantidad_lados')"
                                    content="Inferido por IA — confirma con el cliente" placement="top">
                          <i class="el-icon-magic-stick ai-inferred-icon ai-inferred-icon--outside"></i>
                        </el-tooltip>
                      </div>
                      <div class="ai-field">
                        <label class="lbl-help" title="Factor manual de la impresión (millares). Multiplica el Costo Total de Impresión: costo × colores × lados × millar. Por defecto 1.">Millar <i class="el-icon-info field-tip-icon"></i></label>
                        <el-input-number v-model="props.row.cantidad_millar" size="mini" :precision="2" :step="1" :min="0"
                                         controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                      </div>
                    </div>
                  </div>
                  <div class="erp-tech-duo">
                      <div class="ai-field">
                        <label class="lbl-help" title="Descripción comercial o especificaciones del producto.">Descripción <i class="el-icon-info field-tip-icon"></i></label>
                        <el-input v-model="props.row.product_description" size="mini" :disabled="!isEditable"
                                  type="textarea" :autosize="{minRows: 2, maxRows: 6}"
                                  placeholder="Descripción comercial o especificaciones del producto..."
                                  @input="onFieldEdited(props.row, 'product_description')"
                                  :class="{ 'ai-inferred-input': isInferred(props.row, 'product_description') }">
                        </el-input>
                      </div>
                      <div class="ai-field erp-obs" v-if="!isComision(props.row)">
                        <label class="lbl-help" title="Notas internas de esta línea; no se muestran al cliente.">Observaciones <i class="el-icon-info field-tip-icon"></i></label>
                        <el-input v-model="props.row.observaciones" size="mini" :disabled="!isEditable"
                                  type="textarea" :autosize="{minRows: 2, maxRows: 6}"
                                  placeholder="Notas internas para esta línea (opcional)">
                        </el-input>
                      </div>
                    </div>

              <!-- Agosto 2026 (punto 2) — kits y servicio de armado.
                   Un kit son varios productos agrupados solo para esta cotización
                   y el armado es un servicio puntual: ninguno es un producto del
                   catálogo. El check "No registrar..." se retiró: esa función vive
                   ahora en el combo Tipo = Servicio (al convertir NO se le crea
                   un producto propio). La línea conserva igual este nombre. -->

              <!-- ───────────── Mi Costeo del ítem (formato mockup) ───────────── -->
              <!-- Producto: costeo completo. Servicio: solo Costo Base, Cantidad y Margen. -->
              <div class="costeo-section erp-cost" v-if="props.row.tipo_linea === 'product'">
                <div class="costeo-section__title">
                  <i class="el-icon-coin"></i> <strong>Mi Costeo del ítem</strong>
                  <span class="costeo-section__hint">Muestra y movilidad se cargan 1 vez por pedido; impresión y empaque pueden ser por unidad. Impresión = costo × colores × lados × millar. El Seguro SCTR normal se mantiene para el Total sin IGV; el Costo Real Total usa una base de costo real propia.</span>
                </div>

                <div class="erp-cost-media-grid">
                  <div class="erp-costgrid">
                    <div class="ai-field">
                      <label class="lbl-help" title="Cantidad de muestras físicas. El costo de muestra se calcula como N° de muestras × precio de la muestra.">N° de muestras <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.numero_muestras" size="mini" :precision="0" :step="1" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>

                      <!-- Agosto 2026 (punto 4) — El precio de la muestra no siempre
                           es el costo base: una muestra suelta suele salir más cara
                           que una unidad de una tirada larga. Marcado, se habilita el
                           campo y el Costo Muestra pasa a N° muestras × Precio x Muestra. -->
                      <el-checkbox v-model="props.row._precio_muestra_distinto" :disabled="!isEditable"
                                   style="margin-top:6px;"
                                   @change="onPrecioMuestraToggle(props.row)">
                        <span style="font-size:11px;">Precio distinto al costo base</span>
                      </el-checkbox>
                      <el-tooltip placement="top" content="Marca si el precio de la muestra es diferente al costo base.">
                        <i class="el-icon-info field-tip-icon" style="margin-left:4px;"></i>
                      </el-tooltip>

                      <div v-if="props.row._precio_muestra_distinto" style="margin-top:4px;">
                        <label class="lbl-help" style="font-size:11px;" title="Precio unitario de la muestra. Déjalo en 0 si las muestras son gratis.">Precio x Muestra (S/)</label>
                        <el-input-number v-model="props.row.precio_muestra" size="mini" :precision="2" :step="1" :min="0"
                                         controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                      </div>

                      <div style="font-size:11px; color:#909399; margin-top:2px; line-height:1.2;">Costo muestra: S/ {{ costoMuestraDerivado(props.row).toFixed(2) }}</div>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Costo de movilidad o envío. Se carga una sola vez por pedido.">Costo movilidad (S/) <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.costo_movilidad" size="mini" :precision="2" :step="1" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Costo de impresión. El Costo Total de Impresión = costo × cant. colores × cant. lados × cantidad por millar. Marca «por unidad» si ese total se cobra por cada unidad del pedido en lugar de una sola vez.">Costo impresión (S/) <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.costo_impresion" size="mini" :precision="2" :step="0.5" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                      <!-- COM-02: el factor "Cant. Millar" se edita arriba, en Detalles técnicos, junto a colores y lados. -->
                      <!-- Agosto 2026 (punto 3): mismo comportamiento que su par de empaque. -->
                      <el-checkbox v-model="props.row.impresion_por_unidad" :disabled="!isEditable"
                                   style="margin-top:2px;">por unidad (× cant.)</el-checkbox>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Costo de empaque. Puede ser fijo o por unidad.">Costo empaque (S/) <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.costo_empaque" size="mini" :precision="2" :step="0.5" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                      <el-checkbox v-model="props.row.empaque_por_unidad" :disabled="!isEditable"
                                   style="margin-top:2px;">por unidad (× cant.)</el-checkbox>
                    </div>
                    <div class="ai-field erp-span2">
                      <label class="lbl-help" title="Seguro SCTR de este producto. Si lo dejas vacío, hereda el % de la cotización.">Seguro SCTR % <i class="el-icon-info field-tip-icon"></i></label>
                      <!-- el-input (no el-input-number): permite dejarlo vacío = heredar.
                           el-input-number coacciona el null a 0 al renderizar. -->
                      <el-input v-model="props.row.sctr_porcentaje" size="mini" :disabled="!isEditable"
                                :placeholder="'Hereda ' + sctrPct + '%'" clearable
                                @change="onSctrItemChange(props.row)" style="width:100%;"></el-input>
                      <div style="font-size:11px; color:#909399; margin-top:2px; line-height:1.2;">
                        Efectivo: {{ sctrEfectivo(props.row) }}%<template v-if="!tieneOverrideSctr(props.row)"> (heredado)</template>
                        <el-button v-if="tieneOverrideSctr(props.row)" type="text" size="mini" style="padding:0; margin-left:6px;" :disabled="!isEditable" @click="heredarSctr(props.row)">heredar</el-button>
                      </div>
                    </div>
                  </div>
                  <div class="detail-photo-drop"
                       @click="triggerItemImageUpload(props.row)"
                       @dragover.prevent @drop.prevent="onItemImageDrop(props.row, $event)"
                       v-loading="uploadingItemImageId === props.row.id"
                       role="button" tabindex="0" title="Click o arrastra una imagen">
                    <button v-if="itemImageUrl(props.row)" class="detail-photo-remove" type="button"
                            title="Quitar imagen" @click.stop="deleteItemImage(props.row)">×</button>
                    <img v-if="itemImageUrl(props.row)" :src="itemImageUrl(props.row)" alt="Foto del producto"/>
                    <div v-else class="detail-photo-copy"><strong>Click o arrastra imagen</strong><span>para cargar la foto del producto</span></div>
                  </div>
                </div>
              </div>

              <!-- Servicio: solo Costo Base, Cantidad y Margen editables -->
              <div class="costeo-section erp-cost" v-if="props.row.tipo_linea === 'service'">
                <div class="costeo-section__title">
                  <i class="el-icon-coin"></i> <strong>Mi Costeo del ítem (Servicio)</strong>
                  <span class="costeo-section__hint">Costo base fijo sin IGV ni SCTR. Solo se editan Costo Base, Cantidad y Margen.</span>
                </div>
                <div class="erp-cost-media-grid">
                  <div class="erp-costgrid">
                    <div class="ai-field">
                      <label class="lbl-help" title="Costo unitario base del servicio.">Costo Base (S/) <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.costo_base" size="mini" :precision="2" :step="0.5" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Cantidad de unidades del servicio.">Cantidad <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.quantity" size="mini" :precision="0" :step="1" :min="1"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                    </div>
                    <div class="ai-field">
                      <label class="lbl-help" title="Porcentaje de margen sobre el costo base.">Margen % <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.margen_porcentaje" size="mini" :precision="1" :step="5" :min="0" :max="999"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                    </div>
                  </div>
                  <div class="detail-photo-drop" style="display:none;"></div>
                </div>
                <div class="erp-costcard">
                  <div class="costtitle">▣ Mi Resultado del costeo (Servicio)</div>
                  <template v-for="bd in [costeoBreakdown(props.row)]">
                    <div class="costeo-breakdown" :key="'bd-' + props.row.id">
                      <div class="costeo-breakdown__row">
                        <span>Resultado margen unitario</span>
                        <span class="costeo-breakdown__val">S/ {{ bd.resultadoMargenUnit.toFixed(2) }}</span>
                      </div>
                      <div class="costeo-breakdown__row costeo-breakdown__margen">
                        <span>Margen Ganancia</span>
                        <span class="costeo-breakdown__val">S/ {{ bd.ganancia.toFixed(2) }}</span>
                      </div>
                      <div class="costeo-breakdown__row">
                        <span>Total (S/) = Costo Base + Resultado</span>
                        <span class="costeo-breakdown__val">S/ {{ bd.productoUnit.toFixed(2) }}</span>
                      </div>
                      <div class="costeo-breakdown__row costeo-breakdown__unit">
                        <span>Costo unitario (sin IGV)</span>
                        <span class="costeo-breakdown__val">S/ {{ bd.unit.toFixed(2) }} / u</span>
                      </div>
                      <div class="costeo-breakdown__row costeo-breakdown__total">
                        <span>Total sin IGV</span>
                        <span class="costeo-breakdown__val">S/ {{ bd.totalSinIgv.toFixed(2) }}</span>
                      </div>
                    </div>
                  </template>
                </div>
              </div>

                </div><!-- /.erp-detail-main -->
                <div class="erp-costcard">
                  <div class="costtitle">▣ Mi Resultado del costeo</div>
                  <!-- Desglose en vivo: el v-for calcula el desglose UNA sola vez
                       y lo expone como `bd` (truco de Vue 2 para variable local). -->
                  <template v-for="bd in [costeoBreakdown(props.row)]">
                    <div class="costeo-breakdown" :key="'bd-' + props.row.id">
                    <!-- ── Cadena de VENTA (lo que se le cobra al cliente) ── -->
                    <div class="costeo-breakdown__row">
                      <span>Resultado margen unitario</span>
                      <span class="costeo-breakdown__val">S/ {{ bd.resultadoMargenUnit.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__margen">
                      <span>Margen Ganancia <small>(ya incluido en Producto)</small></span>
                      <span class="costeo-breakdown__val">S/ {{ bd.ganancia.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Total (S/) = Costo Base + Resultado</span>
                      <span class="costeo-breakdown__val">S/ {{ bd.productoUnit.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Costo muestra</span><span class="costeo-breakdown__val">S/ {{ bd.muestra.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Total Producto <small>(incluye muestra y movilidad)</small></span>
                      <span class="costeo-breakdown__val">S/ {{ bd.totalProducto.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Total Costo Impresión
                        <small v-if="bd.imprColores > 1 || bd.imprLados > 1 || bd.imprMillar !== 1">(× {{ bd.imprColores }} col × {{ bd.imprLados }} lad × {{ bd.imprMillar }} millar)</small>
                      </span>
                      <span class="costeo-breakdown__val">S/ {{ bd.impresion.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Costo empaque <small v-if="props.row.empaque_por_unidad">(× {{ props.row.quantity }})</small></span>
                      <span class="costeo-breakdown__val">S/ {{ bd.empaque.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>+ Seguro SCTR ({{ bd.sctrPctEfectivo }}%)</span><span class="costeo-breakdown__val">S/ {{ bd.sctr.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__total">
                      <span>= Total sin IGV</span><span class="costeo-breakdown__val">S/ {{ bd.totalSinIgv.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__unit">
                      <span>Costo unitario (sin IGV)</span><span class="costeo-breakdown__val">S/ {{ bd.unit.toFixed(2) }} / u</span>
                    </div>

                    <!-- ── Cadena de COSTO REAL (lo que de verdad cuesta el pedido) ──
                         Solo lectura, derivado en vivo. No altera nada de la cadena de venta. -->
                    <div class="costeo-breakdown__row">
                      <span>Base de costo real</span><span class="costeo-breakdown__val">S/ {{ bd.baseCostoReal.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Seguro SCTR para Costo Real ({{ bd.sctrPctEfectivo }}%)</span>
                      <span class="costeo-breakdown__val">S/ {{ bd.sctrCostoReal.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__total">
                      <span>Costo Real Total (Q+logística)</span>
                      <span class="costeo-breakdown__val">S/ {{ bd.costoRealTotal.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__margen">
                      <span>Utilidad Real</span><span class="costeo-breakdown__val">S/ {{ bd.utilidadReal.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__margen">
                      <span>Margen Real %</span><span class="costeo-breakdown__val">{{ bd.margenRealPct.toFixed(2) }}%</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Precio Necesario para {{ bd.margenObjetivoPct }}%</span>
                      <span class="costeo-breakdown__val">S/ {{ bd.precioNecesario.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row">
                      <span>Cuánto subir (S/.)</span><span class="costeo-breakdown__val">S/ {{ bd.cuantoSubir.toFixed(2) }}</span>
                    </div>
                    <div class="costeo-breakdown__row costeo-breakdown__unit">
                      <span>Precio Unitario a Vender (p/{{ bd.margenObjetivoPct }}%)</span>
                      <span class="costeo-breakdown__val">S/ {{ bd.precioUnitObjetivo.toFixed(2) }} / u</span>
                    </div>
                    <!-- Agosto 2026 (punto 7): el margen que hay que cargar sobre el
                         producto para que el margen REAL llegue al objetivo, más el
                         botón que lo aplica. Es mayor que el objetivo porque los
                         costos de muestra, movilidad, impresión y empaque entran al
                         precio sin markup. -->
                    <div class="costeo-breakdown__row costeo-breakdown__margen">
                      <span>Margen requerido para {{ bd.margenObjetivoPct }}%</span>
                      <span class="costeo-breakdown__val">
                        {{ bd.margenRequeridoPct === null ? '—' : bd.margenRequeridoPct.toFixed(2) + '%' }}
                      </span>
                    </div>
                    <div v-if="isEditable && bd.margenRequeridoPct !== null" class="costeo-breakdown__accion">
                      <el-button size="mini" type="primary" plain icon="el-icon-magic-stick"
                                 @click="aplicarMargenObjetivo(props.row)">
                        Aplicar margen
                      </el-button>
                      <el-tooltip placement="top"
                                  content="Coloca ese porcentaje en Margen % y guarda el producto. El precio se redondea después, así que el margen real queda igual o apenas por encima del objetivo.">
                        <i class="el-icon-info field-tip-icon" style="margin-left:6px;"></i>
                      </el-tooltip>
                    </div>

                    <div class="costeo-breakdown__note">
                      Vista previa con IGV {{ igvPct }}% y SCTR {{ bd.sctrPctEfectivo }}%. El valor definitivo se recalcula al guardar.
                      El precio unitario se redondea hacia arriba a terminación 0, 5 o 9.
                    </div>
                    </div>
                  </template>
                </div><!-- /.erp-costcard -->
              </div><!-- /.erp-detail-layout -->

              <!-- ─────── Comparativa de proveedores [NUEVO FLUJO 2026-10-20] ───────
                   N cotizaciones por ítem en tabla nueva quotation_items_supplier.
                   Origen SIEMPRE manual (sin match con catálogo).
                   Los cambios NO se guardan hasta presionar "Guardar detalles" del ítem. -->
              <div class="supplier-quotes-section" v-if="!isComision(props.row)">
                <div class="supplier-quotes__title">▣ Comparativa de proveedores</div>
                <div class="supplier-quotes__hint">El proveedor ganador define el Costo Base de esta línea. El origen siempre es manual. Los cambios se guardan al presionar "Guardar detalles".</div>
                <el-table :data="supplierQuotesOf(props.row)" size="mini" border style="width:100%;">
                  <el-table-column label="Ganador" width="110" align="center">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO 2026-10-21] el-radio de Element: el <input type="radio">
                           nativo lo oculta el CSS global (position:absolute; left:-9999px; opacity:0),
                           por eso la columna salía vacía. @input (no @change): el valor
                           emitido es el label (id de la fila) al hacer clic. -->
                      <el-radio :value="qscope.row.seleccionado ? qscope.row.id : null" :label="qscope.row.id"
                                :disabled="!isEditable" @input="setWinnerQuote(props.row, qscope.row)">
                        <span class="supplier-quotes__radio-label">&nbsp;</span>
                      </el-radio>
                      <div v-if="!qscope.row.seleccionado" class="supplier-quotes__respaldo"
                           title="Este proveedor queda como respaldo; el ganador define el Costo Base.">
                        Respaldo
                      </div>
                    </template>
                  </el-table-column>
                  <el-table-column label="Proveedor" min-width="170">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO] Al cambiar proveedor, limpia costo/tiempo/observaciones -->
                      <el-select v-model="qscope.row.supplier_id" size="mini" filterable
                                 placeholder="Proveedor por definir"
                                 :filter-method="filterSuppliers"
                                 :disabled="!isEditable" style="width:100%;"
                                 @change="function (val) { onQuoteSupplierChange(props.row, qscope.row, val) }">
                        <el-option :key="0" :label="'Proveedor por definir'" :value="0"></el-option>
                        <el-option v-for="s in supplierOptionsFor(qscope.row)" :key="s.id"
                                   :label="s.nombre_comercial || s.razon_social" :value="s.id"></el-option>
                      </el-select>
                    </template>
                  </el-table-column>
                  <el-table-column label="Origen" width="90" align="center">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO] Origen siempre manual en esta tabla -->
                      <el-tag size="mini" effect="plain">Manual</el-tag>
                    </template>
                  </el-table-column>
                  <el-table-column label="Producto / referencia cotizada" min-width="200">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO 2026-10-21] Solo lectura: es el MISMO campo
                           "Producto solicitado" de la fila del item (el-input__inner).
                           Se edita desde la fila del item, no desde acá. Antes, al
                           agregar un proveedor, este campo salía en blanco. -->
                      <span class="campo-calculado">{{ qscope.row.product_reference || props.row.product_name }}</span>
                    </template>
                  </el-table-column>
                  <el-table-column label="Costo Base" width="110" align="right">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO 2026-10-21] v-model: el input-number con
                           :value + @change perdía la edición (no se guardaba). -->
                      <el-input-number v-model="qscope.row.costo_base" size="mini" :precision="2" :step="1" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"
                                       @change="markSupplierQuoteDirty(props.row)"></el-input-number>
                    </template>
                  </el-table-column>
                  <el-table-column label="V. Unit." width="86" align="right">
                    <template slot="header">
                      <span class="col-tip" title="Unitario de venta que saldría si este proveedor fuera el ganador (Total sin IGV ÷ cantidad, con muestras, impresión, empaque y SCTR).">V. Unit. <i class="el-icon-info"></i></span>
                    </template>
                    <template slot-scope="qscope">
                      <span class="campo-calculado">{{ quoteUnitValue(props.row, qscope.row) }}</span>
                    </template>
                  </el-table-column>
                  <el-table-column label="Tiempo entrega" width="130">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO 2026-10-21] v-model: editable de verdad. -->
                      <el-input v-model="qscope.row.tiempo_estimado_dias" size="mini" placeholder="Ej: 15 días útiles"
                                :disabled="!isEditable" @input="markSupplierQuoteDirty(props.row)"></el-input>
                    </template>
                  </el-table-column>
                  <el-table-column label="Observaciones" min-width="150">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO 2026-10-21] v-model: editable de verdad. -->
                      <el-input v-model="qscope.row.observaciones" size="mini" placeholder="Observaciones"
                                :disabled="!isEditable" @input="markSupplierQuoteDirty(props.row)"></el-input>
                    </template>
                  </el-table-column>
                  <el-table-column label="Acción" width="60" align="center">
                    <template slot-scope="qscope">
                      <!-- [NUEVO FLUJO] Solo botón eliminar con confirmación. Sin "Agregar catálogo". -->
                      <el-button v-if="isEditable" size="mini" type="danger" icon="el-icon-delete" circle
                                 :disabled="supplierQuotesOf(props.row).length <= 1"
                                 @click="removeSupplierQuote(props.row, qscope.row)"></el-button>
                    </template>
                  </el-table-column>
                </el-table>
                <div style="margin-top:8px;">
                  <el-button v-if="isEditable" size="mini" icon="el-icon-plus" @click="addSupplierQuote(props.row)">+ proveedor</el-button>
                </div>
              </div>

              <!-- ─────── Fase C: Escalones de precio por volumen ─────── -->
              <div v-if="!isComision(props.row) && props.row.precios_volumen_disponibles && props.row.precios_volumen_disponibles.length > 0"
                   class="tiers-section">
                <div class="tiers-section__title">
                  <i class="el-icon-sort"></i> <strong>Precios por volumen del catálogo</strong>
                  <span class="tiers-section__hint">
                    Al cambiar la cantidad, el sistema aplica automáticamente el escalón mayorista alcanzado (último ≤ cantidad).
                  </span>
                </div>
                <el-table :data="props.row.precios_volumen_disponibles" border size="mini"
                          row-key="id"
                          :row-class-name="(row) => tierRowClass(row, props.row)"
                          style="max-width:480px;">
                  <el-table-column label="Desde" prop="quantity_unit" width="80" align="right">
                    <template slot-scope="t">{{ formatTierQty(t.row.quantity_unit) }} u</template>
                  </el-table-column>
                  <el-table-column label="Precio unitario" width="130" align="right">
                    <template slot-scope="t">S/ {{ t.row.price1.toFixed(2) }}</template>
                  </el-table-column>
                  <el-table-column label="Descripción" prop="description"></el-table-column>
                  <el-table-column label="" width="50" align="center">
                    <template slot-scope="t">
                      <i v-if="props.row.tier_aplicado && props.row.tier_aplicado.id === t.row.id"
                         class="el-icon-check" style="color:#67c23a; font-weight:bold;"
                         title="Escalón aplicado"></i>
                    </template>
                  </el-table-column>
                </el-table>
              </div>

              <div class="ai-inferred-details__footer">
                <el-button v-if="isEditable" size="mini" type="success" icon="el-icon-check"
                           @click="saveItem(props.row)">
                  Guardar detalles
                </el-button>
                <span class="ai-inferred-details__hint">
                  <i class="el-icon-magic-stick"></i>
                  = campo sugerido por la IA. Al editarlo, la marca desaparece.
                </span>
              </div>
            </div>
          </template>
        </el-table-column>

        <!-- Kit: check para agrupar (no en comisiones ni en filas kit) -->
        <el-table-column label="Kit" width="118" align="center">
          <template slot="header">
            <span class="col-tip" title="Marca 2 o más productos para crear un kit. La comisión no entra a kits.">Kit <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <!-- [NUEVO FLUJO 2026-10-21] Kits ocultados momentaneamente (pedido del
                 jefe): el check de marcado queda oculto. Si el item YA pertenece a
                 un kit, se sigue mostrando el kit y el botón "Disolver". -->
            <el-checkbox v-if="false"
                         :value="kitSelection.indexOf(scope.row.id) !== -1"
                         :disabled="!isEditable"
                         @change="toggleKitSelected(scope.row)"></el-checkbox>
            <template v-if="scope.row._isKit">
              <el-button size="mini" type="danger" plain
                         :disabled="!isEditable" @click="askDissolveKit(scope.row.kit)">Disolver</el-button>
            </template>
          </template>
        </el-table-column>

        <el-table-column prop="item_order" label="#" width="40" align="center">
          <template slot-scope="scope">{{ scope.row._isKit ? scope.row._displayNum : scope.row.item_order }}</template>
        </el-table-column>

        <el-table-column label="Producto solicitado" min-width="110">
          <template slot-scope="scope">
            <!-- Cadena if/else-if ÚNICA: no agregar otro v-if aquí (ver AGENTS.md §5). -->
            <el-input v-if="!scope.row._isKit && !isComision(scope.row)" v-model="scope.row.product_name" size="mini"
                      :disabled="!isEditable"
                      :placeholder="isService(scope.row) ? 'Servicio' : ''"
                      @input="onItemNameChanged(scope.row, $event)"></el-input>
            <span v-else-if="!scope.row._isKit" style="font-size:12px;">{{ scope.row.product_name || 'COMISIÓN' }}</span>
            <el-input v-else v-model="scope.row.kit.nombre" size="mini" :disabled="!isEditable" maxlength="191"></el-input>
          </template>
        </el-table-column>

        <!-- Columna NUEVA: foto editable POR ÍTEM (multimodal). Mismo patrón que la imagen del requerimiento. -->
        <el-table-column label="Foto" width="76" align="center">
          <template slot="header">
            <span class="col-tip" title="Imagen de referencia de este producto. La IA puede usarla para inferir mejor material, técnica y medidas.">Foto <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <div v-if="!scope.row._isKit" class="item-foto-cell">
              <img v-if="itemImageUrl(scope.row)" :src="itemImageUrl(scope.row)" alt="foto"
                   class="item-foto-thumb item-foto-click"
                   :title="isEditable ? 'Click para cambiar la foto' : 'Foto del producto'"
                   @click="isEditable && triggerItemImageUpload(scope.row)"/>
              <span v-else class="item-foto-empty item-foto-click"
                    :title="isEditable ? 'Click para subir la foto' : ''"
                    @click="isEditable && triggerItemImageUpload(scope.row)"><i class="el-icon-picture-outline"></i></span>

              <div v-if="isEditable && itemImageUrl(scope.row)" class="item-foto-actions">
                <el-button size="mini" type="text" class="item-foto-del"
                           icon="el-icon-delete"
                           @click="deleteItemImage(scope.row)">
                  Quitar
                </el-button>
              </div>
            </div>
            <div v-else class="item-foto-cell">
              <img v-if="kitPhotoUrl(scope.row.kit)" :src="kitPhotoUrl(scope.row.kit)" alt="foto kit"
                   class="item-foto-thumb"/>
              <span v-else class="item-foto-empty" title="Sin foto"><i class="el-icon-picture-outline"></i></span>
              <div v-if="isEditable && !scope.row.kit._isNew" class="item-foto-actions">
                <el-button size="mini" type="text" icon="el-icon-upload2"
                           :loading="uploadingKitPhotoId === scope.row.kit.id"
                           @click="triggerKitPhotoUpload(scope.row.kit)">
                  {{ kitPhotoUrl(scope.row.kit) ? 'Cambiar' : 'Subir' }}
                </el-button>
              </div>
            </div>
          </template>
        </el-table-column>

        <!-- Columna sin nombre: tipo de línea (mockup db). En kit solo
             producto/servicio (la comisión no entra a kits). -->
        <el-table-column label="" width="112">
          <template slot-scope="scope">
            <el-select v-if="!scope.row._isKit" v-model="scope.row.tipo_linea" size="mini" :disabled="!isEditable"
                       title="Tipo de línea" @change="onTipoLineaChange(scope.row)"
                       style="width:100%;">
              <el-option label="Producto" value="product"></el-option>
              <el-option label="Servicio" value="service"></el-option>
              <el-option label="Comisión" value="comision" :disabled="itemInKit(scope.row)"></el-option>
            </el-select>
          </template>
        </el-table-column>

        <!-- Columna NUEVA: producto del catálogo + badge de matching -->
        <el-table-column label="Producto del catálogo" min-width="150">
          <template slot="header">
            <span class="col-tip" title="Producto del catálogo de Compipro asociado a lo que pidió el cliente (sugerido por la IA).">Producto del catálogo <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <div v-if="!scope.row._isKit && !isComision(scope.row) && !isService(scope.row)">
              <!-- Estado: alta confianza -->
              <el-tag v-if="matchStatus(scope.row) === 'matched'" size="mini" type="success" effect="plain">
                <i class="el-icon-success"></i>
                {{ scope.row.catalog_item ? scope.row.catalog_item.name : ('ID ' + scope.row.item_id) }}
              </el-tag>
              <!-- Estado: necesita review -->
              <el-tag v-else-if="matchStatus(scope.row) === 'review'" size="mini" type="warning" effect="dark"
                      style="cursor:pointer;" @click="openMatchDialog(scope.row)">
                <i class="el-icon-warning-outline"></i> Revisar producto
              </el-tag>
              <!-- Estado: sin match -->
              <el-tag v-else size="mini" type="info" effect="plain"
                      style="cursor:pointer;" @click="openMatchDialog(scope.row)">
                <i class="el-icon-search"></i> {{ isService(scope.row) ? 'Sin catálogo' : 'Sin match — buscar' }}
              </el-tag>

              <!-- Botón pequeño para abrir el dialog -->
              <el-button v-if="matchStatus(scope.row) === 'matched'"
                         size="mini" icon="el-icon-edit" type="text" style="padding:0; margin-left:4px;"
                         @click="openMatchDialog(scope.row)" title="Cambiar producto"></el-button>

              <!-- Confianza visual -->
              <div v-if="scope.row.product_match_confidence !== null && scope.row.product_match_confidence !== undefined"
                   style="margin-top:2px; font-size:10px; color:#909399;">
                Confianza: {{ Math.round((parseFloat(scope.row.product_match_confidence) || 0) * 100) }}%
              </div>
            </div>
            <el-tag v-else-if="scope.row._isKit" size="mini" type="warning" effect="plain">Kit compuesto</el-tag>
            <el-tag v-else-if="isService(scope.row)" size="mini" type="info" effect="plain">Sin catálogo</el-tag>
            <el-tag v-else size="mini" type="info" effect="plain">Comisión</el-tag>
            <div v-if="scope.row._isKit" style="margin-top:2px; font-size:10px; color:#909399;">
              {{ kitMemberCount(scope.row.kit) }} componentes · Margen real {{ kitRealMarginPct(scope.row.kit).toFixed(2) }}%
            </div>
          </template>
        </el-table-column>

        <el-table-column label="Cant." width="88">
          <template slot-scope="scope">
            <el-input-number v-if="!scope.row._isKit" v-model="scope.row.quantity" size="mini" :min="1" :max="999999"
                             controls-position="right" :disabled="!isEditable || isComision(scope.row)" class="campo-precio"></el-input-number>
            <el-input-number v-else v-model="scope.row.kit.cantidad" size="mini" :min="1" :max="999999"
                             controls-position="right" :disabled="!isEditable" class="campo-precio"
                             title="Al guardar se aplica a todo el kit"></el-input-number>
          </template>
        </el-table-column>
        <el-table-column label="Costo Base" width="118" align="right">
          <template slot="header">
            <span class="col-tip" title="Costo unitario base del producto, antes de aplicar el margen. Se toma del proveedor seleccionado en la comparativa (solo lectura).">Costo Base <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <template v-if="!scope.row._isKit">
            <!-- [NUEVO FLUJO 2026-10-21] Solo lectura: el costo base del item es
                 el del proveedor SELECCIONADO en la comparativa (se edita allá). -->
            <span class="campo-calculado">{{ selectedQuoteCost(scope.row) }}</span>
            <!-- Fase C — Badge "Escalón aplicado" cuando el costo viene de item_unit_types -->
            <div v-if="scope.row.tier_aplicado" class="tier-aplicado-badge">
              <el-tooltip
                :content="'Precio del escalón ' + scope.row.tier_aplicado.quantity_unit +
                          '+ unidades. Al cambiar la cantidad, el costo se recalcula automáticamente.'"
                placement="top">
                <span><i class="el-icon-collection-tag"></i> ≥{{ formatTierQty(scope.row.tier_aplicado.quantity_unit) }} u → S/ {{ scope.row.tier_aplicado.price1.toFixed(2) }}</span>
              </el-tooltip>
            </div>
            </template>
            <span v-else class="campo-calculado">S/ {{ kitRowTotals(scope.row.kit).costo.toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="Margen %" width="108" align="right">
          <template slot="header">
            <span class="col-tip" title="Porcentaje de ganancia aplicado sobre el costo base.">Margen % <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <el-tooltip v-if="!scope.row._isKit"
              :disabled="!isUnderMinMargin(scope.row)"
              :content="minMarginTooltip(scope.row)"
              placement="top" effect="dark">
              <el-input-number v-model="scope.row.margen_porcentaje" size="mini" :precision="1" :step="5"
                               :min="0" :max="999" controls-position="right" :disabled="!isEditable || isComision(scope.row)"
                               class="campo-precio"
                               :class="{ 'campo-margen-bajo': isUnderMinMargin(scope.row) }">
              </el-input-number>
            </el-tooltip>
            <span v-else class="campo-calculado">{{ kitRowTotals(scope.row.kit).margenPct.toFixed(2) }}%</span>
            <div v-if="!scope.row._isKit && isUnderMinMargin(scope.row)" class="campo-margen-bajo__hint">
              <i class="el-icon-warning"></i> Bajo el mínimo ({{ scope.row.min_margin_aplicable }}%)
            </div>
          </template>
        </el-table-column>
        <el-table-column label="Margen (S/)" width="96" align="right">
          <template slot="header">
            <span class="col-tip" title="Ganancia en soles del ítem (markup sobre el costo × cantidad). Solo lectura.">Margen (S/) <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <span v-if="!scope.row._isKit" class="campo-calculado campo-margen-soles">{{ itemGainValue(scope.row) }}</span>
            <span v-else class="campo-calculado campo-margen-soles">S/ {{ kitRowTotals(scope.row.kit).margenS.toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="V. Unit." width="86" align="right">
          <template slot="header">
            <span class="col-tip" title="Unitario de venta: Total sin IGV ÷ cantidad (ya incluye muestras, movilidad, impresión, empaque y SCTR). Es el mismo número que el del proveedor ganador en la comparativa.">V. Unit. <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <span v-if="!scope.row._isKit" class="campo-calculado">{{ itemUnitValue(scope.row) }}</span>
            <span v-else class="campo-calculado">S/ {{ kitRowTotals(scope.row.kit).vunit.toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="Total" width="92" align="right">
          <template slot-scope="scope">
            <span v-if="!scope.row._isKit" class="campo-calculado"><strong>{{ itemTotalValue(scope.row) }}</strong></span>
            <span v-else class="campo-calculado"><strong>S/ {{ kitRowTotals(scope.row.kit).total.toFixed(2) }}</strong></span>
          </template>
        </el-table-column>
        <el-table-column label="" width="100" align="center">
          <template slot-scope="scope">
            <el-button v-if="scope.row._isKit" size="mini" type="success" icon="el-icon-check" circle
                       :loading="!!kitSaving[scope.row.kit._kid]"
                       :disabled="!isEditable" @click="saveKit(scope.row.kit)"
                       title="Guardar kit"></el-button>
            <template v-if="!scope.row._isKit">
            <el-button size="mini" type="success" icon="el-icon-check" circle
                       @click="saveItem(scope.row)" :disabled="!isEditable"
                       title="Guardar cambios del producto"></el-button>
            <!-- Agosto 2026 (punto 5): duplicar para cotizaciones por escala —el
                 mismo producto a 50, 100 y 300 unidades— sin cargarlo de cero. -->
            <el-button size="mini" type="primary" icon="el-icon-document-copy" circle
                       @click="duplicarItem(scope.row)"
                       :disabled="!isEditable" :loading="duplicandoItemId === scope.row.id"
                       title="Duplicar producto (copia imagen, descripción, personalización y costos)"></el-button>
            <el-button size="mini" type="danger" icon="el-icon-delete" circle
                       @click="deleteItem(scope.row)" :disabled="!isEditable"
                       title="Eliminar producto"></el-button>
            </template>
          </template>
        </el-table-column>
      </el-table>

      <!-- Aviso: faltan proveedores para Validar/Aprobar (requisito de conversion a proyecto) -->
      <el-alert v-if="hayProductosSinProveedor && !prequote.quotation_id"
                type="warning" :closable="false" show-icon class="mt-2"
                :title="productosSinProveedor + ' producto(s) sin proveedor. Asigna un proveedor a cada producto en la pestaña \'Proveedores\' para poder Validar y Aprobar la pre-cotizacion.'">
      </el-alert>

      <!-- Acciones -->
      <el-row type="flex" justify="end" class="mt-2">
        <el-button v-if="isEditable" type="warning" size="small" :disabled="hayProductosSinProveedor"
                   @click="changeStatus('validada')" icon="el-icon-circle-check">
          Validar
        </el-button>
        <el-button v-if="prequote.estado === 'validada'" type="success" size="small" :disabled="hayProductosSinProveedor"
                   @click="changeStatus('aprobada')" icon="el-icon-check">
          Aprobar
        </el-button>
        <el-button v-if="prequote.estado === 'aprobada' && !prequote.quotation_id"
                   type="primary" size="small" @click="convertToQuotation" :loading="converting"
                   icon="el-icon-document">
          Convertir a Cotización Formal
        </el-button>
        <el-tag v-if="prequote.quotation_id" type="success" size="medium" class="ml-2">
          Convertida - Quotation #{{ prequote.quotation_id }}
        </el-tag>
        <el-button v-if="!prequote.quotation_id" size="small"
                   @click="generatePrequote" :loading="generating" icon="el-icon-refresh">
          Regenerar
        </el-button>
      </el-row>
    </div>

    <!-- Dialog: Revisar / elegir producto del catálogo -->
    <el-dialog :title="matchDialogTitle" :visible.sync="matchDialogVisible" width="880px"
               custom-class="product-match-dialog" append-to-body
               @close="onMatchDialogClose">

      <div v-if="matchTarget">
        <!-- Bloque resumen del ítem -->
        <el-card shadow="never" style="margin-bottom: 14px; background:#fafafa;">
          <div style="font-size:12px; color:#909399;">PRODUCTO SOLICITADO POR EL CLIENTE</div>
          <div style="font-size:15px; font-weight:600;">{{ matchTarget.product_name }}</div>
          <div v-if="matchTarget.product_description" style="font-size:12px; color:#606266; margin-top:2px;">
            {{ matchTarget.product_description }}
          </div>
          <div style="margin-top:6px;">
            <el-tag size="mini" type="info">Cant: {{ matchTarget.quantity }}</el-tag>
            <el-tag v-if="matchTarget.personalizacion" size="mini" type="info" style="margin-left:4px;">
              {{ matchTarget.personalizacion }}
            </el-tag>
          </div>
        </el-card>

        <!-- Sugerencias de la IA -->
        <div style="margin-bottom:8px;">
          <strong>Sugerencias de la IA:</strong>
          <span style="color:#909399; font-size:12px;">
            (ordenadas por similitud)
          </span>
        </div>
        <el-table v-if="matchSuggestions.length > 0" :data="matchSuggestions" size="small" border style="margin-bottom:14px;">
          <el-table-column label="Producto" min-width="200">
            <template slot-scope="s">
              <strong>{{ s.row.name }}</strong>
              <div v-if="s.row.description" style="font-size:11px; color:#606266;">
                {{ s.row.description }}
              </div>
              <div v-if="s.row.reason" style="font-size:11px; color:#909399; font-style:italic;">
                {{ s.row.reason }}
              </div>
            </template>
          </el-table-column>
          <el-table-column label="Precio ref." width="100" align="right">
            <template slot-scope="s">
              S/ {{ parseFloat(s.row.sale_unit_price || 0).toFixed(2) }}
            </template>
          </el-table-column>
          <el-table-column label="Score" width="80" align="center">
            <template slot-scope="s">
              <el-tag :type="scoreTagType(s.row.score)" size="mini" effect="plain">
                {{ Math.round((parseFloat(s.row.score) || 0) * 100) }}%
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="" width="100" align="center">
            <template slot-scope="s">
              <el-button size="mini" type="primary" plain
                         :loading="actingSuggestionId === s.row.item_id"
                         @click="applyMatch(s.row.item_id)">
                Elegir
              </el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-alert v-else type="info" :closable="false" show-icon
                  style="margin-bottom:14px;"
                  title="No hay sugerencias automáticas — usa la búsqueda manual abajo."></el-alert>

        <!-- Búsqueda manual en tarjetas (mismo endpoint de catálogo) -->
        <div style="margin-bottom:8px;">
          <strong>Buscar otro producto en el catálogo:</strong>
        </div>
        <el-input v-model="manualQuery" clearable
                  placeholder="Escribe nombre o descripción para buscar (mínimo 2 caracteres)"
                  style="width:100%;" @input="fetchManualResults">
          <i slot="prefix" class="el-input__icon el-icon-search"></i>
        </el-input>
        <div v-loading="manualSearching" class="catalog-cards">
          <div v-for="r in manualResults" :key="r.item_id" class="catalog-card"
               @click="onManualSelect(r)" :title="'Elegir: ' + (r.name || '')">
            <div class="catalog-card__badge">
              <img v-if="r.image_url_small" :src="r.image_url_small" :alt="r.name"
                   @error="$event.target.style.display='none'"/>
              <span v-else>{{ catalogInitials(r) }}</span>
            </div>
            <div class="catalog-card__body">
              <div class="catalog-card__head">
                <strong>{{ r.name }}</strong>
                <span class="catalog-card__score">{{ Math.round((parseFloat(r.score) || 0) * 100) }}% coincidencia</span>
              </div>
              <div v-if="r.description" class="catalog-card__desc">{{ truncate(r.description, 110) }}</div>
              <div class="catalog-card__meta">
                <span v-if="r.item_code">{{ r.item_code }}</span>
                <span>Desde S/ {{ parseFloat(r.sale_unit_price || 0).toFixed(2) }}</span>
              </div>
              <div class="catalog-card__cta">
                <span v-if="actingSuggestionId === r.item_id"><i class="el-icon-loading"></i> Cargando…</span>
                <span v-else class="catalog-card__cta-link">Click para cargar en el ítem</span>
              </div>
            </div>
          </div>
          <div v-if="!manualSearching && manualQuery.trim().length >= 2 && manualResults.length === 0"
               class="catalog-cards__empty">
            Sin resultados — prueba con otras palabras.
          </div>
        </div>
      </div>

      <div slot="footer" style="display:flex; justify-content:space-between;">
        <div>
          <el-button @click="rematchWithAI" :loading="rematchLoading" icon="el-icon-magic-stick" type="text">
            Re-buscar con IA
          </el-button>
        </div>
        <div>
          <el-button @click="applyMatch(null)" type="info" plain :loading="actingSuggestionId === 'none'">
            Ninguno corresponde
          </el-button>
          <el-button @click="matchDialogVisible = false">Cerrar</el-button>
        </div>
      </div>
    </el-dialog>

    <!-- [NUEVO FLUJO 2026-10-20] Dialog: Buscar referencia en catálogo.
         YA NO SE USA: el botón "Buscar" fue eliminado de la comparativa porque
         el flujo nuevo no mapea productos del catálogo en proveedores.
         El código se conserva por si se necesita en el futuro. -->
    <el-dialog title="Buscar referencia en catálogo" :visible.sync="quoteRefDialogVisible" width="640px"
               append-to-body>
      <div v-if="quoteRefTarget">
        <div style="font-size:12px; color:#909399; margin-bottom:8px;">
          Cotización de <strong>{{ quoteRefTarget.quote.supplier_name }}</strong> — Ítem #{{ quoteRefTarget.item.item_order }}
        </div>
        <el-input v-model="quoteRefQuery" size="small" placeholder="Escribe para buscar en el catálogo (mínimo 2 caracteres)"
                  @input="searchQuoteRef" clearable style="margin-bottom:10px;">
          <el-button slot="append" icon="el-icon-search" @click="searchQuoteRef">Buscar</el-button>
        </el-input>
        <div v-loading="quoteRefLoading">
          <div v-if="quoteRefResults.length === 0" style="color:#909399; font-size:12px;">Sin resultados.</div>
          <el-table v-else :data="quoteRefResults" size="mini" border style="width:100%;">
            <el-table-column label="Producto" min-width="200">
              <template slot-scope="s">{{ s.row.name || s.row.titulo || s.row.description }}</template>
            </el-table-column>
            <el-table-column label="Precio ref." width="110" align="right">
              <template slot-scope="s">S/ {{ parseFloat(s.row.precio_ref || s.row.sale_unit_price || 0).toFixed(2) }}</template>
            </el-table-column>
            <el-table-column label="" width="90" align="center">
              <template slot-scope="s">
                <el-button size="mini" type="primary" plain @click="pickQuoteRef(s.row)">Elegir</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>
      <div slot="footer">
        <el-button @click="quoteRefDialogVisible = false">Cerrar</el-button>
      </div>
    </el-dialog>
  </div>
</template>

<script>
// Agosto 2026 (punto 6): el redondeo comercial se importa del helper compartido
// en vez de reescribirlo acá. Es la misma función que usa el Margen (%) del
// Proyecto, así los dos lados no se pueden desincronizar.
import { redondeoComercial } from '../../lib/costeoReal'

// COM-02 · Objetivo de margen real (%) que usan "Precio Necesario para X%" y
// "Precio Unitario a Vender (p/X%)" del bloque de Costo Real.
// Decisión de negocio: hoy es FIJO. Si algún día se vuelve configurable, este
// es el ÚNICO lugar a cambiar: los rótulos lo leen desde aquí, no lo repiten.
var MARGEN_REAL_OBJETIVO_PCT = 40

export default {
  props: {
    requerimientoId: { type: [Number, String], default: null },
    // Modo standalone: si viene prequoteId, carga directamente la prequote sin
    // buscar por requerimiento_id. Usado por la vista del Cotizador independiente.
    prequoteId: { type: [Number, String], default: null }
  },
  data() {
    return {
      prequote: null,
      loading: false,
      generating: false,
      converting: false,
      // Agosto 2026 (punto 5): id del ítem que se está duplicando, para el spinner
      // del botón. Null = ninguno en curso.
      duplicandoItemId: null,
      // Kit creando (spinner del botón de kit en la tabla de requerimientos)
      kitCreating: false,
      // Match dialog
      matchDialogVisible: false,
      matchTarget: null,        // PreQuoteItem actual
      matchSuggestions: [],     // Lista de candidatos del catálogo
      manualQuery: '',
      manualResults: [],      // Resultados de la búsqueda manual (tarjetas)
      manualSearching: false,
      manualSearchTimer: null,
      manualSearchSeq: 0,     // evita que una respuesta lenta pise una nueva
      actingSuggestionId: null, // ID del item del catálogo que se está aplicando
      rematchLoading: false,
      // S2 — Imagen de referencia
      uploadingImage: false,
      deletingImage: false,
      reinterpretingImage: false,
      imagePreviewVisible: false,
      imageVersion: 0,          // bust cache cuando cambia
      // Imagen POR ÍTEM (mismo patrón que la del requerimiento)
      uploadingItemImageId: null, // id del ítem cuya imagen se está subiendo
      itemImageVersion: 0,        // bust cache de las miniaturas por ítem
      // Porcentajes para el preview de costeo. Son los defaults; el backend
      // usa los configurables de quotation_config (sctr_porcentaje, igv_percentage).
      sctrPct: 1.5,
      igvPct: 18,
      // COM-02 · "Referencia del producto" — agregar 1 ítem por IA (texto XOR imagen)
      referenceText: '',
      referenceImageFile: null,
      referenceImagePreview: '',
      referenceMode: null,      // 'texto' | 'imagen' | null
      interpretingItem: false,
      // Ítem manual (alternativa sin IA): mismo patrón de flag que interpretingItem.
      addingManual: false,
      // Rediseño: acordeones de ingreso (IA abierto por defecto, kits cerrado)
      // + kits solo-frontales (el backend llega después).
      iaCollapseOpen: ['ia'],
      kitCollapseOpen: [],
      // Kits/paquetes (backend): selección + borradores + estado por kit.
      kitSelection: [],       // ids de ítems sueltos marcados con el check Kit
      kitAction: 'new',       // 'new' o 'add:<kitId>'
      kitNameInput: '',
      kits: [],               // kits del servidor (+ borradores locales _isNew)
      kitsLoadedFor: null,    // prequote.id ya cargado
      kitSaving: {},          // _kid -> true
      kitDraftSeq: 1,
      uploadingKitPhotoId: null,
      kitPhotoVersion: 0,
      pendingKitPhotoId: null,
      // Comparativa de proveedores por ítem (mockup db)
      supplierQuotesMap: {},     // itemId -> [quotes]
      supplierQuotesLoaded: {},  // itemId -> true (ya cargadas)
      supplierQuotesLoading: {}, // itemId -> true (cargando)
      supplierOptionsMap: {},    // quoteId -> [opciones de proveedor]
      supplierSearching: false,
      // [NUEVO FLUJO 2026-10-20] Lista global de proveedores, precargada al
      // abrir la comparativa para que el dropdown muestre todos sin escribir.
      allSuppliers: [],           // [{id, razon_social, ruc, nombre_comercial}]
      suppliersPreloaded: false,  // ya se pidió la lista completa
      // Texto que el usuario escribe en el combo: el filtro es LOCAL.
      supplierFilterQuery: '',
      // [NUEVO FLUJO 2026-10-21] Buffer de cambios pendientes por ítem.
      // itemId -> { quotes: [copia local], dirty: bool }
      // Los cambios NO se envían al backend hasta "Guardar detalles".
      supplierQuoteBuffers: {},
      // [NUEVO FLUJO 2026-10-21] Copia de lo que había en BD por ítem
      // (para detectar qué se borró). Antes vivía en el objeto del ítem
      // (item._originalQuotes) y se perdía al re-renderizar la fila.
      supplierQuoteOriginals: {},
      quoteRefDialogVisible: false,
      quoteRefTarget: null,      // { item, quote }
      quoteRefQuery: '',
      quoteRefResults: [],
      quoteRefLoading: false,
    }
  },
  computed: {
    isEditable() {
      if (!this.prequote) return false
      return ['borrador', 'en_revision', 'ajustada'].indexOf(this.prequote.estado) !== -1
    },
    isStandalone() {
      return !!this.prequoteId
    },
    itemsNeedingReview() {
      if (!this.prequote || !this.prequote.items) return 0
      return this.prequote.items.filter(function (it) {
        return !!it.needs_product_review
      }).length
    },
    itemsWithInferred() {
      if (!this.prequote || !this.prequote.items) return 0
      return this.prequote.items.filter(function (it) {
        return Array.isArray(it.datos_inferidos) && it.datos_inferidos.length > 0
      }).length
    },
    // Productos sin proveedor asignado: bloquean Validar/Aprobar.
    // (Requisito para que el requerimiento pueda convertirse luego en proyecto.)
    // La comisión no lleva proveedor: se excluye del conteo.
    productosSinProveedor() {
      if (!this.prequote || !this.prequote.items) return 0
      return this.prequote.items.filter(function (it) {
        if (String((it && it.tipo_linea) || '').toLowerCase() === 'comision') return false
        return !it.supplier_id
      }).length
    },
    hayProductosSinProveedor() {
      return this.productosSinProveedor > 0
    },
    matchDialogTitle() {
      if (!this.matchTarget) return 'Producto del catálogo'
      return 'Asociar producto del catálogo — Ítem #' + this.matchTarget.item_order
    },
    referenceImageUrl() {
      if (!this.prequote || !this.prequote.reference_image_path) return null
      // Endpoint sirve la imagen con auth de sesión.  imageVersion sirve para
      // forzar refresh tras cambio (cache-bust).
      return '/marketing/quotation/prequotes/' + this.prequote.id + '/reference-image?v=' + this.imageVersion
    },
    // COM-02 · id del requerimiento para el endpoint de "interpretar ítem"
    // (fallback al de la prequote en modo standalone/cotizador independiente).
    refReqId() {
      return this.requerimientoId || (this.prequote && this.prequote.requerimiento_id) || null
    },

    /*
    |------------------------------------------------------------------
    | COM-02 · Costo Real — Resumen General (en vivo desde los ítems)
    |------------------------------------------------------------------
    | Recorre la MISMA cadena que `costeoBreakdown`, así el encabezado y la
    | tarjeta de cada ítem siempre cierran entre sí.
    |
    | Subtotal / IGV / TOTAL / Margen Ganancia siguen siendo los valores
    | PERSISTIDOS que devuelve el backend (la verdad después de guardar);
    | estos cinco se calculan en vivo para que reflejen lo que hay en
    | pantalla aunque todavía no se haya guardado.
    */
    resumenCostoReal() {
      var acc = { utilidadReal: 0, movilidad: 0, empaque: 0, seguro: 0, totalSinIgv: 0 }
      if (!this.prequote || !Array.isArray(this.prequote.items)) return acc
      for (var i = 0; i < this.prequote.items.length; i++) {
        var bd = this.costeoBreakdown(this.prequote.items[i])
        if (!bd) continue
        acc.utilidadReal += bd.utilidadReal
        acc.movilidad    += bd.movilidad
        acc.empaque      += bd.empaque      // efectivo (× cantidad si es "por unidad")
        acc.seguro       += bd.sctr         // SCTR NORMAL (el de venta), como pide el documento
        acc.totalSinIgv  += bd.totalSinIgv
      }
      return acc
    },

    /**
     * Margen Real % del pedido completo.
     * El denominador es el Total sin IGV EN VIVO (no el subtotal persistido):
     * mezclar un numerador vivo con un denominador guardado daría un
     * porcentaje sin sentido a mitad de una edición sin guardar.
     */
    margenRealTotalPct() {
      var r = this.resumenCostoReal
      return r.totalSinIgv > 0 ? (r.utilidadReal / r.totalSinIgv) * 100 : 0
    },

    /**
     * Promedio simple del Margen % ingresado por ítem (columna "Prom.
     * margen ingresado" de la barra de totales).
     */
    promedioMargenIngresado() {
      if (!this.prequote || !Array.isArray(this.prequote.items) || !this.prequote.items.length) return 0
      var sum = 0
      for (var i = 0; i < this.prequote.items.length; i++) {
        sum += parseFloat(this.prequote.items[i].margen_porcentaje) || 0
      }
      return sum / this.prequote.items.length
    },

    /** Aclaración (tooltip) de los 5 indicadores nuevos del encabezado. */
    tituloEnVivo() {
      return 'Se calcula en vivo con lo que está en pantalla. Subtotal, IGV y TOTAL se actualizan al guardar.'
    },
  },
  watch: {
    requerimientoId: {
      immediate: true,
      handler(newId) {
        if (newId && !this.prequoteId) this.loadPrequote()
      }
    },
    prequoteId: {
      immediate: true,
      handler(newId) {
        if (newId) this.loadPrequoteById(newId)
      }
    },
    // Comparativa + kits: al cambiar la precotización, cargar cotizaciones de
    // los ítems aún no cargados (una sola vez por ítem) y los kits.
    prequote: {
      handler(pq) {
        var self = this
        if (!pq || !Array.isArray(pq.items)) {
          this.kits = []
          this.kitsLoadedFor = null
          this.kitSelection = []
          return
        }
        pq.items.forEach(function (it) {
          if (it && it.id && !self.supplierQuotesLoaded[it.id]) self.loadSupplierQuotes(it)
        })
        // [NUEVO FLUJO] Precarga todos los proveedores para los dropdowns
        this.preloadAllSuppliers()
        this.loadKits()
      }
    }
  },
  mounted() {
    // Precarga la lista de proveedores aunque la prequote ya venga cargada
    // (el watcher "prequote" no es immediate y no se disparaba).
    this.preloadAllSuppliers()
  },
  methods: {
    // COM-02 R3 — Guarda el % de SCTR de la cotización y repinta con la prequote
    // recalculada que devuelve el backend (items + totales + desglose).
    async saveSctr() {
      if (!this.prequote || !this.isEditable) return
      try {
        var resp = await this.$http.put(
          '/marketing/quotation/prequotes/' + this.prequote.id + '/sctr',
          { sctr_porcentaje: this.sctrPct }
        )
        if (resp.data && resp.data.prequote) {
          this.prequote = this.parseNumericFields(resp.data.prequote)
        }
        this.$message({ type: 'success', message: 'SCTR actualizado', duration: 1500 })
      } catch (e) {
        var msg = e.response && e.response.data && e.response.data.message
          ? e.response.data.message : 'No se pudo actualizar el SCTR.'
        this.$message.error(msg)
        // Revertir el input al valor real de la cotización.
        this.parseNumericFields(this.prequote)
      }
    },

    async loadPrequote() {
      this.loading = true
      try {
        var response = await this.$http.get('/marketing/quotation/prequotes', {
          params: { requerimiento_id: this.requerimientoId }
        })
        var list = response.data.prequotes.data || response.data.prequotes || []

        if (list.length > 0) {
          var detailResponse = await this.$http.get('/marketing/quotation/prequotes/' + list[0].id)
          this.prequote = this.parseNumericFields(detailResponse.data.prequote)
        } else {
          this.prequote = null
        }
      } catch (e) {
        this.prequote = null
      }
      this.loading = false
    },

    async loadPrequoteById(id) {
      this.loading = true
      try {
        var detailResponse = await this.$http.get('/marketing/quotation/prequotes/' + id)
        this.prequote = this.parseNumericFields(detailResponse.data.prequote)
      } catch (e) {
        this.prequote = null
      }
      this.loading = false
    },

    async generatePrequote() {
      this.generating = true
      try {
        var response = await this.$http.post('/marketing/quotation/generate', {
          requerimiento_id: this.requerimientoId,
        })

        var pq = response.data.prequote
        var nItems = pq && pq.items ? pq.items.length : 0

        if (nItems === 0) {
          // El motor de IA no devolvio productos. La causa mas frecuente es
          // saldo/conexion del proveedor de IA; tambien puede ser una
          // descripcion que el motor no logro interpretar.
          this.$message({
            message: 'La IA no devolvió productos. Causa más probable: el proveedor de IA del cotizador (COM-02) no respondió ' +
                     '(sin saldo o sin conexión). Revisa Marketing → Configuración de Agentes IA → COM-02 Cotizador, ' +
                     'o ajusta la descripción del requerimiento e inténtalo de nuevo.',
            type: 'warning',
            duration: 9000,
            showClose: true
          })
        } else {
          this.$message.success('Pre-cotización generada con ' + nItems + ' producto(s)')
        }

        this.prequote = this.parseNumericFields(response.data.prequote)
        this.$emit('precotizacion-added')
      } catch (e) {
        var msg = e.response && e.response.data && e.response.data.message ? e.response.data.message : 'Error generando pre-cotización'
        this.$message.error(msg)
      }
      this.generating = false
    },

    /**
     * Agosto 2026 (punto 5) — Duplicar un producto completo.
     *
     * El backend hace la copia (incluida la imagen, que apunta al mismo archivo)
     * y la deja justo debajo del original. Acá solo se dispara y se refresca la
     * pantalla con lo que devuelve, para no reconstruir el ítem en el navegador
     * y arriesgar que la copia de pantalla difiera de la guardada.
     */
    async duplicarItem(item) {
      this.duplicandoItemId = item.id
      try {
        var response = await this.$http.post(
          '/marketing/quotation/prequotes/' + this.prequote.id + '/item/' + item.id + '/duplicate'
        )
        if (response.data && response.data.prequote) {
          this.prequote = this.parseNumericFields(response.data.prequote)
        } else {
          await this.loadPrequoteById(this.prequote.id)
        }
        this.$message.success('Producto duplicado. Ajusta la cantidad de la copia.')
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message)
          ? e.response.data.message : 'No se pudo duplicar el producto'
        this.$message.error(msg)
      }
      this.duplicandoItemId = null
    },

    async deleteItem(item) {
      try {
        await this.$confirm(
          'Se eliminará el producto "' + (item.product_name || 'sin nombre') + '" de la pre-cotización. Esta acción no se puede deshacer.',
          'Eliminar producto',
          { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
        )
      } catch (e) {
        return // el usuario canceló
      }
      try {
        var response = await this.$http.delete(
          '/marketing/quotation/prequotes/' + this.prequote.id + '/item/' + item.id
        )
        if (response.data && response.data.prequote) {
          this.prequote = this.parseNumericFields(response.data.prequote)
        } else {
          this.loadPrequoteById(this.prequote.id)
        }
        this.$message.success('Producto eliminado')
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message)
          ? e.response.data.message : 'No se pudo eliminar el producto'
        this.$message.error(msg)
      }
    },

    /**
     * Combo tipo de línea (mockup db): normaliza y guarda; el backend enlaza
     * el ítem 271 si es comisión o suelta el match si es servicio.
     * Servicio ⇒ excluir_catalogo (vive solo en esta cotización; al
     * convertir no crea producto). El check manual se retiró.
     */
    onTipoLineaChange(item) {
      if (!item) return
      var v = String(item.tipo_linea || 'product').toLowerCase()
      if (['product', 'service', 'comision'].indexOf(v) === -1) v = 'product'
      item.tipo_linea = v
      // Comisión = línea propia con nombre fijo (sin input de solicitado).
      if (v === 'comision') {
        item.product_name = 'COMISIÓN'
        item.quantity = 1
        item.margen_porcentaje = 0
      }
      if (v === 'service') {
        item.excluir_catalogo = true
        // Si venía de COMISIÓN, el nombre fijo "COMISIÓN" ya no aplica: el
        // Servicio sí tiene "Producto solicitado" editable.
        if (String(item.product_name || '').trim().toUpperCase() === 'COMISIÓN') {
          item.product_name = 'Servicio'
        }
        // Servicio: solo Costo Base, Cantidad y Margen editables. Todo lo demás en 0.
        item.cantidad_colores = 1
        item.cantidad_lados = 1
        item.cantidad_millar = 1
        item.numero_muestras = 0
        item.precio_muestra = null
        item.costo_movilidad = 0
        item.costo_impresion = 0
        item.costo_empaque = 0
        item.impresion_por_unidad = 0
        item.empaque_por_unidad = 0
        item.sctr_porcentaje = null
        item.material = ''
        item.medida = ''
        item.empaque = ''
        item.personalizacion = ''
        item.tecnica_impresion = ''
        item.observaciones = ''
      }
      this.saveItem(item)
    },
    // Comisión = costo fijo (solo Costo Base editable).
    isComision(item) {
      return String((item && item.tipo_linea) || '').toLowerCase() === 'comision'
    },
    // Servicio = línea propia del requerimiento: solo Descripción, Costo Base,
    // Cantidad y Margen editables. Sin match, sin proveedores, sin catálogo.
    isService(item) {
      return String((item && item.tipo_linea) || '').toLowerCase() === 'service'
    },

    async saveItem(item) {
      // Nombre obligatorio (espejo del backend): evita el 500 por NOT NULL.
      if (!String(item.product_name || '').trim()) {
        this.$message.warning('Ponle un nombre al producto antes de guardar.')
        return
      }
      try {
        var payload = {
          product_name: item.product_name,
          product_description: item.product_description,
          quantity: item.quantity,
          // Tipo de línea (combo sin nombre en la tabla; comisión = ítem 271).
          tipo_linea: item.tipo_linea || 'product',
          personalizacion: item.personalizacion,
          tecnica_impresion: item.tecnica_impresion,
          // Detalles técnicos editables desde la fila expandible (badge "Inferido por IA")
          material: item.material,
          medida: item.medida,
          empaque: item.empaque,
          cantidad_colores: item.cantidad_colores,
          cantidad_lados: item.cantidad_lados,
          observaciones: item.observaciones,
          // [NUEVO FLUJO 2026-10-21] costo_base NO se manda: ya no se edita en el
          // item (es el del proveedor SELECCIONADO en la comparativa) y el motor
          // lo resuelve desde quotation_items_supplier.
          margen_porcentaje: item.margen_porcentaje,
          // Componentes de costo (Fase A — motor de costeo ampliado)
          numero_muestras: item.numero_muestras,
          // Agosto 2026 (punto 4): null cuando el checkbox está desmarcado, así
          // el backend vuelve a usar el costo base.
          precio_muestra: item._precio_muestra_distinto ? (item.precio_muestra !== '' ? item.precio_muestra : 0) : null,
          costo_movilidad: item.costo_movilidad,
          costo_impresion: item.costo_impresion,
          // COM-02 #2: factor manual de la impresión (Costo Total = costo × colores × lados × millar).
          cantidad_millar: item.cantidad_millar,
          costo_empaque: item.costo_empaque,
          impresion_por_unidad: item.impresion_por_unidad ? 1 : 0,
          empaque_por_unidad: item.empaque_por_unidad ? 1 : 0,
          // Agosto 2026 (punto 2): kit o servicio → no se le crea producto propio.
          excluir_catalogo: item.excluir_catalogo ? 1 : 0,
          // SCTR unitario (override por ítem; null = hereda el % de la cotización).
          sctr_porcentaje: (item.sctr_porcentaje === '' || item.sctr_porcentaje === undefined) ? null : item.sctr_porcentaje,
        }

        // Servicio = línea propia del requerimiento. Solo Costo Base, Cantidad y Margen.
        // Todo lo demás va en 0 para que el backend no los sume en el costo.
        if (String((item.tipo_linea || '').toLowerCase()) === 'service') {
          payload.personalizacion = ''
          payload.tecnica_impresion = ''
          payload.material = ''
          payload.medida = ''
          payload.empaque = ''
          payload.cantidad_colores = 1
          payload.cantidad_lados = 1
          payload.observaciones = ''
          payload.numero_muestras = 0
          payload.precio_muestra = null
          payload.costo_movilidad = 0
          payload.costo_impresion = 0
          payload.cantidad_millar = 1
          payload.costo_empaque = 0
          payload.impresion_por_unidad = 0
          payload.empaque_por_unidad = 0
          payload.sctr_porcentaje = null
        }

        var response = await this.$http.put(
          '/marketing/quotation/prequotes/' + this.prequote.id + '/item/' + item.id,
          payload
        )
        var pq = response.data.prequote
        this.prequote.subtotal = pq.subtotal
        this.prequote.total_igv = pq.total_igv
        this.prequote.total = pq.total
        this.$set(this.prequote, 'ganancia_total', pq.ganancia_total)

        if (pq.items) {
          for (var ii = 0; ii < pq.items.length; ii++) {
            if (parseInt(pq.items[ii].id) === parseInt(item.id)) {
              var updated = pq.items[ii]
              updated.quantity = parseInt(updated.quantity) || 0
              updated.costo_base = parseFloat(updated.costo_base) || 0
              updated.margen_porcentaje = parseFloat(updated.margen_porcentaje) || 0
              updated.sctr_porcentaje = this.parseSctrItem(updated.sctr_porcentaje)
              updated.sctr_porcentaje_efectivo = this.parseSctrEfectivo(updated.sctr_porcentaje_efectivo)
              // COM-02 #2: mantener cantidad_millar como número tras guardar (≤0/inválido → 1).
              updated.cantidad_millar = parseFloat(updated.cantidad_millar)
              if (isNaN(updated.cantidad_millar) || updated.cantidad_millar <= 0) updated.cantidad_millar = 1
              // Agosto 2026 (punto 4): el ítem que devuelve el backend no trae la
              // bandera de pantalla del checkbox, así que hay que rederivarla de
              // `precio_muestra`. Sin esto, el check se desmarcaba solo al guardar
              // y el guardado siguiente volvía a dejar el costo base.
              this.normalizarPrecioMuestra(updated)
              for (var jj = 0; jj < this.prequote.items.length; jj++) {
                if (parseInt(this.prequote.items[jj].id) === parseInt(item.id)) {
                  this.$set(this.prequote.items, jj, updated)
                  break
                }
              }
              break
            }
          }
        }
        this.$message.success('Item actualizado')
        // [NUEVO FLUJO 2026-10-20] Sincroniza los proveedores pendientes con el backend
        // después de guardar el ítem. Solo hace algo si hay cambios en el buffer.
        try {
          await this.flushSupplierQuotes(item)
        } catch (flushErr) {
          console.error('flushSupplierQuotes error:', flushErr)
        }
      } catch (e) {
        // Mostrar mensaje real del backend (status + mensaje) para facilitar el diagnóstico.
        var status = e.response && e.response.status ? e.response.status : ''
        var apiMsg = e.response && e.response.data && e.response.data.message ? e.response.data.message : (e.message || 'error desconocido')
        var fullMsg = status ? ('Error actualizando item (' + status + '): ' + apiMsg) : ('Error actualizando item: ' + apiMsg)
        this.$message({ message: fullMsg, type: 'error', duration: 8000 })
        // También loggear al console para inspección con DevTools
        if (e.response) {
          console.error('saveItem error:', e.response.status, e.response.data)
        } else {
          console.error('saveItem error (sin response):', e)
        }
      }
    },

    async changeStatus(newStatus) {
      try {
        await this.$http.put(
          '/marketing/quotation/prequotes/' + this.prequote.id + '/status',
          { estado: newStatus }
        )
        this.$message.success('Estado cambiado a: ' + newStatus)
        this.loadPrequote()
      } catch (e) {
        this.$message.error('Error cambiando estado')
      }
    },

    async convertToQuotation() {
      try {
        await this.$confirm(
          'Se creará una Cotización formal en el sistema de Preventa vinculada a este requerimiento. ¿Continuar?',
          'Convertir a Cotización',
          { confirmButtonText: 'Convertir', cancelButtonText: 'Cancelar', type: 'warning' }
        )
      } catch (e) {
        return
      }

      this.converting = true
      try {
        var response = await this.$http.post(
          '/marketing/quotation/prequotes/' + this.prequote.id + '/convert'
        )
        this.$message.success('Cotización formal creada (ID: ' + response.data.quotation_id + ')')
        this.loadPrequote()
        this.$emit('precotizacion-added')
      } catch (e) {
        var msg = e.response && e.response.data && e.response.data.message ? e.response.data.message : 'Error al convertir'
        if (e.response && e.response.status === 422) {
          // Validacion de negocio (ej.: productos sin asociar al catalogo): aviso accionable, no error.
          this.$message({ message: msg, type: 'warning', duration: 7000, showClose: true })
        } else {
          this.$message.error(msg)
        }
      }
      this.converting = false
    },

    parseNumericFields(pq) {
      // SCTR de la cotización (COM-02 R3): usa el % propio de la cotización si
      // existe; si viene null, precarga el default global del config del cotizador.
      if (pq) {
        var sctrCot = (pq.sctr_porcentaje !== null && pq.sctr_porcentaje !== undefined && pq.sctr_porcentaje !== '')
          ? parseFloat(pq.sctr_porcentaje)
          : parseFloat(pq.sctr_config_default)
        if (!isNaN(sctrCot)) this.sctrPct = sctrCot
      }
      if (pq && pq.items) {
        for (var i = 0; i < pq.items.length; i++) {
          pq.items[i].quantity = parseInt(pq.items[i].quantity) || 0
          pq.items[i].costo_base = parseFloat(pq.items[i].costo_base) || 0
          pq.items[i].margen_porcentaje = parseFloat(pq.items[i].margen_porcentaje) || 0
          // Componentes de costo (Fase A) — asegurar que sean números para el-input-number
          pq.items[i].costo_muestra = parseFloat(pq.items[i].costo_muestra) || 0
          pq.items[i].numero_muestras = parseInt(pq.items[i].numero_muestras) || 0
          this.normalizarPrecioMuestra(pq.items[i])
          pq.items[i].costo_movilidad = parseFloat(pq.items[i].costo_movilidad) || 0
          pq.items[i].costo_impresion = parseFloat(pq.items[i].costo_impresion) || 0
          pq.items[i].costo_empaque = parseFloat(pq.items[i].costo_empaque) || 0
          // COM-02 #2: factor "Cantidad por millar" (default 1). ≤0 o inválido → 1 (neutro, igual que el motor).
          pq.items[i].cantidad_millar = parseFloat(pq.items[i].cantidad_millar)
          if (isNaN(pq.items[i].cantidad_millar) || pq.items[i].cantidad_millar <= 0) pq.items[i].cantidad_millar = 1
          pq.items[i].impresion_por_unidad = !!pq.items[i].impresion_por_unidad
          pq.items[i].empaque_por_unidad = !!pq.items[i].empaque_por_unidad
          // Agosto 2026 (punto 2): llega como 0/1 desde la BD; el checkbox necesita booleano.
          pq.items[i].excluir_catalogo = !!pq.items[i].excluir_catalogo
          // Tipo de línea (combo sin nombre): filas viejas vienen null → product.
          var tl = String(pq.items[i].tipo_linea || 'product').toLowerCase()
          pq.items[i].tipo_linea = (tl === 'service' || tl === 'comision') ? tl : 'product'
          // SCTR unitario (override por ítem): null = hereda; efectivo = el que aplicó el backend.
          pq.items[i].sctr_porcentaje = this.parseSctrItem(pq.items[i].sctr_porcentaje)
          pq.items[i].sctr_porcentaje_efectivo = this.parseSctrEfectivo(pq.items[i].sctr_porcentaje_efectivo)
          // Fase B — margen mínimo aplicable (campo virtual del backend).
          // Null = no hay regla configurada con mínimo para este ítem.
          var minMargin = pq.items[i].min_margin_aplicable
          if (minMargin === null || minMargin === undefined || minMargin === '') {
            pq.items[i].min_margin_aplicable = null
          } else {
            pq.items[i].min_margin_aplicable = parseFloat(minMargin)
            if (isNaN(pq.items[i].min_margin_aplicable)) {
              pq.items[i].min_margin_aplicable = null
            }
          }
          // Fase C — escalones de precio por volumen (campos virtuales del backend).
          if (!Array.isArray(pq.items[i].precios_volumen_disponibles)) {
            pq.items[i].precios_volumen_disponibles = []
          } else {
            // Asegurar que quantity_unit y price1 sean números (no strings de decimal:2).
            pq.items[i].precios_volumen_disponibles = pq.items[i].precios_volumen_disponibles.map(function (t) {
              return Object.assign({}, t, {
                quantity_unit: parseFloat(t.quantity_unit) || 0,
                price1: parseFloat(t.price1) || 0,
              })
            })
          }
          var tier = pq.items[i].tier_aplicado
          if (tier && typeof tier === 'object') {
            pq.items[i].tier_aplicado = Object.assign({}, tier, {
              quantity_unit: parseFloat(tier.quantity_unit) || 0,
              price1: parseFloat(tier.price1) || 0,
            })
          } else {
            pq.items[i].tier_aplicado = null
          }
          // Parsear product_suggestions (puede venir como string JSON)
          var sugg = pq.items[i].product_suggestions
          if (typeof sugg === 'string' && sugg) {
            try { pq.items[i].product_suggestions = JSON.parse(sugg) } catch (e) {}
          }
          // Defensa: si el backend aún no tiene el cast 'array' (deploy parcial),
          // datos_inferidos/datos_explicitos pueden venir como string JSON.
          var dInf = pq.items[i].datos_inferidos
          if (typeof dInf === 'string' && dInf) {
            try { pq.items[i].datos_inferidos = JSON.parse(dInf) } catch (e) { pq.items[i].datos_inferidos = [] }
          }
          if (!Array.isArray(pq.items[i].datos_inferidos)) pq.items[i].datos_inferidos = []
          var dExp = pq.items[i].datos_explicitos
          if (typeof dExp === 'string' && dExp) {
            try { pq.items[i].datos_explicitos = JSON.parse(dExp) } catch (e) { pq.items[i].datos_explicitos = [] }
          }
          if (!Array.isArray(pq.items[i].datos_explicitos)) pq.items[i].datos_explicitos = []
        }
      }
      return pq
    },

    /**
     * Fase B — ¿el margen ingresado en este ítem está por debajo del mínimo
     * configurado en quotation_margin_rules?
     *
     * Retorna false si:
     *  - El ítem no tiene min_margin_aplicable (no hay regla con mínimo).
     *  - El margen es mayor o igual al mínimo.
     *
     * Solo dispara alerta visual; NO bloquea el guardado (decisión de Fase B
     * para mantener flexibilidad operativa de la ejecutiva).
     */
    isUnderMinMargin(item) {
      var min = item.min_margin_aplicable
      if (min === null || min === undefined) return false
      var actual = parseFloat(item.margen_porcentaje)
      if (isNaN(actual)) return false
      return actual < parseFloat(min)
    },

    minMarginTooltip(item) {
      var min = item.min_margin_aplicable
      if (min === null || min === undefined) return ''
      return 'El margen ingresado está por debajo del mínimo configurado (' + min +
        '%). Se guardará igual, pero revisa con tu líder si necesitas aprobación.'
    },

    /**
     * Fase C — Formato de cantidad del escalón sin decimales innecesarios.
     * item_unit_types.quantity_unit es decimal(12,4), pero en la práctica
     * COMPIPRO usa enteros (50, 100, 500). Mostramos sin decimales si es entero.
     */
    formatTierQty(qty) {
      var n = parseFloat(qty)
      if (isNaN(n)) return '0'
      return n % 1 === 0 ? String(n) : n.toFixed(2).replace(/\.?0+$/, '')
    },

    /**
     * Fase C — Aplicar clase CSS a la fila del escalón aplicado en la tabla
     * de escalones disponibles (para resaltarlo visualmente).
     */
    tierRowClass(tier, item) {
      if (!item.tier_aplicado) return ''
      return item.tier_aplicado.id === tier.id ? 'tier-row-applied' : ''
    },

    estadoTagType(estado) {
      var map = {
        'borrador': 'info', 'en_revision': 'warning', 'ajustada': 'warning',
        'validada': 'primary', 'aprobada': 'success', 'rechazada': 'danger',
      }
      return map[estado] || 'info'
    },

    /**
     * Preview en vivo del costeo de un ítem. Replica la cadena del
     * QuotationCalculationEngine (backend) para que la ejecutiva vea el
     * efecto al instante. El valor definitivo lo recalcula el backend al
     * guardar (fuente de verdad). Usa los % default (igvPct, sctrPct).
     *
     * Cadena:
     *   T   = costo_base × (1 + markup%)
     *   TP  = Q×T + muestra + movilidad
     *   impr = costo × colores × lados × millar (fijo) ; emp = ×Q si "por unidad", sino fijo
     *   SCTR = sctr% × (TP + impr + emp)
     *   Total = TP + impr + emp + SCTR ; unit = Total / Q
     */
    /**
     * Agosto 2026 (punto 4) — Precio unitario efectivo de la muestra.
     *
     * El propio del ítem si lo tiene; si no, el costo base, que es como se
     * calculó siempre. Se compara contra null/'' y NO con un simple `||`: un
     * precio de 0 significa "muestras gratis" y tiene que respetarse, mientras
     * que `0 || costoBase` devolvería el costo base y lo perdería.
     *
     * Espejo de la misma regla en QuotationCalculationEngine::recalculateItem
     * y en costeoReal.js.
     */
    /**
     * Agosto 2026 (punto 4) — Deja el ítem listo para la pantalla: normaliza
     * `precio_muestra` y deriva de él el estado del checkbox.
     *
     * El checkbox NO es una columna: es la presencia del precio propio. Por eso
     * hay que recalcularlo cada vez que el ítem se reemplaza por uno que viene
     * del backend, que no conoce esa bandera de pantalla.
     *
     * Vive en un método propio, y no repetido en cada lugar, porque
     * justamente ahí estuvo el error: `saveItem` reemplaza el objeto del ítem con
     * el que devuelve el servidor, y como no pasaba por esta normalización, la
     * bandera quedaba en undefined y el checkbox se desmarcaba solo al guardar.
     * Peor todavía, el guardado siguiente mandaba `precio_muestra: null` y el
     * costo volvía al costo base sin que nadie lo pidiera.
     */
    normalizarPrecioMuestra(item) {
      if (!item) return
      var pm = item.precio_muestra
      var tienePrecioPropio = pm !== null && pm !== undefined && pm !== ''
      item.precio_muestra = tienePrecioPropio ? (parseFloat(pm) || 0) : null
      this.$set(item, '_precio_muestra_distinto', tienePrecioPropio)
    },

    precioMuestraEfectivo(item, costoBaseAlterno) {
      if (!item) return 0
      var propio = item.precio_muestra
      if (propio !== null && propio !== undefined && propio !== '') {
        var n = parseFloat(propio)
        if (!isNaN(n)) return n
      }
      // Sin precio propio, la muestra se cotiza al costo base.
      if (costoBaseAlterno !== undefined && costoBaseAlterno !== null) {
        return parseFloat(costoBaseAlterno) || 0
      }
      return this.selectedQuoteCostValue(item)
    },

    // Costo de muestra derivado = N° de muestras × precio de la muestra (preview
    // en vivo; el backend lo recalcula y persiste igual al guardar).
    costoMuestraDerivado(item) {
      if (!item) return 0
      return (parseInt(item.numero_muestras) || 0) * this.precioMuestraEfectivo(item)
    },

    /**
     * Agosto 2026 (punto 4) — El checkbox no es una columna: es la PRESENCIA del
     * precio propio. Al desmarcarlo se vuelve a null y el cálculo cae al costo
     * base; al marcarlo arranca en el costo base, que es el valor que la
     * ejecutiva ve hoy, para que el número no salte solo al marcar.
     */
    onPrecioMuestraToggle(item) {
      if (item._precio_muestra_distinto) {
        if (item.precio_muestra === null || item.precio_muestra === undefined || item.precio_muestra === '') {
          this.$set(item, 'precio_muestra', this.selectedQuoteCostValue(item))
        }
      } else {
        this.$set(item, 'precio_muestra', null)
      }
    },
    // ── SCTR unitario (override por ítem, COM-02) ──
    parseSctrItem(v) {
      if (v === null || v === undefined || v === '') return null
      var n = parseFloat(v)
      return isNaN(n) ? null : n
    },
    parseSctrEfectivo(v) {
      var n = parseFloat(v)
      return isNaN(n) ? null : n
    },
    // % de SCTR que realmente aplica al ítem: su override si lo tiene; si no, el de la cotización.
    sctrEfectivo(item) {
      var own = this.parseSctrItem(item ? item.sctr_porcentaje : null)
      return own !== null ? own : this.sctrPct
    },
    tieneOverrideSctr(item) {
      return this.parseSctrItem(item ? item.sctr_porcentaje : null) !== null
    },
    // Vuelve el ítem a heredar el % de la cotización (borra su override).
    heredarSctr(item) {
      item.sctr_porcentaje = null
      this.saveItem(item)
    },
    // Normaliza lo escrito en la celda de SCTR del ítem y persiste:
    // vacío → null (hereda); número → clamp 0..10.
    onSctrItemChange(item) {
      var v = item.sctr_porcentaje
      if (v === null || v === undefined || (typeof v === 'string' && v.trim() === '')) {
        item.sctr_porcentaje = null
      } else {
        var n = parseFloat(v)
        if (isNaN(n)) {
          item.sctr_porcentaje = null
        } else {
          if (n < 0) n = 0
          if (n > 10) n = 10
          item.sctr_porcentaje = n
        }
      }
      this.saveItem(item)
    },
    costeoBreakdown(item, costoBaseAlterno) {
      if (!item) return null
      var costoBaseUsado = (costoBaseAlterno !== undefined && costoBaseAlterno !== null)
        ? (parseFloat(costoBaseAlterno) || 0)
        : this.selectedQuoteCostValue(item)
      // Comisión = costo fijo: el costo base es el total directo, sin margen,
      // SCTR, IGV ni adicionales (espejo del motor backend). El encabezado
      // (resumenCostoReal) suma este mismo objeto, así que cierra solo.
      if (String((item && item.tipo_linea) || '').toLowerCase() === 'comision') {
        var cbc = Math.round(costoBaseUsado * 100) / 100
        return {
          productoUnit: cbc, productoTotal: cbc, muestra: 0, movilidad: 0,
          impresion: 0, imprColores: 1, imprLados: 1, imprMillar: 1,
          empaque: 0, sctr: 0, sctrPctEfectivo: 0, ganancia: 0,
          totalSinIgv: cbc, unit: cbc,
          resultadoMargenUnit: 0, totalProducto: cbc,
          baseCostoReal: cbc, sctrCostoReal: 0, costoRealTotal: cbc,
          utilidadReal: 0, margenRealPct: 0,
          precioNecesario: cbc, cuantoSubir: 0, precioUnitObjetivo: cbc,
          margenObjetivoPct: MARGEN_REAL_OBJETIVO_PCT, margenRequeridoPct: null,
          totalConIgv: cbc,
        }
      }
      // Servicio: el motor no tiene caso especial para 'service' (solo para
      // 'comision'), así que se costea como un producto (con SCTR e IGV).
      var q = parseInt(item.quantity) || 1
      if (q < 1) q = 1
      var costoBase = costoBaseUsado
      var markup    = parseFloat(item.margen_porcentaje) || 0
      // Agosto 2026 (punto 4): N° muestras × precio de la muestra. Cae al costo
      // base cuando el ítem no tiene un precio propio (comportamiento histórico).
      var muestra   = (parseInt(item.numero_muestras) || 0) * this.precioMuestraEfectivo(item, costoBase)
      var movilidad = parseFloat(item.costo_movilidad) || 0
      var impresion = parseFloat(item.costo_impresion) || 0
      var empaque   = parseFloat(item.costo_empaque) || 0
      // COM-02 #2: factores de la impresión. Caen a 1 si vienen vacíos o ≤ 0 (igual que el motor de Marketing).
      var colores = parseInt(item.cantidad_colores);   if (isNaN(colores) || colores <= 0) colores = 1
      var lados   = parseInt(item.cantidad_lados);     if (isNaN(lados)   || lados   <= 0) lados   = 1
      var millar  = parseFloat(item.cantidad_millar);  if (isNaN(millar)  || millar  <= 0) millar  = 1

      var T = costoBase * (1 + markup / 100)
      var productoTotal = q * T
      var TP = productoTotal + muestra + movilidad
      // Costo Total de Impresión = costo × colores × lados × millar (costo FIJO del pedido; ya NO escala por cantidad).
      // Agosto 2026 (punto 3): la impresión puede ser fija por pedido o por unidad,
      // igual que el empaque. Misma fórmula que costeoReal.js y que el motor del backend.
      var imprBase = impresion * colores * lados * millar
      var imprEf = item.impresion_por_unidad ? (imprBase * q) : imprBase
      var empEf  = item.empaque_por_unidad ? (empaque * q) : empaque
      // SCTR efectivo del ítem: su override si lo tiene, si no el % de la cotización.
      var sctrPctItem = this.sctrEfectivo(item)
      var sctr = (sctrPctItem / 100) * (TP + imprEf + empEf)
      // Agosto 2026 (punto 6): el unitario se redondea a terminación 0, 5 o 9
      // hacia arriba y el total se recompone desde él, igual que
      // QuotationCalculationEngine::computeItem. Tiene que ser idéntico o la
      // vista previa mostraría un número y el guardado dejaría otro.
      var unit = redondeoComercial((TP + imprEf + empEf + sctr) / q)
      var totalSinIgv = unit * q

      // ── COM-02 · COSTO REAL (solo display; NO altera nada de la cadena de venta de arriba) ──
      // La diferencia con la cadena de venta es que el producto entra a COSTO BASE,
      // sin el margen: así se ve lo que de verdad cuesta el pedido y, contra el
      // Total sin IGV, cuánto se gana en serio.
      //
      // Dos desvíos deliberados respecto del mockup, acordados con JC:
      //   · impresión EFECTIVA (imprEf, con el clamp 0→1 de colores/lados/millar, igual que el motor)
      //   · empaque EFECTIVO  (empEf, × cantidad cuando es "por unidad")
      // Con los valores planos, esa diferencia contra el Total sin IGV aparecería
      // como utilidad que en realidad no existe.
      var baseCostoReal  = (costoBase * q) + muestra + movilidad + imprEf + empEf
      var sctrCostoReal  = (sctrPctItem / 100) * baseCostoReal
      var costoRealTotal = baseCostoReal + sctrCostoReal
      var utilidadReal   = totalSinIgv - costoRealTotal
      var margenRealPct  = totalSinIgv > 0 ? (utilidadReal / totalSinIgv) * 100 : 0
      // Precio al que habría que vender para que la utilidad real sea el X% del precio.
      var precioNecesario    = costoRealTotal * 100 / (100 - MARGEN_REAL_OBJETIVO_PCT)
      var cuantoSubir        = precioNecesario - totalSinIgv   // negativo = ya se vende por encima del objetivo
      var precioUnitObjetivo = precioNecesario / q             // q ya viene clampeado a >= 1

      // ── Agosto 2026 (punto 7) · Margen requerido para llegar al objetivo real ──
      //
      // El markup se aplica SOLO al producto; muestras, movilidad, impresión y
      // empaque entran al precio sin margen. Por eso el margen que hay que cargar
      // sobre el producto es mayor que el margen real que se obtiene al final.
      //
      // Llamando C al costo del producto (q × costo base) y K al resto de costos:
      //
      //     venta = (1+s)·[ C·(1+m) + K ]        costo real = (1+s)·( C + K )
      //
      // Igualando margen real = g  (venta = costo real / (1−g)), el factor (1+s)
      // del SCTR se cancela en los dos lados y queda:
      //
      //     m = (C + K) · g/(1−g) / C
      //
      // Comprobado con el ejemplo del cliente: C=100, K=20, g=0.40
      //     m = 120 × (0.40/0.60) / 100 = 0.80 → 80%
      //     y con 80%: precio 180+20 = 200, costo 120, utilidad 80 → 80/200 = 40% ✔
      //
      // Queda en null cuando el costo base es 0: ahí el markup multiplica por cero
      // y no hay porcentaje que alcance el objetivo, así que no se ofrece el botón.
      var costoProducto   = q * costoBase                        // C
      var costosSinMarkup = muestra + movilidad + imprEf + empEf  // K
      var g = MARGEN_REAL_OBJETIVO_PCT / 100
      var margenRequeridoPct = null
      if (costoProducto > 0) {
        margenRequeridoPct = ((costoProducto + costosSinMarkup) * (g / (1 - g)) / costoProducto) * 100
        // El campo "Margen %" es un el-input-number con :precision="1", o sea que
        // solo admite un decimal. Hay que entregarle un valor que pueda contener:
        // si se le pasa 66.67 lo muestra como 66.7 y deja el modelo en 66.7,
        // mientras que al backend ya se fue el 66.67 — y la pantalla termina
        // calculando con un número distinto del que quedó guardado.
        //
        // Se redondea HACIA ARRIBA a un decimal, no al más cercano: hacia abajo
        // el margen real quedaría apenas por debajo del objetivo, que es
        // justamente lo que este botón existe para evitar.
        margenRequeridoPct = Math.ceil(Number((margenRequeridoPct * 10).toFixed(6))) / 10
        // La columna margen_porcentaje es decimal(5,2): no admite más de 999.99.
        if (margenRequeridoPct > 999.9) margenRequeridoPct = 999.9
        if (margenRequeridoPct < 0) margenRequeridoPct = 0
      }

      var r2 = function (n) { return Math.round(n * 100) / 100 }
      var igvTotal = r2(totalSinIgv * (this.igvPct / 100))
      var totalConIgv = r2(totalSinIgv + igvTotal)
      return {
        productoUnit:  r2(T),
        productoTotal: r2(productoTotal),
        muestra:       r2(muestra),
        movilidad:     r2(movilidad),
        impresion:     r2(imprEf),
        imprColores:   colores,
        imprLados:     lados,
        imprMillar:    millar,
        empaque:       r2(empEf),
        sctr:          r2(sctr),
        sctrPctEfectivo: sctrPctItem,
        // Ganancia del markup (igual que ganancia_absoluta del backend): Q × costo × margen%.
        // Es informativa: ya está embebida en "Producto", no es un sumando aparte.
        ganancia:      r2(q * costoBase * (markup / 100)),
        totalSinIgv:   r2(totalSinIgv),
        unit:          r2(unit),
        // Líneas nuevas de la cadena de venta en la tarjeta.
        resultadoMargenUnit: r2(costoBase * (markup / 100)),
        totalProducto:       r2(TP),
        // ── Bloque de Costo Real (solo lectura) ──
        baseCostoReal:      r2(baseCostoReal),
        sctrCostoReal:      r2(sctrCostoReal),
        costoRealTotal:     r2(costoRealTotal),
        utilidadReal:       r2(utilidadReal),
        margenRealPct:      r2(margenRealPct),
        precioNecesario:    r2(precioNecesario),
        cuantoSubir:        r2(cuantoSubir),
        precioUnitObjetivo: r2(precioUnitObjetivo),
        margenObjetivoPct:  MARGEN_REAL_OBJETIVO_PCT,
        totalConIgv: totalConIgv,
        // Agosto 2026 (punto 7). null = costo base en 0, no hay margen posible.
        margenRequeridoPct: margenRequeridoPct === null ? null : r2(margenRequeridoPct),
      }
    },

    /**
     * Agosto 2026 (punto 7) — Botón "Aplicar margen".
     *
     * Coloca en Margen % el porcentaje que hace que el margen REAL del ítem
     * llegue al objetivo, y guarda. El cálculo vive en costeoBreakdown; acá solo
     * se aplica, para que la pantalla y el botón no puedan discrepar.
     *
     * Si el ítem ya rinde por encima del objetivo, aplicar BAJARÍA el precio. Eso
     * no se hace en silencio: se pide confirmación, porque bajar sin querer un
     * precio ya trabajado con el cliente es caro de revertir.
     */
    aplicarMargenObjetivo(item) {
      var bd = this.costeoBreakdown(item)
      if (!bd || bd.margenRequeridoPct === null) {
        this.$message.warning('Carga primero el Costo Base del producto.')
        return
      }

      var actual = parseFloat(item.margen_porcentaje) || 0
      var nuevo  = bd.margenRequeridoPct
      var self   = this

      var aplicar = function () {
        item.margen_porcentaje = nuevo
        self.saveItem(item)
      }

      if (nuevo < actual) {
        this.$confirm(
          'Este producto ya rinde ' + bd.margenRealPct.toFixed(2) + '% real, por encima del objetivo de ' +
          bd.margenObjetivoPct + '%. Aplicar ' + nuevo.toFixed(2) + '% BAJARÍA el precio de venta. ¿Continuar?',
          'Aplicar margen',
          { confirmButtonText: 'Aplicar igual', cancelButtonText: 'Cancelar', type: 'warning' }
        ).then(aplicar).catch(function () { /* cancelado por la ejecutiva */ })
        return
      }

      aplicar()
    },

    /*
    |------------------------------------------------------------------
    | Badge "Inferido por IA" (datos_inferidos[])
    |------------------------------------------------------------------
    | El backend devuelve un array `datos_inferidos` por ítem con los
    | nombres de los campos que la IA infirió (ej: ['material', 'medida',
    | 'tecnica_impresion']).  La UI usa estos helpers para mostrar el
    | badge de varita mágica en cada campo correspondiente.
    */

    /**
     * ¿El campo `fieldName` de este ítem está marcado como inferido por la IA?
     */
    isInferred(item, fieldName) {
      if (!item || !Array.isArray(item.datos_inferidos)) return false
      return item.datos_inferidos.indexOf(fieldName) !== -1
    },

    /**
     * Lista de campos inferidos por la IA en este ítem.
     * @return {string[]}
     */
    inferredFields(item) {
      if (!item || !Array.isArray(item.datos_inferidos)) return []
      return item.datos_inferidos
    },

    /**
     * Cuando la ejecutiva edita un campo que estaba marcado como inferido,
     * lo removemos del array `datos_inferidos` localmente para que el badge
     * desaparezca al instante.  El backend persiste la edición al guardar
     * (y marca is_inferred=false en el ítem entero).
     */
    onFieldEdited(item, fieldName) {
      if (!item || !Array.isArray(item.datos_inferidos)) return
      var idx = item.datos_inferidos.indexOf(fieldName)
      if (idx !== -1) {
        // Hacer reactivo el cambio para que Vue re-renderice el badge
        var copy = item.datos_inferidos.slice()
        copy.splice(idx, 1)
        this.$set(item, 'datos_inferidos', copy)
      }
    },

    /*
    |------------------------------------------------------------------
    | Product matching UI
    |------------------------------------------------------------------
    */

    matchStatus(item) {
      if (item.needs_product_review) return 'review'
      if (item.item_id && parseFloat(item.product_match_confidence) >= 0.85) return 'matched'
      if (item.item_id) return 'matched'  // manual match (confidence=1)
      return 'no-match'
    },

    scoreTagType(score) {
      var s = parseFloat(score) || 0
      if (s >= 0.85) return 'success'
      if (s >= 0.60) return 'warning'
      if (s >= 0.40) return 'info'
      return 'danger'
    },

    truncate(s, n) {
      s = String(s || '')
      return s.length > n ? s.substring(0, n) + '…' : s
    },

    openMatchDialog(item) {
      // Comisión no usa match manual: ya está enlazada al ítem 271.
      if (item && String(item.tipo_linea || '').toLowerCase() === 'comision') {
        this.$message.info('La comisión ya está enlazada al ítem COMISIÓN del catálogo.')
        return
      }
      this.matchTarget = item
      this.matchSuggestions = Array.isArray(item.product_suggestions) ? item.product_suggestions : []
      // Búsqueda manual arranca con el nombre solicitado (como la referencia)
      this.manualQuery = item.product_name || ''
      this.manualResults = []
      this.actingSuggestionId = null
      this.matchDialogVisible = true
      this.fetchManualResults()
    },

    onMatchDialogClose() {
      this.matchTarget = null
      this.matchSuggestions = []
      this.manualQuery = ''
      this.manualResults = []
      this.manualSearching = false
    },

    /**
     * Aplicar match manual (item_id|null) al item actual.
     *
     * Si el backend trajo automáticamente el costo del catálogo (cuando el
     * costo_base del ítem estaba en 0), también devuelve la prequote con
     * los totales recalculados — la usamos para refrescar la UI sin tener
     * que hacer un loadPrequote() extra.
     */
    async applyMatch(catalogItemId) {
      if (!this.matchTarget) return
      // El nombre solicitado pudo editarse en la tabla sin guardar. El backend
      // no lo modifica, pero su respuesta trae el nombre viejo y updateLocalItem
      // lo pisaría (quedaba "tomatodo" con el match de "usb"). Se respalda.
      var nombreSolicitado = this.matchTarget.product_name
      this.actingSuggestionId = catalogItemId === null ? 'none' : catalogItemId
      try {
        var resp = await this.$http.post(
          '/marketing/quotation/prequote-items/' + this.matchTarget.id + '/match',
          { item_id: catalogItemId }
        )
        if (resp.data && resp.data.ok) {
          this.$message.success(resp.data.message || 'Producto asociado')
          this.updateLocalItem(resp.data.item)

          // Si el backend recalculó totales (porque trajo el costo desde el
          // catálogo), refrescar la prequote local con los nuevos valores.
          if (resp.data.prequote) {
            this.prequote = this.parseNumericFields(resp.data.prequote)
          }

          // Restaurar lo tipeado SIN autoguardar: queda pendiente hasta el ✓ de
          // la fila (no pisar lo tipeado, pero tampoco quitarle propósito).
          if (resp.data.item && resp.data.item.id
              && String(nombreSolicitado || '') !== String(resp.data.item.product_name || '')) {
            this.restoreNombreSolicitado(resp.data.item.id, nombreSolicitado)
          }

          this.matchDialogVisible = false

          // Al cambiar el producto del catálogo, la imagen debe reflejar el nuevo
          // producto. El backend ya sirve la imagen del catálogo por fallback, PERO si
          // el ítem tenía una imagen propia subida, esa la tapa. Se borra la propia para
          // que herede la imagen del nuevo catálogo (o quede sin imagen si no tiene).
          this.itemImageVersion++
          if (resp.data.item && resp.data.item.product_reference_image) {
            try {
              await this.$http.delete('/marketing/quotation/prequote-items/' + resp.data.item.id + '/reference-image')
              this.itemImageVersion++
              await this.loadPrequote()
            } catch (e) { /* el match ya quedó aplicado; no se bloquea si el borrado de la imagen falla */ }
          }
        } else {
          this.$message.error((resp.data && resp.data.message) || 'No se pudo asociar')
        }
      } catch (e) {
        var msg = e.response && e.response.data && e.response.data.message ? e.response.data.message : 'Error de red'
        this.$message.error(msg)
      }
      this.actingSuggestionId = null
    },

    /**
     * El nombre solicitado puede editarse sin guardar; las respuestas del
     * backend traen el nombre viejo. Restaura lo tipeado sobre la fila
     * ya reemplazada y la devuelve (o null si no la encuentra).
     */
    restoreNombreSolicitado(itemId, nombre) {
      if (!this.prequote || !Array.isArray(this.prequote.items)) return null
      for (var i = 0; i < this.prequote.items.length; i++) {
        if (parseInt(this.prequote.items[i].id) === parseInt(itemId)) {
          this.prequote.items[i].product_name = nombre
          return this.prequote.items[i]
        }
      }
      return null
    },

    /**
     * Re-ejecutar matching automático (después de editar product_name).
     */
    async rematchWithAI() {
      if (!this.matchTarget) return
      var nombreSolicitado = this.matchTarget.product_name
      this.rematchLoading = true
      try {
        var resp = await this.$http.post(
          '/marketing/quotation/prequote-items/' + this.matchTarget.id + '/rematch'
        )
        if (resp.data && resp.data.ok) {
          this.$message.success('Matching re-ejecutado')
          this.updateLocalItem(resp.data.item)
          // Igual que en applyMatch: no pisar lo tipeado sin guardar.
          if (resp.data.item && resp.data.item.id
              && String(nombreSolicitado || '') !== String(resp.data.item.product_name || '')) {
            this.restoreNombreSolicitado(resp.data.item.id, nombreSolicitado)
          }
          // Refrescar el dialog con las nuevas sugerencias
          var sugg = resp.data.result && resp.data.result.suggestions ? resp.data.result.suggestions : []
          this.matchSuggestions = sugg
          this.matchTarget.product_suggestions = sugg
        } else {
          this.$message.error('No se pudo re-ejecutar')
        }
      } catch (e) {
        this.$message.error('Error al re-buscar')
      }
      this.rematchLoading = false
    },

    /**
     * Búsqueda manual en el catálogo (mismo endpoint; ahora llena las
     * tarjetas en vez del autocomplete). Con debounce y anti-carrera.
     */
    fetchManualResults() {
      var self = this
      if (this.manualSearchTimer) clearTimeout(this.manualSearchTimer)
      var query = (this.manualQuery || '').trim()
      if (query.length < 2) { this.manualResults = []; this.manualSearching = false; return }
      this.manualSearching = true
      this.manualSearchTimer = setTimeout(function () {
        var token = ++self.manualSearchSeq
        self.$http.get('/marketing/quotation/products/search', {
          params: { q: query, limit: 15 }
        }).then(function (resp) {
          if (token !== self.manualSearchSeq) return // llegó algo más nuevo
          self.manualResults = resp.data && resp.data.results ? resp.data.results : []
          self.manualSearching = false
        }).catch(function () {
          if (token !== self.manualSearchSeq) return
          self.manualResults = []
          self.manualSearching = false
        })
      }, 350)
    },

    // Iniciales para el badge de la tarjeta (código o nombre).
    catalogInitials(r) {
      var code = String((r && (r.item_code || r.internal_id)) || '').replace(/[^A-Za-z0-9]/g, '').toUpperCase()
      if (code) return code.substring(0, 4)
      var words = String((r && r.name) || '').trim().split(/\s+/).filter(Boolean)
      var ini = words.slice(0, 2).map(function (w) { return w.charAt(0) }).join('').toUpperCase()
      return ini || '—'
    },

    onManualSelect(item) {
      if (item && item.item_id) {
        this.applyMatch(item.item_id)
      }
    },

    /*
    |------------------------------------------------------------------
    | S2 — Imagen de referencia (multimodal)
    |------------------------------------------------------------------
    */

    triggerImageUpload() {
      if (this.$refs.imageInput) this.$refs.imageInput.click()
    },

    async onImageSelected(event) {
      var files = event.target.files
      if (!files || files.length === 0) return
      var file = files[0]

      // Validación cliente espejo del backend
      var allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
      if (allowed.indexOf(file.type) === -1) {
        this.$message.error('Tipo de imagen no permitido. Solo JPG, PNG, WEBP o GIF.')
        return
      }
      if (file.size > 5 * 1024 * 1024) {
        this.$message.error('La imagen excede 5 MB.')
        return
      }

      this.uploadingImage = true
      try {
        var fd = new FormData()
        fd.append('image', file)
        var resp = await this.$http.post(
          '/marketing/quotation/prequotes/' + this.prequote.id + '/reference-image',
          fd,
          { headers: { 'Content-Type': 'multipart/form-data' } }
        )
        if (resp.data && resp.data.ok) {
          this.$message.success('Imagen guardada')
          // Actualizar el path en la prequote local + bust cache
          this.prequote.reference_image_path = resp.data.image.path
          this.prequote.reference_image_mime = resp.data.image.mime
          this.imageVersion++
        } else {
          this.$message.error((resp.data && resp.data.message) || 'No se pudo subir la imagen')
        }
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error subiendo imagen'
        this.$message.error(msg)
      } finally {
        this.uploadingImage = false
        // Limpiar el input para permitir re-seleccionar el mismo archivo si quiere
        if (this.$refs.imageInput) this.$refs.imageInput.value = ''
      }
    },

    async deleteReferenceImage() {
      try {
        await this.$confirm('¿Quitar la imagen del requerimiento?', 'Confirmar',
          { confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar', type: 'warning' })
      } catch (e) { return }

      this.deletingImage = true
      try {
        var resp = await this.$http.delete('/marketing/quotation/prequotes/' + this.prequote.id + '/reference-image')
        if (resp.data && resp.data.ok) {
          this.$message.success('Imagen eliminada')
          this.prequote.reference_image_path = null
          this.prequote.reference_image_mime = null
        }
      } catch (e) {
        this.$message.error('Error eliminando imagen')
      } finally {
        this.deletingImage = false
      }
    },

    async reinterpretWithImage() {
      try {
        await this.$confirm(
          'Esta acción borrará los items actuales y los volverá a generar usando la imagen como referencia. ¿Continuar?',
          'Re-interpretar con imagen',
          { confirmButtonText: 'Continuar', cancelButtonText: 'Cancelar', type: 'warning' }
        )
      } catch (e) { return }

      this.reinterpretingImage = true
      try {
        var resp = await this.$http.post(
          '/marketing/quotation/prequotes/' + this.prequote.id + '/reinterpret-with-image'
        )
        if (resp.data && resp.data.ok) {
          this.$message.success(resp.data.message || 'Re-interpretado')
          this.prequote = this.parseNumericFields(resp.data.prequote)
        } else {
          this.$message.error((resp.data && resp.data.message) || 'No se pudo re-interpretar')
        }
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error al re-interpretar'
        this.$message.error(msg)
      } finally {
        this.reinterpretingImage = false
      }
    },

    // ── COM-02 · "Referencia del producto" — agregar 1 ítem por IA (texto XOR imagen), sin borrar los existentes ──
    onReferenceTextInput() {
      var t = (this.referenceText || '').trim()
      this.referenceMode = t.length > 0 ? 'texto' : null
    },
    triggerReferenceImageUpload() {
      if (this.referenceMode === 'texto') {
        this.$message.warning('Ya ingresaste una referencia en texto. Para usar imagen, primero limpiá el texto.')
        return
      }
      if (this.$refs.referenceImageInput) this.$refs.referenceImageInput.click()
    },
    onReferenceImageSelected(event) {
      var files = event.target.files
      if (files && files.length > 0) this.setReferenceImage(files[0])
      if (this.$refs.referenceImageInput) this.$refs.referenceImageInput.value = ''
    },
    onReferenceDrop(event) {
      if (this.interpretingItem) return
      if (this.referenceMode === 'texto') {
        this.$message.warning('Ya ingresaste una referencia en texto. Para usar imagen, primero limpiá el texto.')
        return
      }
      var files = event.dataTransfer && event.dataTransfer.files
      if (files && files.length > 0) this.setReferenceImage(files[0])
    },
    setReferenceImage(file) {
      var allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
      if (allowed.indexOf(file.type) === -1) {
        this.$message.error('Tipo de imagen no permitido. Solo JPG, PNG, WEBP o GIF.')
        return
      }
      if (file.size > 5 * 1024 * 1024) {
        this.$message.error('La imagen excede 5 MB.')
        return
      }
      this.referenceImageFile = file
      this.referenceMode = 'imagen'
      var self = this
      var reader = new FileReader()
      reader.onload = function (e) { self.referenceImagePreview = e.target.result }
      reader.readAsDataURL(file)
    },
    removeReferenceImage() {
      this.referenceImageFile = null
      this.referenceImagePreview = ''
      this.referenceMode = null
    },
    clearReference() {
      this.referenceText = ''
      this.referenceImageFile = null
      this.referenceImagePreview = ''
      this.referenceMode = null
    },
    async interpretItem() {
      if (!this.referenceMode) {
        this.$message.warning('Escribí el producto o subí una imagen referencial para interpretar el ítem.')
        return
      }
      if (!this.refReqId) {
        this.$message.error('No se pudo determinar el requerimiento para interpretar el ítem.')
        return
      }
      this.interpretingItem = true
      try {
        var fd = new FormData()
        if (this.referenceMode === 'texto') {
          fd.append('text', (this.referenceText || '').trim())
        } else {
          fd.append('image', this.referenceImageFile)
        }
        // Si ya hay una prequote abierta, indicarla; si no, el backend crea una vacía (arranque en frío).
        if (this.prequote && this.prequote.id) fd.append('prequote_id', this.prequote.id)

        var resp = await this.$http.post(
          '/marketing/quotation/requerimientos/' + this.refReqId + '/prequote/items/interpret',
          fd,
          { headers: { 'Content-Type': 'multipart/form-data' } }
        )
        if (resp.data && resp.data.ok) {
          this.prequote = this.parseNumericFields(resp.data.prequote)
          this.clearReference()
          if (resp.data.warning) {
            this.$message({ message: resp.data.warning, type: 'warning', duration: 8000, showClose: true })
          } else {
            this.$message.success('Ítem interpretado y agregado. Los ítems actuales no se modificaron.')
          }
          this.$emit('precotizacion-added')
        } else {
          this.$message.error((resp.data && resp.data.message) || 'No se pudo interpretar la referencia.')
        }
      } catch (e) {
        var status = e.response && e.response.status ? e.response.status : ''
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error al interpretar la referencia.'
        this.$message({ message: (status ? '(' + status + ') ' : '') + msg, type: 'error', duration: 8000, showClose: true })
      } finally {
        this.interpretingItem = false
      }
    },
    /**
     * Ítem manual (alternativa sin IA a "Referencia del producto").
     * Crea 1 ítem vacío en el backend —que bootstrapea la prequote si no
     * existe— y refresca igual que interpretItem. La fila nace editable
     * (sin datos inferidos), así que saveItem/deleteItem/match le sirven
     * sin cambios.
     */
    async addManualItem() {
      if (!this.refReqId) {
        this.$message.error('No se pudo determinar el requerimiento para agregar el ítem.')
        return
      }
      this.addingManual = true
      try {
        var payload = {}
        // Si ya hay una prequote abierta, indicarla; si no, el backend crea una vacía (arranque en frío).
        if (this.prequote && this.prequote.id) payload.prequote_id = this.prequote.id
        var resp = await this.$http.post(
          '/marketing/quotation/requerimientos/' + this.refReqId + '/prequote/items/manual',
          payload
        )
        if (resp.data && resp.data.ok) {
          this.prequote = this.parseNumericFields(resp.data.prequote)
          this.$message.success('Ítem manual agregado. Complétalo y guárdalo.')
          this.$emit('precotizacion-added')
        } else {
          this.$message.error((resp.data && resp.data.message) || 'No se pudo agregar el ítem manual.')
        }
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error al agregar el ítem manual.'
        this.$message({ message: msg, type: 'error', duration: 8000, showClose: true })
      } finally {
        this.addingManual = false
      }
    },
    /* ──────────────────────────────────────────────────────────
     * Comparativa de proveedores por ítem (mockup db).
     * ────────────────────────────────────────────────────────── */
    quotesBaseUrl(item) {
      return '/marketing/quotation/prequotes/' + this.prequote.id + '/items/' + item.id + '/quotes'
    },
    supplierQuotesOf(item) {
      if (!item || !item.id) return []
      // [NUEVO FLUJO 2026-10-21] El BUFFER es la única fuente que se pinta.
      // Antes se pintaba el mapa y, al no estar "dirty", la edición se perdía.
      var buf = this.supplierQuoteBuffers[item.id]
      if (buf) return buf.quotes
      return this.supplierQuotesMap[item.id] || []
    },
    // [NUEVO FLUJO] Inicializa o actualiza el buffer local con la copia de los quotes.
    initSupplierQuoteBuffer(item) {
      if (!item || !item.id) return
      if (!this.supplierQuoteBuffers[item.id]) {
        var src = (this.supplierQuotesMap[item.id] || []).map(function (q) { return Object.assign({}, q) })
        // [NUEVO FLUJO 2026-10-21] Conserva la marca de la fila del ÍTEM.
        this.markItemQuoteRow(src, item)
        this.$set(this.supplierQuoteBuffers, item.id, {
          quotes: src,
          dirty: false,
          loaded: false
        })
      }
    },
    // [NUEVO FLUJO] Marca el buffer del ítem como "sucio" (pendiente de guardar).
    markSupplierQuoteDirty(item) {
      this.initSupplierQuoteBuffer(item)
      this.$set(this.supplierQuoteBuffers[item.id], 'dirty', true)
    },
    async loadSupplierQuotes(item, force) {
      var self = this
      if (!this.prequote || !item || !item.id) return
      if (!force && (this.supplierQuotesLoaded[item.id] || this.supplierQuotesLoading[item.id])) return
      this.$set(this.supplierQuotesLoading, item.id, true)
      try {
        var resp = await this.$http.get(this.quotesBaseUrl(item))
        if (resp.data && resp.data.ok) {
          var loaded = resp.data.quotes || []
          // [NUEVO FLUJO 2026-10-21] Marca la fila del ÍTEM (la principal).
          this.markItemQuoteRow(loaded, item)
          this.$set(this.supplierQuotesMap, item.id, loaded)
          this.$set(this.supplierQuotesLoaded, item.id, true)
          // [NUEVO FLUJO 2026-10-21] Copia de lo que hay en BD (para detectar borrados).
          // Va en un mapa por ítem: antes vivía en item._originalQuotes y se perdía
          // cuando la fila se re-renderizaba.
          this.$set(this.supplierQuoteOriginals, item.id, JSON.parse(JSON.stringify(loaded)))
          // [NUEVO FLUJO] Al cargar desde backend, inicializa/resetea el buffer si no estaba sucio
          var buf = this.supplierQuoteBuffers[item.id]
          if (!buf || !buf.dirty) {
            // [NUEVO FLUJO 2026-10-21] Copia con la marca de la fila del ÍTEM.
            var copy = loaded.map(function (q) { return Object.assign({}, q) })
            this.markItemQuoteRow(copy, item)
            this.$set(this.supplierQuoteBuffers, item.id, {
              quotes: copy,
              dirty: false,
              loaded: true
            })
          }
        }
      } catch (e) {
        // Silencioso: la sección queda vacía hasta el próximo intento.
      } finally {
        this.$set(this.supplierQuotesLoading, item.id, false)
      }
    },
    applyQuotesResponse(item, data) {
      if (data.quotes) {
        // [NUEVO FLUJO 2026-10-21] Marca la fila del ÍTEM (la principal).
        this.markItemQuoteRow(data.quotes, item)
        this.$set(this.supplierQuotesMap, item.id, data.quotes)
        this.$set(this.supplierQuotesLoaded, item.id, true)
        // [NUEVO FLUJO 2026-10-21] Copia de lo que hay en BD (para detectar borrados).
        this.$set(this.supplierQuoteOriginals, item.id, JSON.parse(JSON.stringify(data.quotes)))
        // [NUEVO FLUJO] Tras una respuesta del backend, resincroniza el buffer
        var copy2 = data.quotes.map(function (q) { return Object.assign({}, q) })
        this.markItemQuoteRow(copy2, item)
        this.$set(this.supplierQuoteBuffers, item.id, {
          quotes: copy2,
          dirty: false,
          loaded: true
        })
      }
      if (data.prequote) {
        this.prequote = this.parseNumericFields(data.prequote)
        this.$emit('precotizacion-added')
      }
    },
    // [NUEVO FLUJO] Cambia proveedor en el dropdown: limpia costo/tiempo/observaciones.
    // [NUEVO FLUJO 2026-10-21] Normaliza 0 -> null (el "Proveedor por definir" es
    // la opción 0 del select) y deja el nombre del proveedor resuelto.
    onQuoteSupplierChange(item, quote, supplierId) {
      var sid = supplierId ? Number(supplierId) : null
      this.saveSupplierQuote(item, quote, {
        supplier_id: sid,
        costo_base: 0,
        tiempo_estimado_dias: '',
        observaciones: ''
      })
    },
    // [NUEVO FLUJO 2026-10-21] Valor unitario del ÍTEM: usa el costo base del
    // proveedor SELECCIONADO (mismo criterio que el de la comparativa).
    // Unitario final de venta = Total sin IGV / Cantidad (con muestras,
    // movilidad, impresión, empaque y SCTR repartidos).
    itemUnitValue(item) {
      var bd = this.costeoBreakdown(item)
      if (!bd) return 'S/ ' + (parseFloat(item && item.valor_unitario) || 0).toFixed(2)
      return 'S/ ' + (parseFloat(bd.unit) || 0).toFixed(2)
    },
    // [NUEVO FLUJO 2026-10-21] Costo base (número) del proveedor SELECCIONADO.
    selectedQuoteCostValue(item) {
      var quotes = this.supplierQuotesOf(item) || []
      for (var i = 0; i < quotes.length; i++) {
        if (Number(quotes[i].seleccionado) === 1) return parseFloat(quotes[i].costo_base) || 0
      }
      return parseFloat(item && item.costo_base) || 0
    },
    // [NUEVO FLUJO 2026-10-21] Costo base del ÍTEM = el del proveedor
    // SELECCIONADO en la comparativa (solo lectura en la fila del item).
    selectedQuoteCost(item) {
      var costo = this.selectedQuoteCostValue(item)
      return 'S/ ' + costo.toFixed(2)
    },
    // Unitario del PROVEEDOR (columna "V. unit." de la comparativa): el que
    // saldría si ESE proveedor fuera el ganador (misma cadena del motor, con
    // su costo base).
    quoteUnitValue(item, quote) {
      var bd = this.costeoBreakdown(item, parseFloat(quote && quote.costo_base) || 0)
      if (!bd) return 'S/ 0.00'
      return 'S/ ' + (parseFloat(bd.unit) || 0).toFixed(2)
    },
    // Total de la línea (CON IGV, tal como lo guarda el motor en `total_item`).
    itemTotalValue(item) {
      var bd = this.costeoBreakdown(item)
      if (!bd) return 'S/ ' + (parseFloat(item && item.total_item) || 0).toFixed(2)
      return 'S/ ' + (parseFloat(bd.totalConIgv) || 0).toFixed(2)
    },
    // Ganancia de la línea (Q × costo del proveedor SELECCIONADO × margen%).
    itemGainValue(item) {
      var bd = this.costeoBreakdown(item)
      if (!bd) return 'S/ ' + (parseFloat(item && item.ganancia_absoluta) || 0).toFixed(2)
      return 'S/ ' + (parseFloat(bd.ganancia) || 0).toFixed(2)
    },
    // [NUEVO FLUJO 2026-10-21] ¿Esta fila es la ELEGIDA (seleccionado = 1)?
    // Es la que refleja el nombre del producto y la que define el Costo Base
    // del ítem. Las demás son respaldos.
    isItemQuoteRow(quote) {
      if (!quote) return false
      return Number(quote.seleccionado) === 1
    },
    // [NUEVO FLUJO 2026-10-21] Normaliza la comparativa tras cargar:
    //  - deja exactamente UNA fila con seleccionado = 1 (si no hay, se toma la
    //    más antigua);
    //  - la fila elegida refleja el nombre ACTUAL del producto (si no, quedaba
    //    con el nombre viejo de cuando se creó).
    markItemQuoteRow(quotes, item) {
      var list = quotes || []
      if (!list.length) return list
      var selected = null
      for (var i = 0; i < list.length; i++) {
        if (Number(list[i].seleccionado) === 1) { selected = list[i]; break }
      }
      if (!selected) {
        selected = list[0]
        for (var j = 1; j < list.length; j++) {
          if (Number(list[j].id) < Number(selected.id)) selected = list[j]
        }
        this.$set(selected, 'seleccionado', 1)
      }
      var nombre = item && item.product_name ? String(item.product_name) : ''
      if (nombre && selected.product_reference !== nombre) {
        this.$set(selected, 'product_reference', nombre)
      }
      return list
    },
    // [NUEVO FLUJO 2026-10-21] Cambió el nombre del producto del ítem: la fila
    // ELEGIDA debe reflejar SIEMPRE el nombre actual, no el viejo.
    onItemNameChanged(item, newName) {
      if (!item) return
      item.product_name = newName
      var buf = this.supplierQuoteBuffers[item.id]
      if (!buf || !buf.quotes || !buf.quotes.length) return
      for (var i = 0; i < buf.quotes.length; i++) {
        if (this.isItemQuoteRow(buf.quotes[i])) {
          if (buf.quotes[i].product_reference !== newName) {
            this.$set(buf.quotes[i], 'product_reference', newName)
          }
          this.$set(buf, 'dirty', true)
          break
        }
      }
    },
    // [NUEVO FLUJO] Guarda cambios LOCALMENTE en el buffer. NO llama al backend.
    // El backend se llama recién en flushSupplierQuotes() al presionar "Guardar detalles".
    // [NUEVO FLUJO 2026-10-21] Muta el objeto EN EL LUGAR (no crea uno nuevo):
    // los inputs usan v-model sobre qscope.row, así que si se reemplazaba el
    // objeto, la vista seguía mostrando el valor viejo (edición perdida).
    saveSupplierQuote(item, quote, patch) {
      this.initSupplierQuoteBuffer(item)
      var buf = this.supplierQuoteBuffers[item.id]
      var quotes = buf.quotes
      var idx = quotes.findIndex(function (q) { return Number(q.id) === Number(quote.id) })
      if (idx === -1) {
        var nuevo = Object.assign({}, quote, patch)
        quotes.push(nuevo)
        this.$set(buf, 'quotes', quotes)
      } else {
        var target = quotes[idx]
        Object.keys(patch).forEach(function (key) {
          this.$set(target, key, patch[key])
        }, this)
        // [NUEVO FLUJO] Si cambia el proveedor, resuelve el nombre desde la opción
        if (patch.hasOwnProperty('supplier_id')) {
          if (patch.supplier_id) {
            var opt = (this.allSuppliers || []).concat(this.supplierOptionsMap[quote.id] || []).find(function (s) {
              return Number(s.id) === Number(patch.supplier_id)
            })
            this.$set(target, 'supplier_name', opt ? (opt.nombre_comercial || opt.razon_social) : target.supplier_name)
          } else {
            this.$set(target, 'supplier_name', 'Proveedor por definir')
          }
        }
      }
      this.$set(buf, 'dirty', true)
      // Actualiza también el mapa visible por si algo lo lee de ahí
      this.$set(this.supplierQuotesMap, item.id, quotes)
    },
    // [NUEVO FLUJO] Agrega proveedor LOCALMENTE (temp id negativo para distinguir de BD).
    addSupplierQuote(item) {
      this.initSupplierQuoteBuffer(item)
      var buf = this.supplierQuoteBuffers[item.id]
      var quotes = buf.quotes
      var tempId = -(Date.now())
      // [NUEVO FLUJO 2026-10-21] Ya no hay columna `orden`. La fila nueva entra
      // como RESPALDO (seleccionado = 0); el radio "Ganador" elige cuál manda.
      quotes.push({
        id: tempId,  // temp id negativo: existe solo en local
        supplier_id: null,
        supplier_name: 'Proveedor por definir',
        origen: 'manual',
        product_reference: '',
        costo_base: 0,
        tiempo_estimado_dias: '',
        observaciones: '',
        seleccionado: 0,
        _isNew: true  // marca para que flush sepa que es nuevo
      })
      this.$set(buf, 'quotes', quotes)
      this.$set(buf, 'dirty', true)
      this.$set(this.supplierQuotesMap, item.id, quotes)
      this.$message.success('Proveedor agregado. Selecciónalo y completa los datos. Se guardará al presionar "Guardar detalles".')
    },
    // [NUEVO FLUJO] Elimina proveedor LOCALMENTE con confirmación.
    removeSupplierQuote(item, quote) {
      var self = this
      this.$confirm(
        '¿Estás seguro de eliminar al proveedor "' + (quote.supplier_name || 'sin nombre') + '"?',
        'Confirmar eliminación',
        { confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
      ).then(function () {
        self.initSupplierQuoteBuffer(item)
        var buf = self.supplierQuoteBuffers[item.id]
        var quotes = buf.quotes.filter(function (q) { return Number(q.id) !== Number(quote.id) })
        // Si se borró la elegida, promueve la primera
        if (Number(quote.seleccionado) === 1 && quotes.length > 0
            && !quotes.some(function (q) { return Number(q.seleccionado) === 1 })) {
          self.$set(quotes[0], 'seleccionado', 1)
        }
        self.$set(buf, 'quotes', quotes)
        self.$set(buf, 'dirty', true)
        self.$set(self.supplierQuotesMap, item.id, quotes)
        self.$message.success('Proveedor eliminado. Se aplicará al guardar.')
      }).catch(function () {
        // Cancelado: no hace nada
      })
    },
    // [NUEVO FLUJO] Marca el proveedor ELEGIDO LOCALMENTE (los demás quedan
    // como respaldo).
    // [NUEVO FLUJO 2026-10-21] Muta en el lugar (no reemplaza objetos) para que
    // los inputs con v-model no pierdan lo tipeado al cambiar de ganador.
    setWinnerQuote(item, quote) {
      this.initSupplierQuoteBuffer(item)
      var buf = this.supplierQuoteBuffers[item.id]
      var quotes = buf.quotes
      for (var i = 0; i < quotes.length; i++) {
        this.$set(quotes[i], 'seleccionado', Number(quotes[i].id) === Number(quote.id) ? 1 : 0)
      }
      this.$set(buf, 'quotes', quotes)
      this.$set(buf, 'dirty', true)
      this.$set(this.supplierQuotesMap, item.id, quotes)
    },
    // [NUEVO FLUJO] Sincroniza TODOS los cambios pendientes del buffer con el backend.
    // Se llama desde saveItem() cuando el usuario presiona "Guardar detalles".
    async flushSupplierQuotes(item) {
      if (!item || !item.id) return
      var buf = this.supplierQuoteBuffers[item.id]
      if (!buf) return

      // [NUEVO FLUJO 2026-10-21] La fila ELEGIDA (seleccionado = 1) ES la
      // línea del producto: su referencia debe ser SIEMPRE el nombre actual (no
      // uno viejo). Se hace ANTES de validar "dirty" para que el renombrado del
      // ítem también se guarde.
      for (var ri = 0; ri < buf.quotes.length; ri++) {
        if (this.isItemQuoteRow(buf.quotes[ri]) && item.product_name) {
          if (buf.quotes[ri].product_reference !== item.product_name) {
            this.$set(buf.quotes[ri], 'product_reference', item.product_name)
            this.$set(buf, 'dirty', true)
          }
          break
        }
      }
      if (!buf.dirty) return  // no hay cambios pendientes

      var quotes = buf.quotes
      // [NUEVO FLUJO 2026-10-21] Los originales viven en un mapa por ítem
      // (antes en item._originalQuotes, que se perdía al re-renderizar).
      var originals = this.supplierQuoteOriginals[item.id] || []  // lo que había en BD al cargar
      var idMap = {}  // [NUEVO FLUJO] tempId -> id real (quotes recién creados)

      try {
        // 1. Eliminar quotes que ya no están en el buffer (estaban en BD pero se borraron)
        for (var i = 0; i < originals.length; i++) {
          var orig = originals[i]
          var stillExists = quotes.some(function (q) { return Number(q.id) === Number(orig.id) })
          if (!stillExists) {
            await this.$http.delete(this.quotesBaseUrl(item) + '/' + orig.id)
          }
        }
        // 2. Crear o actualizar cada quote del buffer
        for (var j = 0; j < quotes.length; j++) {
          var q = quotes[j]
          var payload = {
            supplier_id: q.supplier_id || null,
            // [NUEVO FLUJO 2026-10-21] La referencia es el nombre del producto
            // del ítem: no se edita desde la fila del proveedor.
            product_reference: item.product_name || q.product_reference || '',
            costo_base: q.costo_base || 0,
            tiempo_estimado_dias: q.tiempo_estimado_dias || '',
            observaciones: q.observaciones || ''
          }
          if (q._isNew || q.id < 0) {
            // [NUEVO FLUJO] Nuevo: crear en BD. El backend devuelve la lista
            // real; el id nuevo es el que NO estaba antes (no el último,
            // que puede ser otro si el orden no coincide).
            var knownIds = quotes.filter(function (x) { return Number(x.id) > 0; })
              .map(function (x) { return Number(x.id) })
            var created = await this.$http.post(this.quotesBaseUrl(item), payload)
            var serverQuotes = (created.data && created.data.quotes) || []
            var realId = null
            for (var si = 0; si < serverQuotes.length; si++) {
              if (knownIds.indexOf(Number(serverQuotes[si].id)) === -1) {
                realId = serverQuotes[si].id
                break
              }
            }
            if (realId === null && serverQuotes.length) {
              realId = serverQuotes[serverQuotes.length - 1].id
            }
            if (realId !== null) {
              idMap[q.id] = realId
              this.$set(q, 'id', realId)
              this.$delete(q, '_isNew')
            }
          } else {
            // Existente: actualizar en BD
            await this.$http.put(this.quotesBaseUrl(item) + '/' + q.id, payload)
          }
        }
        // 3. Marcar el proveedor ELEGIDO (el que tenga seleccionado = 1, ya con id real)
        var winner = quotes.find(function (q) { return Number(q.seleccionado) === 1 && q.id > 0 })
        if (winner) {
          await this.$http.post(this.quotesBaseUrl(item) + '/' + winner.id + '/winner', {})
        }
        // 4. Recargar desde backend para tener datos consistentes
        await this.loadSupplierQuotes(item, true)
        this.$delete(this.supplierQuoteBuffers, item.id)
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error sincronizando proveedores.'
        this.$message({ message: msg, type: 'error', duration: 8000, showClose: true })
        // Recarga para mostrar estado real de BD
        this.loadSupplierQuotes(item, true)
      }
    },
    // [NUEVO FLUJO] Guardar cotización individual - ahora solo actualiza el buffer.
    // Mantenido por compatibilidad con código existente.
    // Ya NO llama al backend directamente.
    async setWinnerQuoteAPI(item, quote) {
      // Este método ya no se usa. setWinnerQuote() actualiza el buffer local.
      return
    },
    toCatalogQuote(item, quote) {
      // [NUEVO FLUJO 2026-10-20] ESTE MÉTODO YA NO SE USA.
      // El botón "Agregar catálogo" fue eliminado de la UI porque el flujo
      // nuevo no mapea productos del catálogo en proveedores.
      this.$message.info('La función de catálogo no está disponible en este flujo.')
    },
    supplierOptionsFor(quote) {
      // Lista GLOBAL precargada (todos los proveedores) + los extras buscados.
      var list = (this.allSuppliers || []).slice()
      var extras = this.supplierOptionsMap[quote.id] || []
      extras.forEach(function (s) {
        if (!list.some(function (m) { return Number(m.id) === Number(s.id) })) list.push(s)
      })
      // Filtro local (nombre comercial, razón social o RUC).
      var q = (this.supplierFilterQuery || '').toLowerCase()
      if (q) {
        list = list.filter(function (s) {
          var texto = ((s.nombre_comercial || '') + ' ' + (s.razon_social || '') + ' ' + (s.ruc || '')).toLowerCase()
          return texto.indexOf(q) !== -1
        })
      }
      // La opcion elegida siempre visible aunque no este en la lista.
      var hasCurrent = quote.supplier_id && list.some(function (s) { return Number(s.id) === Number(quote.supplier_id) })
      if (quote.supplier_id && !hasCurrent) {
        list = [{ id: quote.supplier_id, razon_social: quote.supplier_name, nombre_comercial: null }].concat(list)
      }
      return list
    },
    // Filtro LOCAL del combo de proveedores.
    // OJO: en Element 2.x el `filter-method` NO filtra solo (su valor de retorno
    // se ignora): hay que mutar la lista propia. Por eso guardamos el texto en
    // `supplierFilterQuery` y `supplierOptionsFor` lo aplica.
    filterSuppliers(query) {
      this.supplierFilterQuery = (query || '').trim()
    },
    // [NUEVO FLUJO] Precarga la lista COMPLETA de proveedores (una sola vez).
    // Se llama al abrir la comparativa para que el dropdown tenga todo disponible.
    async preloadAllSuppliers() {
      if (this.suppliersPreloaded) return
      this.suppliersPreloaded = true
      try {
        var resp = await this.$http.get('/comercial/proyectos/search-proveedores', { params: { q: '' } })
        this.allSuppliers = (resp.data && resp.data.data) || []
      } catch (e) {
        this.suppliersPreloaded = false  // permite reintentar en el proximo render
      }
    },
    // [NUEVO FLUJO] Busqueda remota de proveedores (usada por otros selects del
    // modulo; el combo de la comparativa filtra en local con filterSuppliers).
    async searchSuppliers(query) {
      var self = this
      var q = (query || '').trim()
      // [NUEVO FLUJO] Sin texto: asegura la precarga completa (dropdown abierto).
      if (!q) {
        this.preloadAllSuppliers()
        return
      }
      this.supplierSearching = true
      try {
        var resp = await this.$http.get('/comercial/proyectos/search-proveedores', { params: { q: q } })
        var rows = (resp.data && resp.data.data) || []
        // Guardar resultados para las filas abiertas (simple: todas).
        Object.keys(this.supplierQuotesMap).forEach(function (itemId) {
          (self.supplierQuotesMap[itemId] || []).forEach(function (q2) {
            var merged = (self.supplierOptionsMap[q2.id] || []).slice()
            rows.forEach(function (r) {
              if (!merged.some(function (m) { return Number(m.id) === Number(r.id) })) merged.push(r)
            })
            self.$set(self.supplierOptionsMap, q2.id, merged)
          })
        })
      } catch (e) {
        // Silencioso.
      } finally {
        this.supplierSearching = false
      }
    },
    openQuoteRefSearch(item, quote) {
      this.quoteRefTarget = { item: item, quote: quote }
      this.quoteRefQuery = quote.product_reference || item.product_name || ''
      this.quoteRefResults = []
      this.quoteRefDialogVisible = true
      this.searchQuoteRef()
    },
    async searchQuoteRef() {
      var self = this
      var q = (this.quoteRefQuery || '').trim()
      if (q.length < 2) {
        this.quoteRefResults = []
        return
      }
      this.quoteRefLoading = true
      try {
        var resp = await this.$http.get('/marketing/quotation/products/search', { params: { q: q, limit: 10 } })
        this.quoteRefResults = (resp.data && resp.data.results) || []
      } catch (e) {
        this.quoteRefResults = []
      } finally {
        this.quoteRefLoading = false
      }
    },
    async pickQuoteRef(row) {
      var t = this.quoteRefTarget
      if (!t) return
      var name = row.name || row.titulo || row.description || ''
      await this.saveSupplierQuote(t.item, t.quote, { product_reference: name, source: 'catalog' })
      this.quoteRefDialogVisible = false
      this.quoteRefTarget = null
    },
    /* ──────────────────────────────────────────────────────────
     * Kits/paquetes comerciales (backend, mockup db).
     * Los kits se arman en pre-cotización y se persisten con su propio
     * botón Guardar (ahí se asegura el borrador 1:1). Disolver borra de
     * BD de inmediato; los productos sueltos quedan intactos.
     * ────────────────────────────────────────────────────────── */
    kitsBaseUrl() {
      return '/marketing/quotation/prequotes/' + this.prequote.id + '/kits'
    },
    savedKits() {
      var self = this
      return (this.kits || []).filter(function (k) { return !k._isNew })
    },
    kitById(kitId) {
      var found = null
      ;(this.kits || []).forEach(function (k) {
        if (String(k._kid) === String(kitId)) found = k
      })
      return found
    },
    kitMemberIds(kit) {
      return ((kit && kit.members) || []).map(function (m) { return Number(m.id) })
    },
    kitMemberItems(kit) {
      // Array ESTABLE por kit: si se devolviera un filter() nuevo en cada
      // render, la tabla de componentes se re-pintaría al tipear y los
      // inputs perderían el foco. Se recalcula solo si cambia la prequote
      // o la membresía de algún kit.
      var items = (this.prequote && this.prequote.items) || []
      var self = this
      var hash = (this.kits || []).map(function (k) {
        return k._kid + ':' + self.kitMemberIds(k).join(',')
      }).join('|')
      var cache = this._kitMembersCache
      if (!cache || cache.pq !== this.prequote || cache.hash !== hash) {
        var map = {}
        ;(this.kits || []).forEach(function (k) {
          var ids = {}
          self.kitMemberIds(k).forEach(function (id) { ids[id] = true })
          map[k._kid] = items.filter(function (it) { return ids[Number(it.id)] })
        })
        cache = { pq: this.prequote, hash: hash, map: map }
        this._kitMembersCache = cache
      }
      return (cache.map[kit._kid]) || []
    },
    kitMemberCount(kit) {
      return this.kitMemberIds(kit).length
    },
    // Envoltorio estable { row } para el detalle expandido de un componente.
    // (Ver comentario en el template: el literal inline rompía la edición.)
    memberDetailScope(item) {
      if (!this._detailScopeCache) this._detailScopeCache = {}
      var key = 'm' + (item && item.id)
      var cached = this._detailScopeCache[key]
      if (!cached || cached.row !== item) {
        cached = { row: item }
        this._detailScopeCache[key] = cached
      }
      return [cached]
    },
    itemInKit(item) {
      if (!item || !item.id) return null
      var found = null
      var self = this
      ;(this.kits || []).forEach(function (k) {
        if (self.kitMemberIds(k).indexOf(Number(item.id)) !== -1) found = k
      })
      return found
    },
    isKitSelected(item) {
      return !!item && !!item.id && this.kitSelection.indexOf(item.id) !== -1
    },
    toggleKitSelected(item) {
      if (!item || !item.id || !this.isEditable) return
      if (this.itemInKit(item)) {
        this.$message.warning('Ese producto ya está en un kit.')
        return
      }
      var ix = this.kitSelection.indexOf(item.id)
      if (ix === -1) this.kitSelection.push(item.id)
      else this.kitSelection.splice(ix, 1)
    },
    findItemById(itemId) {
      var found = null
      ;((this.prequote && this.prequote.items) || []).forEach(function (it) {
        if (Number(it.id) === Number(itemId)) found = it
      })
      return found
    },
    stampKitServer(kit) {
      kit._server = JSON.stringify({
        nombre: kit.nombre || '',
        descripcion: kit.descripcion || '',
        presentacion: kit.presentacion || '',
        cantidad: Number(kit.cantidad) || 0,
      })
      return kit
    },
    kitIsDirty(kit) {
      if (!kit || kit._isNew) return true
      if (!kit._server) return false
      try {
        return JSON.stringify({
          nombre: kit.nombre || '',
          descripcion: kit.descripcion || '',
          presentacion: kit.presentacion || '',
          cantidad: Number(kit.cantidad) || 0,
        }) !== kit._server
      } catch (e) {
        return false
      }
    },
    async loadKits(force) {
      if (!this.prequote || !this.prequote.id) {
        this.kits = []
        this.kitsLoadedFor = null
        return
      }
      if (!force && this.kitsLoadedFor === this.prequote.id) return
      try {
        var resp = await this.$http.get(this.kitsBaseUrl())
        var rows = (resp.data && resp.data.kits) || []
        var self = this
        rows.forEach(function (k) {
          k._kid = 'db-' + k.id
          self.stampKitServer(k)
        })
        this.kits = rows
        this.kitsLoadedFor = this.prequote.id
        // Limpiar selección de ítems que ya entraron a un kit.
        this.kitSelection = this.kitSelection.filter(function (id) { return !self.itemInKit({ id: id }) })
      } catch (e) {
        // Silencioso: sin kits hasta el próximo intento.
      }
    },
    // Filas de la tabla: sueltos + filas kit intercaladas en la posición
    // de su primer componente. Los envoltorios de kit se reutilizan
    // (cacheados por _kid): si fueran objetos nuevos en cada render, los
    // inputs de la fila perderían el foco al tipear.
    displayRows() {
      var self = this
      var items = (this.prequote && this.prequote.items) || []
      if (!this._kitRowCache) this._kitRowCache = {}
      var rows = []
      var inserted = {}
      var num = 0
      var kitRow = function (kit) {
        var w = self._kitRowCache[kit._kid] || (self._kitRowCache[kit._kid] = { _isKit: true })
        w.displayId = 'row-' + kit._kid
        w._displayNum = num
        w.kit = kit
        return w
      }
      items.forEach(function (it) {
        it.displayId = 'item-' + it.id
        var kit = self.itemInKit(it)
        if (kit) {
          if (!inserted[kit._kid]) {
            inserted[kit._kid] = true
            num++
            rows.push(kitRow(kit))
          }
        } else {
          num++
          it._displayNum = num
          rows.push(it)
        }
      })
      // Borradores sin miembros visibles igual se muestran al final.
      ;(this.kits || []).forEach(function (k) {
        if (!inserted[k._kid]) {
          num++
          rows.push(kitRow(k))
        }
      })
      return rows
    },
    kitRowTotals(kit) {
      var members = this.kitMemberItems(kit)
      var costo = 0
      var margenS = 0
      var vunit = 0
      var total = 0
      members.forEach(function (it) {
        var q = parseFloat(it.quantity) || 0
        var cb = this.selectedQuoteCostValue(it)
        var mp = parseFloat(it.margen_porcentaje) || 0
        costo += q * cb
        margenS += q * cb * (mp / 100)
        var bd = this.costeoBreakdown(it)
        if (bd) {
          vunit += parseFloat(bd.unit) || 0
          total += parseFloat(bd.totalConIgv) || 0
        }
      }, this)
      return {
        costo: costo,
        margenS: margenS,
        margenPct: costo > 0 ? (margenS / costo) * 100 : 0,
        vunit: vunit,
        total: total,
      }
    },
    kitMetrics(kit) {
      var self = this
      var members = this.kitMemberItems(kit)
      var subtotal = 0
      var costoReal = 0
      var utilidad = 0
      members.forEach(function (it) {
        var bd = self.costeoBreakdown(it)
        if (!bd) return
        subtotal += bd.totalSinIgv || 0
        costoReal += bd.costoRealTotal || 0
        utilidad += bd.utilidadReal || 0
      })
      var margenPct = subtotal > 0 ? (utilidad / subtotal) * 100 : 0
      var g = 0.4
      var precio40 = costoReal > 0 ? costoReal / (1 - g) : 0
      var r2 = function (n) { return Math.round(n * 100) / 100 }
      return {
        subtotal: r2(subtotal),
        costoReal: r2(costoReal),
        utilidad: r2(utilidad),
        margenPct: r2(margenPct),
        precio40: r2(precio40),
        subir: r2(precio40 - subtotal),
        unitario40: r2(precio40 / Math.max(1, Number(kit.cantidad) || 0)),
      }
    },
    kitRealMarginPct(kit) {
      return this.kitMetrics(kit).margenPct
    },
    kitPhotoUrl(kit) {
      if (!kit || kit._isNew || !kit.imagen) return ''
      return '/marketing/quotation/prequotes/' + this.prequote.id + '/kits/' + kit.id + '/photo?v=' + this.kitPhotoVersion
    },
    triggerKitPhotoUpload(kit) {
      if (!kit || kit._isNew || !this.isEditable) return
      this.pendingKitPhotoId = kit.id
      if (this.$refs.kitPhotoInput) this.$refs.kitPhotoInput.click()
    },
    async onKitPhotoSelected(event) {
      var files = event.target.files
      if (!files || files.length === 0) { this.pendingKitPhotoId = null; return }
      var kitId = this.pendingKitPhotoId
      var kit = this.kitById('db-' + kitId)
      if (this.$refs.kitPhotoInput) this.$refs.kitPhotoInput.value = ''
      if (!kit) { this.pendingKitPhotoId = null; return }
      var file = files[0]
      var allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
      if (allowed.indexOf(file.type) === -1) {
        this.$message.error('Tipo de imagen no permitido. Solo JPG, PNG, WEBP o GIF.')
        this.pendingKitPhotoId = null
        return
      }
      if (file.size > 5 * 1024 * 1024) {
        this.$message.error('La imagen excede 5 MB.')
        this.pendingKitPhotoId = null
        return
      }
      this.$set(this.kitSaving, kit._kid, true)
      try {
        var fd = new FormData()
        fd.append('image', file)
        var resp = await this.$http.post(
          this.kitsBaseUrl() + '/' + kit.id + '/photo', fd,
          { headers: { 'Content-Type': 'multipart/form-data' } }
        )
        if (resp.data && resp.data.ok) {
          kit.imagen = (resp.data.kit && resp.data.kit.imagen) || resp.data.imagen || kit.imagen
          this.stampKitServer(kit)
          this.kitPhotoVersion++
          this.$message.success('Foto del kit guardada')
        } else {
          this.$message.error((resp.data && resp.data.message) || 'No se pudo subir la foto')
        }
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error subiendo la foto'
        this.$message.error(msg)
      } finally {
        this.$set(this.kitSaving, kit._kid, false)
        this.pendingKitPhotoId = null
      }
    },
    kitQtyMismatchAlert(items, expected) {
      var detail = items
        .filter(function (it) { return Number(it.quantity) !== Number(expected) })
        .map(function (it) { return '<li>' + (it.product_name || ('Ítem ' + it.id)) + ': ' + it.quantity + '</li>' })
        .join('')
      this.$alert(
        'No se puede agrupar como kit porque las cantidades no coinciden.<br><br><strong>Cantidad esperada:</strong> ' +
        expected + '<br><br><ul>' + detail + '</ul><br>Corrige la cantidad y vuelve a intentarlo.',
        'Cantidades distintas',
        { confirmButtonText: 'Entendido', dangerouslyUseHTMLString: true }
      )
    },
    selectedLooseItems() {
      var self = this
      return this.kitSelection
        .map(function (id) { return self.findItemById(id) })
        .filter(function (it) { return it && !self.itemInKit(it) })
    },
    async createKitFromSelection() {
      var nombre = (this.kitNameInput || '').trim()
      if (!nombre) {
        this.$message.warning('Escribe el nombre del kit.')
        return
      }
      var selected = this.selectedLooseItems()
      if (selected.length < 2) {
        this.$message.error('Selecciona 2 o más productos sueltos para crear un kit.')
        return
      }
      var expected = Number(selected[0].quantity)
      var bad = selected.filter(function (it) { return Number(it.quantity) !== expected })
      if (bad.length) {
        this.kitQtyMismatchAlert(selected, expected)
        return
      }
      // Borrador local: se persiste con su propio botón Guardar.
      var localId = 'new-' + (this.kitDraftSeq++)
      var kit = {
        _isNew: true,
        _kid: localId,
        id: null,
        nombre: nombre,
        descripcion: 'compuesto por ' + selected.map(function (it) { return it.product_name }).join(', '),
        presentacion: '',
        cantidad: expected,
        imagen: null,
        _draftItemIds: selected.map(function (it) { return it.id }),
        members: selected.map(function (it) { return { id: it.id } }),
      }
      this.kits.push(kit)
      this.kitSelection = []
      this.kitNameInput = ''
      this.$message.success('Kit "' + nombre + '" creado en pantalla. Guárdalo con su botón ✓.')
    },
    async addSelectionToKit() {
      var target = this.kitAction
      if (!target || target.indexOf('add:') !== 0) return
      var kit = this.kitById(target.replace('add:', ''))
      if (!kit) return
      var selected = this.selectedLooseItems()
      if (!selected.length) {
        this.$message.error('Selecciona uno o más productos sueltos.')
        return
      }
      var expected = Number(kit.cantidad)
      var bad = selected.filter(function (it) { return Number(it.quantity) !== expected })
      if (bad.length) {
        this.kitQtyMismatchAlert(selected, expected)
        return
      }
      if (kit._isNew) {
        var self = this
        selected.forEach(function (it) {
          if (kit._draftItemIds.indexOf(it.id) === -1) {
            kit._draftItemIds.push(it.id)
            kit.members.push({ id: it.id })
          }
        })
        kit.descripcion = 'compuesto por ' + kit._draftItemIds.map(function (id) {
          var it = self.findItemById(id)
          return it ? it.product_name : ''
        }).filter(Boolean).join(', ')
        this.kitSelection = []
        this.$message.success(selected.length + ' producto(s) agregado(s) al borrador. Guárdalo con su botón ✓.')
        return
      }
      try {
        var resp = await this.$http.post(this.kitsBaseUrl() + '/' + kit.id + '/members', {
          item_ids: selected.map(function (it) { return it.id }),
        })
        if (resp.data && resp.data.ok) {
          this.replaceKitFromServer(resp.data.kit)
          this.kitSelection = []
          this.$message.success('Producto(s) agregado(s) a ' + kit.nombre + '.')
        } else {
          this.$message.error((resp.data && resp.data.message) || 'No se pudo agregar al kit.')
        }
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error agregando al kit.'
        this.$message({ message: msg, type: 'error', duration: 8000, showClose: true })
      }
    },
    replaceKitFromServer(serverKit, draftKid) {
      if (!serverKit) return null
      var self = this
      serverKit._kid = 'db-' + serverKit.id
      this.stampKitServer(serverKit)
      var ix = -1
      this.kits.forEach(function (k, i) {
        if (ix !== -1) return
        if (String(k._kid) === String(serverKit._kid)) ix = i
        else if (draftKid && String(k._kid) === String(draftKid)) ix = i
      })
      if (ix >= 0) this.kits.splice(ix, 1, serverKit)
      else this.kits.push(serverKit)
      this.kitSelection = this.kitSelection.filter(function (id) { return !self.itemInKit({ id: id }) })
      return serverKit
    },
    async saveKit(kit) {
      if (!kit || !this.isEditable) return
      if (kit._isNew) {
        var nombre = (kit.nombre || '').trim()
        if (!nombre) {
          this.$message.warning('Ponle un nombre al kit antes de guardarlo.')
          return
        }
        if (!kit._draftItemIds || kit._draftItemIds.length < 2) {
          this.$message.error('El kit necesita al menos 2 productos.')
          return
        }
        this.$set(this.kitSaving, kit._kid, true)
        try {
          var resp = await this.$http.post(this.kitsBaseUrl(), {
            nombre: nombre,
            descripcion: kit.descripcion,
            presentacion: kit.presentacion,
            item_ids: kit._draftItemIds,
          })
          if (resp.data && resp.data.ok) {
            this.replaceKitFromServer(resp.data.kit, kit._kid)
            this.$message.success('Kit guardado.')
          } else {
            this.$message.error((resp.data && resp.data.message) || 'No se pudo guardar el kit.')
          }
        } catch (e) {
          var msg = (e.response && e.response.data && e.response.data.message) || 'Error guardando el kit.'
          this.$message({ message: msg, type: 'error', duration: 8000, showClose: true })
        } finally {
          this.$set(this.kitSaving, kit._kid, false)
        }
        return
      }
      this.$set(this.kitSaving, kit._kid, true)
      try {
        var resp2 = await this.$http.put(this.kitsBaseUrl() + '/' + kit.id, {
          nombre: kit.nombre,
          descripcion: kit.descripcion,
          presentacion: kit.presentacion,
          cantidad: kit.cantidad,
        })
        if (resp2.data && resp2.data.ok) {
          this.replaceKitFromServer(resp2.data.kit)
          await this.loadPrequoteById(this.prequote.id)
          this.$message.success('Kit actualizado.')
        } else {
          this.$message.error((resp2.data && resp2.data.message) || 'No se pudo actualizar el kit.')
        }
      } catch (e2) {
        var msg2 = (e2.response && e2.response.data && e2.response.data.message) || 'Error actualizando el kit.'
        this.$message({ message: msg2, type: 'error', duration: 8000, showClose: true })
      } finally {
        this.$set(this.kitSaving, kit._kid, false)
      }
    },
    async removeKitMember(kit, item) {
      if (!kit || !item || !this.isEditable) return
      if (kit._isNew) {
        kit._draftItemIds = kit._draftItemIds.filter(function (id) { return Number(id) !== Number(item.id) })
        kit.members = kit.members.filter(function (m) { return Number(m.id) !== Number(item.id) })
        if (kit._draftItemIds.length < 2) {
          this.$message.warning('Al kit le queda menos de 2 productos: disuélvelo o agrégale más.')
        }
        return
      }
      try {
        var resp = await this.$http.delete(this.kitsBaseUrl() + '/' + kit.id + '/members/' + item.id)
        if (resp.data && resp.data.ok) {
          this.replaceKitFromServer(resp.data.kit)
          this.$message.success('Producto suelto.')
        } else if (resp.data && resp.data.must_dissolve) {
          this.askDissolveKit(kit, item.id)
        } else {
          this.$message.error((resp.data && resp.data.message) || 'No se pudo quitar del kit.')
        }
      } catch (e) {
        if (e.response && e.response.data && e.response.data.must_dissolve) {
          this.askDissolveKit(kit, item.id)
        } else {
          var msg = (e.response && e.response.data && e.response.data.message) || 'Error quitando del kit.'
          this.$message({ message: msg, type: 'error', duration: 8000, showClose: true })
        }
      }
    },
    askDissolveKit(kit, onlyItemId) {
      var self = this
      if (!kit || !this.isEditable) return
      if (kit._isNew) {
        var ix = -1
        this.kits.forEach(function (k, i) { if (String(k._kid) === String(kit._kid)) ix = i })
        if (ix >= 0) this.kits.splice(ix, 1)
        this.$message.success('Borrador disuelto. Sus componentes vuelven a productos sueltos.')
        return
      }
      this.$prompt(
        'Si disuelves el kit "' + kit.nombre + '" se borrará de la base de datos (los productos sueltos quedan intactos). Para confirmar, escribe el nombre del kit.',
        'Disolver kit',
        {
          confirmButtonText: 'Disolver',
          cancelButtonText: 'Cancelar',
          inputPlaceholder: kit.nombre,
          inputValidator: function (val) {
            return val === kit.nombre ? true : 'Escribe exactamente: ' + kit.nombre
          },
        }
      ).then(async function () {
        try {
          // Al disolver se borra el kit de BD con sus enlaces (los productos
          // quedan intactos como sueltos). Incluye el miembro pendiente.
          var resp = await self.$http.delete(self.kitsBaseUrl() + '/' + kit.id)
          if (resp.data && resp.data.ok) {
            var ix2 = -1
            self.kits.forEach(function (k, i) { if (String(k._kid) === String(kit._kid)) ix2 = i })
            if (ix2 >= 0) self.kits.splice(ix2, 1)
            self.$message.success('Kit disuelto. Sus componentes vuelven a productos sueltos.')
          } else {
            self.$message.error((resp.data && resp.data.message) || 'No se pudo disolver el kit.')
          }
        } catch (e) {
          var msg = (e.response && e.response.data && e.response.data.message) || 'Error disolviendo el kit.'
          self.$message({ message: msg, type: 'error', duration: 8000, showClose: true })
        }
      }).catch(function () {})
    },

    openImagePreview() {
      this.imagePreviewVisible = true
    },

    /*
    |------------------------------------------------------------------
    | Imagen POR ÍTEM (multimodal) — mismo patrón que la del requerimiento
    |------------------------------------------------------------------
    | Consume los endpoints del módulo Marketing:
    |   POST   /marketing/quotation/prequote-items/{id}/reference-image  (campo "image")
    |   GET    /marketing/quotation/prequote-items/{id}/reference-image
    |   DELETE /marketing/quotation/prequote-items/{id}/reference-image
    */

    /**
     * URL de la miniatura del ítem. Si el ítem tiene imagen
     * (product_reference_image o has_image), sirve desde el endpoint GET con
     * cache-bust; si no, retorna '' para mostrar el placeholder.
     */
    itemImageUrl(item) {
      if (!item || !item.id) return ''
      if (!item.product_reference_image && !item.has_image) return ''
      return '/marketing/quotation/prequote-items/' + item.id + '/reference-image?v=' + this.itemImageVersion
    },

    triggerItemImageUpload(item) {
      if (!item || !item.id) return
      this.uploadingItemImageId = item.id
      if (this.$refs.itemImageInput) this.$refs.itemImageInput.click()
    },

    async onItemImageSelected(event) {
      var files = event.target.files
      if (!files || files.length === 0) { this.uploadingItemImageId = null; return }
      var file = files[0]

      var itemId = this.uploadingItemImageId
      if (!itemId) {
        if (this.$refs.itemImageInput) this.$refs.itemImageInput.value = ''
        return
      }

      await this.uploadItemImageFile(itemId, file)
      // Limpiar el input para permitir re-seleccionar el mismo archivo
      if (this.$refs.itemImageInput) this.$refs.itemImageInput.value = ''
    },
    // Dropzone del expandible (formato mockup): click o arrastre usan la misma subida.
    onItemImageDrop(item, event) {
      if (!item || !item.id || !this.isEditable) return
      var files = event.dataTransfer && event.dataTransfer.files
      if (!files || files.length === 0) return
      this.uploadingItemImageId = item.id
      this.uploadItemImageFile(item.id, files[0])
    },
    async uploadItemImageFile(itemId, file) {

      // Validación cliente espejo del backend (mime + 5MB)
      var allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
      if (allowed.indexOf(file.type) === -1) {
        this.$message.error('Tipo de imagen no permitido. Solo JPG, PNG, WEBP o GIF.')
        this.uploadingItemImageId = null
        if (this.$refs.itemImageInput) this.$refs.itemImageInput.value = ''
        return
      }
      if (file.size > 5 * 1024 * 1024) {
        this.$message.error('La imagen excede 5 MB.')
        this.uploadingItemImageId = null
        if (this.$refs.itemImageInput) this.$refs.itemImageInput.value = ''
        return
      }

      try {
        var fd = new FormData()
        fd.append('image', file)
        var resp = await this.$http.post(
          '/marketing/quotation/prequote-items/' + itemId + '/reference-image',
          fd,
          { headers: { 'Content-Type': 'multipart/form-data' } }
        )
        if (resp.data && resp.data.ok) {
          this.$message.success('Imagen del ítem guardada')
          // Marcar el ítem local con imagen + bust cache para refrescar la miniatura
          this.markItemHasImage(itemId, resp.data.image ? resp.data.image.path : true)
          this.itemImageVersion++
        } else {
          this.$message.error((resp.data && resp.data.message) || 'No se pudo subir la imagen')
        }
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error subiendo imagen'
        this.$message.error(msg)
      } finally {
        this.uploadingItemImageId = null
        // Limpiar el input para permitir re-seleccionar el mismo archivo
        if (this.$refs.itemImageInput) this.$refs.itemImageInput.value = ''
      }
    },

    async deleteItemImage(item) {
      if (!item || !item.id) return
      try {
        await this.$confirm('¿Quitar la imagen de este producto?', 'Confirmar',
          { confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar', type: 'warning' })
      } catch (e) { return }

      try {
        var resp = await this.$http.delete(
          '/marketing/quotation/prequote-items/' + item.id + '/reference-image'
        )
        if (resp.data && resp.data.ok) {
          this.$message.success('Imagen del ítem eliminada')
          this.markItemHasImage(item.id, null)
          this.itemImageVersion++
        } else {
          this.$message.error((resp.data && resp.data.message) || 'No se pudo eliminar la imagen')
        }
      } catch (e) {
        var msg = (e.response && e.response.data && e.response.data.message) || 'Error eliminando imagen'
        this.$message.error(msg)
      }
    },

    /**
     * Actualiza de forma reactiva el flag de imagen del ítem en
     * this.prequote.items. value = path (string) cuando hay imagen, o null
     * cuando se quita.
     */
    markItemHasImage(itemId, value) {
      if (!this.prequote || !this.prequote.items) return
      for (var i = 0; i < this.prequote.items.length; i++) {
        if (parseInt(this.prequote.items[i].id) === parseInt(itemId)) {
          this.$set(this.prequote.items[i], 'product_reference_image', value || null)
          this.$set(this.prequote.items[i], 'has_image', !!value)
          break
        }
      }
    },

    /**
     * Reemplazar el ítem editado en this.prequote.items para refrescar UI.
     */
    updateLocalItem(updated) {
      if (!updated || !this.prequote || !this.prequote.items) return
      // Parsear suggestions si viene como string
      if (typeof updated.product_suggestions === 'string' && updated.product_suggestions) {
        try { updated.product_suggestions = JSON.parse(updated.product_suggestions) } catch (e) {}
      }
      updated.quantity = parseInt(updated.quantity) || 0
      updated.costo_base = parseFloat(updated.costo_base) || 0
      updated.margen_porcentaje = parseFloat(updated.margen_porcentaje) || 0

      for (var i = 0; i < this.prequote.items.length; i++) {
        if (parseInt(this.prequote.items[i].id) === parseInt(updated.id)) {
          this.$set(this.prequote.items, i, updated)
          break
        }
      }
    },
  }
}
</script>

<style>
/* R4 — Campos en solo-lectura (cuando la pre-cotización está validada/aprobada):
   texto legible en vez del gris muy claro por defecto de Element UI. Mismo
   criterio aplicado en Proyectos > Datos Generales. */
.tab-precotizacion .el-input.is-disabled .el-input__inner,
.tab-precotizacion .el-textarea.is-disabled .el-textarea__inner,
.tab-precotizacion .el-input-number.is-disabled .el-input__inner {
  color: #5a5e66;
  -webkit-text-fill-color: #5a5e66;
}

.product-match-dialog .el-dialog__body { padding-top: 16px; }
.product-match-autocomplete { max-height: 320px; overflow-y: auto; }

/* Buscador manual en tarjetas (mismo endpoint de catálogo) */
.catalog-cards { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 10px; margin-top: 10px; max-height: 380px; overflow-y: auto; padding: 2px; }
.catalog-card { display: flex; gap: 10px; border: 1px solid #ebeef5; border-radius: 8px; padding: 10px; cursor: pointer; background: #fff; min-width: 0; }
.catalog-card:hover { border-color: #4f46e5; background: #f8f7ff; }
.catalog-card__badge { width: 64px; height: 64px; flex: 0 0 64px; border-radius: 8px; background: #eef4ff; color: #2b5fc7; font-weight: 800; font-size: 13px; display: flex; align-items: center; justify-content: center; text-align: center; overflow: hidden; position: relative; }
.catalog-card__badge img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; }
.catalog-card__body { flex: 1; min-width: 0; }
.catalog-card__head { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; font-size: 13px; }
.catalog-card__score { flex: 0 0 auto; font-size: 11px; color: #909399; white-space: nowrap; }
.catalog-card__desc { font-size: 12px; color: #606266; margin-top: 2px; }
.catalog-card__meta { font-size: 11px; color: #909399; margin-top: 4px; display: flex; gap: 8px; flex-wrap: wrap; }
.catalog-card__cta { margin-top: 6px; font-size: 12px; }
.catalog-card__cta-link { color: #2b5fc7; font-weight: 700; }
.catalog-cards__empty { grid-column: 1 / -1; text-align: center; color: #909399; padding: 18px 0; font-size: 13px; }
@media (max-width: 768px) {
  .catalog-cards { grid-template-columns: minmax(0,1fr); }
}

/* Rediseño — barra de totales fija arriba del tab (formato referencia) */
.precot-totals {
  position: sticky; top: 0; z-index: 20;
  background: #fff; border: 1px solid #ebeef5; border-radius: 6px;
  padding: 8px 12px; margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(0,0,0,.04);
}
.precot-totals__grid { display: flex; flex-wrap: wrap; gap: 6px 14px; }
.precot-total { flex: 1 1 0; min-width: 86px; display: flex; flex-direction: column; gap: 2px; overflow: hidden; }
.precot-total--wide { flex: 1.7 1 0; min-width: 112px; }
.precot-total__label { font-size: 10px; color: #909399; white-space: nowrap; }
.precot-total > span:last-child, .precot-total .el-tag { font-size: 12px; color: #303133; }
.precot-total--total { flex: 1.3 1 0; align-items: flex-end; }
.precot-total--total strong { font-size: 15px; color: #303133; }

/* Rediseño — acordeones de ingreso (IA + kits) */
.precot-collapse { border: 1px solid #ebeef5; border-radius: 6px; overflow: hidden; }
.precot-collapse .el-collapse-item__header { padding: 0 12px; }
.precot-collapse .el-collapse-item__content { padding: 12px; }

/* Rediseño (formato mockup) — expandible: main + resultado al costado */
.erp-detail-layout { display: grid; grid-template-columns: minmax(0,1fr) 400px; gap: 18px; align-items: start; }
.erp-detail-main { min-width: 0; }
.erp-tech-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 10px 14px; align-items: start; }
.erp-tech-duo { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 10px 14px; margin-top: 10px; }
.erp-tech-duo > :only-child { grid-column: 1 / -1; }
.erp-tech-duo .el-textarea__inner { min-height: 64px; }
.erp-tech-minirow { grid-column: 1 / -1; grid-row: auto; display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 10px; }
.erp-tech-grid .ai-field { margin-bottom: 0; }
.erp-tech-grid .ai-field label, .erp-costgrid .ai-field label { display: block; margin-bottom: 4px; color: #818897; text-transform: uppercase; font-size: 10px; font-weight: 800; }
.erp-tech-grid .el-input__inner, .erp-tech-grid .el-textarea__inner { font-size: 12px; padding: 0 8px; }
.erp-cost { margin-top: 16px; padding-top: 14px; border-top: 1px dashed #d7dce8; }
.erp-cost-media-grid { display: grid; grid-template-columns: minmax(0,1fr) 300px; gap: 14px; align-items: stretch; }
.erp-costgrid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 12px; align-content: start; }
.erp-costgrid .erp-span2 { grid-column: 1 / -1; }
.detail-photo-drop { position: relative; min-height: 210px; border: 1px dashed #b9c7dd; border-radius: 7px; background: #f8fbff; display: flex; align-items: center; justify-content: center; text-align: center; color: #657083; cursor: pointer; overflow: hidden; padding: 14px; outline: 0; }
.detail-photo-drop:hover, .detail-photo-drop:focus { border-color: #4f46e5; background: #f6f5ff; }
.detail-photo-drop img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; }
.detail-photo-copy { position: relative; z-index: 1; }
.detail-photo-copy strong { display: block; color: #2d3a50; font-size: 15px; }
.detail-photo-copy span { display: block; margin-top: 4px; font-size: 12px; }
.detail-photo-remove { position: absolute; top: 8px; right: 8px; z-index: 2; width: 28px; height: 28px; border: 1px solid #ffd0d8; border-radius: 50%; background: #fff; color: #ee4d64; font-weight: 900; line-height: 1; cursor: pointer; box-shadow: 0 4px 12px rgba(17,24,39,.12); }
.erp-costcard { border: 1px solid #cfe1ff; border-radius: 7px; background: #f5f9ff; padding: 12px 14px; font-size: 13px; }
.erp-costcard .costtitle { font-weight: 900; margin-bottom: 8px; }
.erp-costcard .costeo-breakdown { margin: 0; }
@media (max-width: 1100px) {
  .erp-detail-layout { grid-template-columns: minmax(0,1fr); }
  .erp-cost-media-grid { grid-template-columns: minmax(0,1fr); }
  .erp-tech-grid { grid-template-columns: minmax(0,1fr); }
  .erp-tech-grid .erp-obs { grid-column: 1; grid-row: auto; }
  .erp-tech-minirow { grid-column: 1; grid-row: auto; }
  .erp-tech-duo { grid-template-columns: minmax(0,1fr); }
}

/* Rediseño — kits solo-frontales */
.kit-front__hint { color: #909399; font-size: 12px; margin: 0 0 8px; }
.kit-front__list { margin-top: 10px; border-top: 1px dashed #dcdfe6; padding-top: 8px;
  display: flex; flex-direction: column; gap: 8px; }
.kit-front__row { display: flex; justify-content: space-between; align-items: flex-start;
  gap: 8px; background: #fafafa; border: 1px solid #ebeef5; border-radius: 6px; padding: 6px 8px; }

/* S2 — Imagen de referencia */
.image-ref-card { border-left: 3px solid #409EFF; }
.image-ref-header { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; }
.image-ref-empty { padding: 8px 0; }
.image-ref-loaded { display: flex; gap: 16px; align-items: flex-start; }
.image-ref-thumb {
  width: 140px; height: 140px; object-fit: cover;
  border-radius: 8px; border: 1px solid #ddd; cursor: zoom-in;
  background: #fafafa;
}
.image-ref-thumb:hover { border-color: #409EFF; }
.image-ref-actions { display: flex; flex-direction: column; gap: 6px; align-items: flex-start; flex: 1; }
.image-ref-preview-dialog .el-dialog__body { padding: 12px; }

/* Foto por ítem (columna en la tabla de items) */
.item-foto-cell { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.item-foto-click { cursor: pointer; }
.item-foto-click:hover { border-color: #4f46e5; }
.item-foto-thumb {
  width: 48px; height: 48px; object-fit: cover;
  border-radius: 4px; border: 1px solid #ddd; background: #fafafa;
}
.item-foto-empty {
  display: inline-flex; align-items: center; justify-content: center;
  width: 48px; height: 48px; border-radius: 4px;
  border: 1px dashed #dcdfe6; color: #c0c4cc; font-size: 20px; background: #fafafa;
}
.item-foto-actions { display: flex; flex-direction: column; align-items: center; line-height: 1.1; }
.item-foto-actions .el-button { padding: 2px 0; font-size: 11px; }
.item-foto-del { color: #f56c6c; }

/* ───────────────────────────────────────────────────────────────────────
 * Fila expandible con detalles técnicos del ítem + badge "Inferido por IA"
 * El badge se aplica a los campos que la IA infirió y la ejecutiva no ha
 * confirmado todavía (ver datos_inferidos[] en el modelo PreQuoteItem).
 *
 * Nota sobre layout:
 * La tabla padre tiene columnas que suman ~1240px y entra en scroll
 * horizontal cuando el viewport es menor. El panel expand NO debe seguir
 * el scroll de la tabla — se queda sticky al borde izquierdo del viewport
 * visible para que el botón "Guardar detalles" siempre sea accesible sin
 * tener que volver al inicio del scroll.
 * ─────────────────────────────────────────────────────────────────────── */
.ai-inferred-alert .el-alert__title { line-height: 1.5; }
.ai-inferred-details {
  padding: 14px 20px;
  background: #fdfaf3;
  border-left: 3px solid #f5c842;
  /* Ocupa todo el ancho de la fila expandida (la tabla ya no tiene scroll
   * horizontal ni sidebar al costado en este tab). */
  width: 100%;
  max-width: none;
  min-width: 0;
  box-sizing: border-box;
}
.ai-inferred-details__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 14px;
  flex-wrap: wrap;
  gap: 8px;
}
.ai-inferred-details__title { color: #606266; font-size: 13px; }
.ai-inferred-details__title i { margin-right: 4px; color: #909399; }
.ai-inferred-details__warning {
  background: #fff4d6;
  border: 1px solid #f5c842;
  color: #8a6d0d;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
}
.ai-inferred-details__warning i { margin-right: 4px; }
.ai-field { margin-bottom: 10px; position: relative; }
.ai-field label {
  display: block;
  font-size: 11px;
  color: #909399;
  margin-bottom: 3px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  font-weight: 500;
}
.ai-field--with-outside-icon { padding-right: 22px; }
.ai-inferred-input .el-input__inner,
.ai-inferred-input.el-input-number .el-input__inner,
.ai-inferred-input .el-textarea__inner {
  background-color: #fff !important;
}
.ai-inferred-icon {
  color: #f5c842;
  font-size: 14px;
  cursor: help;
  padding-right: 4px;
  line-height: 28px;
}
.ai-inferred-icon:hover { color: #d4a017; }
.ai-inferred-icon--outside {
  position: absolute;
  right: 4px;
  top: 24px;
  font-size: 13px;
}
.ai-inferred-details__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 8px;
  flex-wrap: wrap;
  gap: 8px;
}
.ai-inferred-details__hint {
  font-size: 11px;
  color: #909399;
  font-style: italic;
}
.ai-inferred-details__hint i { color: #f5c842; }

/* ── Sección de Costeo (Fase A) ─────────────────────────────────────── */
.costeo-section {
  margin-top: 14px;
  padding-top: 12px;
  border-top: 1px dashed #dcdfe6;
}
.costeo-section__title { font-size: 13px; color: #606266; margin-bottom: 10px; }
.costeo-section__title i { color: #409EFF; margin-right: 4px; }
.costeo-section__hint {
  display: block; font-size: 11px; color: #909399; font-weight: 400; margin-top: 2px;
}
.costeo-breakdown {
  margin-top: 10px; max-width: 420px; background: #f4f8ff;
  border: 1px solid #d9ecff; border-radius: 6px; padding: 10px 14px; font-size: 12px;
}
.costeo-breakdown__row {
  display: flex; justify-content: space-between; padding: 2px 0; color: #606266;
}
.costeo-breakdown__val { font-variant-numeric: tabular-nums; }
.costeo-breakdown__total {
  border-top: 1px solid #c6e2ff; margin-top: 4px; padding-top: 6px;
  font-weight: 600; color: #303133;
}
.costeo-breakdown__unit { font-weight: 600; color: #409EFF; }
.costeo-breakdown__margen { color: #67C23A; }
.costeo-breakdown__margen small { color: #909399; font-weight: normal; font-size: 11px; }
/* Agosto 2026 (punto 7): fila del botón "Aplicar margen", separada del listado
   de cifras para que se lea como una acción y no como un renglón más. */
.costeo-breakdown__accion {
  display: flex; align-items: center;
  border-top: 1px solid #c6e2ff; margin-top: 6px; padding-top: 8px;
}
.campo-margen-soles { color: #67C23A; font-weight: 600; }
.sctr-cotizacion {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 4px 0 10px;
  padding: 6px 10px;
  background: #f4f6f9;
  border: 1px solid #e4e7ed;
  border-radius: 4px;
}
.sctr-cotizacion__label { font-size: 13px; font-weight: 600; color: #606266; white-space: nowrap; }
.sctr-cotizacion__label i { color: #409EFF; margin-right: 2px; }
.sctr-cotizacion__hint { font-size: 11px; color: #909399; }
.costeo-breakdown__note {
  margin-top: 8px; font-size: 10px; color: #909399; font-style: italic; line-height: 1.4;
}

/* ── Colores por ROL de campo (UX) ──────────────────────────────────────
 * 🔵 precio: afecta el cálculo | ⚪ descriptivo: solo informa | 🟢 calculado.
 * El amarillo del badge "Inferido por IA" (.ai-inferred-input) usa !important
 * y por eso gana sobre el gris descriptivo cuando ambos aplican.
 * ──────────────────────────────────────────────────────────────────────── */

/* 🔵 Afecta el precio — tabla principal (Cant., Costo Base, Margen) + costeo */
.campo-precio .el-input__inner,
.costeo-section .el-input__inner {
  background-color: #ecf5ff;
  border-color: #b3d8ff;
}

/* Números de la tabla de ítems: Element les da 130px fijos y se desbordan
 * de la celda, tapando el valor con los botones. Se fuerzan al 100%. */
.items-table .el-input-number { width: 100%; }
.items-table .el-input-number .el-input__inner { padding-left: 6px; padding-right: 32px; text-align: right; }

/* ⚪ Descriptivo — detalles técnicos del expandible */
.campo-descriptivo .el-input__inner,
.erp-tech-grid .el-input__inner,
.erp-tech-grid .el-textarea__inner {
  background-color: #f4f4f5;
}

/* 🟢 Calculado (read-only) — V. Unit. y Total */
.campo-calculado {
  display: inline-block;
  background-color: #f0f9eb;
  color: #529b2e;
  padding: 1px 6px;
  border-radius: 4px;
}

/* ⚠️ Fase B — Margen por debajo del mínimo configurado
 * Refuerza el celeste del campo-precio con borde y fondo de alerta. Solo es
 * advertencia visual: el guardado sigue habilitado y la ejecutiva decide. */
.campo-margen-bajo .el-input__inner,
.campo-margen-bajo.el-input-number .el-input__inner {
  background-color: #fef0f0 !important;
  border-color: #f56c6c !important;
  color: #c45656 !important;
  font-weight: 600;
}
.campo-margen-bajo__hint {
  font-size: 10px;
  color: #f56c6c;
  margin-top: 2px;
  line-height: 1.2;
  text-align: right;
}
.campo-margen-bajo__hint .el-icon-warning {
  margin-right: 2px;
}

/* 📦 Fase C — Badge de escalón aplicado debajo del Costo Base
 * Indica que el costo viene de item_unit_types (precio por volumen del
 * catálogo) y que se recalculará al cambiar la cantidad. */
.tier-aplicado-badge {
  font-size: 10px;
  color: #67c23a;
  margin-top: 2px;
  text-align: right;
  line-height: 1.2;
  cursor: help;
}
.tier-aplicado-badge .el-icon-collection-tag {
  margin-right: 2px;
}

/* Sección de escalones de precio por volumen en la fila expandida */
.tiers-section {
  margin-top: 14px;
  padding: 10px 12px;
  background: #f0f9eb;
  border-left: 3px solid #67c23a;
  border-radius: 0 4px 4px 0;
}
/* Comparativa de proveedores por ítem (mockup db) */
.supplier-quotes-section {
  margin-top: 14px;
  padding: 10px 12px;
  background: #fdf6ec;
  border-left: 3px solid #e6a23c;
  border-radius: 0 4px 4px 0;
}
.supplier-quotes__title { font-size: 13px; color: #606266; font-weight: 800; margin-bottom: 2px; }
.supplier-quotes__hint { font-size: 11px; color: #909399; margin-bottom: 8px; }
/* [NUEVO FLUJO 2026-10-21] Radio de "Ganador": se usa el-radio de Element
   (el <input type="radio"> nativo lo oculta el CSS global del tema). */
.supplier-quotes-section .el-radio { margin-right: 0; }
.supplier-quotes__radio-label { display: inline-block; }
.supplier-quotes__respaldo {
  font-size: 10px; color: #a8abb2; margin-top: 2px; line-height: 1;
}
/* Kits/paquetes (mockup db) */
.kit-bar { padding: 4px 2px; }
.kit-bar__row { display: flex; gap: 8px; align-items: center; margin-bottom: 6px; flex-wrap: wrap; }
.kit-bar__label { font-size: 11px; color: #818897; text-transform: uppercase; font-weight: 800; }
.kit-bar__sub { font-size: 11px; color: #909399; }
.kit-detail { border: 1px solid #ebeef5; border-radius: 6px; padding: 12px; background: #fffdf6; }
.kit-detail__header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 8px; flex-wrap: wrap; }
.kit-detail__title { font-size: 13px; color: #606266; }
.kit-detail__title i { color: #e6a23c; margin-right: 4px; }
.kit-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px; margin-bottom: 12px; }
.kit-summary .metric { background: #fff; border: 1px solid #ebeef5; border-radius: 6px; padding: 8px 10px; }
.kit-summary .metric span { display: block; font-size: 10px; color: #909399; text-transform: uppercase; font-weight: 800; }
.kit-summary .metric strong { font-size: 14px; color: #303133; }
.kit-detail__grid { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 10px 14px; margin-bottom: 10px; }
.kit-detail__photo { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.kit-photo-thumb { width: 110px; height: 82px; object-fit: cover; border-radius: 6px; border: 1px solid #d1d5db; background: #fff; }
.kit-detail__subtitle { font-size: 13px; color: #606266; margin: 6px 0 8px; }
.tiers-section__title { font-size: 13px; color: #606266; margin-bottom: 8px; }
.tiers-section__title i { color: #67c23a; margin-right: 4px; }
.tiers-section__hint {
  display: block;
  font-size: 11px;
  color: #909399;
  font-weight: normal;
  margin-top: 2px;
}
/* Resaltar la fila del escalón actualmente aplicado en la tabla */
.el-table .tier-row-applied,
.el-table .tier-row-applied > td {
  background-color: #d9ecff !important;
  font-weight: 600;
}

/* Leyenda de colores arriba de la tabla */
.campos-leyenda {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  margin-bottom: 8px;
  font-size: 11px;
  color: #606266;
}
.campos-leyenda__item { display: inline-flex; align-items: center; gap: 5px; }
.campos-leyenda__dot {
  display: inline-block; width: 11px; height: 11px; border-radius: 3px; border: 1px solid rgba(0,0,0,.08);
}
.campos-leyenda__dot--precio { background-color: #ecf5ff; border-color: #b3d8ff; }
.campos-leyenda__dot--descriptivo { background-color: #f4f4f5; }
.campos-leyenda__dot--calculado { background-color: #f0f9eb; }

/* Tooltips de ayuda (icono informativo en labels de formulario y headers de tabla) */
.ai-field label.lbl-help { cursor: help; }
.ai-field label.lbl-help .field-tip-icon { color: #909399; font-size: 12px; margin-left: 3px; vertical-align: middle; cursor: help; }
.col-tip { cursor: help; }
.col-tip .el-icon-info { color: #909399; font-size: 12px; margin-left: 2px; }
</style>
