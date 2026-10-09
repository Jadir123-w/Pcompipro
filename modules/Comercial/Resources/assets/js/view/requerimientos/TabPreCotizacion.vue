<template>
  <div v-loading="loading" class="tab-precotizacion">

    <!-- ═══ COM-02 · "Referencia del producto": agregar 1 ítem por IA (texto XOR imagen), sin borrar los existentes ═══
         Se muestra en arranque en frío (sin prequote) y con prequote editable. La sección "Imagen del requerimiento"
         + "Re-interpretar con imagen" se mantiene aparte, más abajo. -->
    <el-card v-if="refReqId && !loading && (!prequote || isEditable)" shadow="never"
             class="mb-2" style="border-left:3px solid #4f46e5;">
      <div slot="header" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
        <span><i class="el-icon-magic-stick" style="color:#4f46e5;"></i> <strong>Referencia del producto</strong> <span style="color:#dc2626;">*</span></span>
        <small style="color:#909399; text-align:right;">Escribe el producto o sube una imagen. La IA interpretará la referencia y agregará un nuevo ítem.</small>
      </div>

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
          <el-button size="mini" icon="el-icon-upload"
                     :disabled="referenceMode === 'texto' || interpretingItem"
                     @click="triggerReferenceImageUpload">Subir imagen</el-button>
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
        <el-button type="primary" icon="el-icon-plus" :loading="interpretingItem"
                   :disabled="!referenceMode" @click="interpretItem">
          {{ interpretingItem ? 'Interpretando…' : 'Interpretar ítem' }}
        </el-button>
      </div>
    </el-card>

    <!-- Sin precotización: botón para generar -->
    <div v-if="!prequote && !loading" style="text-align:center; padding:30px 0;">
      <p style="color:#888; margin-bottom:15px;">Este requerimiento aún no tiene precotización generada.</p>
      <el-button type="primary" icon="el-icon-magic-stick" @click="generatePrequote" :loading="generating">
        Generar Pre-Cotización con IA
      </el-button>
    </div>

    <!-- Con precotización: editor -->
    <div v-if="prequote">

      <!-- Info general — Resumen General (COM-02).
           Fila 1: los 4 indicadores históricos (valores PERSISTIDOS por el backend) + Utilidad Real.
           Fila 2: los 4 indicadores nuevos del costo real + TOTAL (persistido).
           Los 5 nuevos se calculan EN VIVO desde los ítems (computed `resumenCostoReal`),
           así reflejan lo que hay en pantalla aunque todavía no se haya guardado. -->
      <el-row :gutter="15">
        <el-col :span="4">
          <strong>Estado:</strong>
          <el-tag :type="estadoTagType(prequote.estado)" size="small">{{ prequote.estado }}</el-tag>
        </el-col>
        <el-col :span="5">
          <strong>Subtotal:</strong> S/ {{ parseFloat(prequote.subtotal).toFixed(2) }}
        </el-col>
        <el-col :span="4">
          <strong>IGV:</strong> S/ {{ parseFloat(prequote.total_igv).toFixed(2) }}
        </el-col>
        <el-col :span="6">
          <strong>Margen Ganancia:</strong> <span style="color:#67C23A;">S/ {{ parseFloat(prequote.ganancia_total || 0).toFixed(2) }}</span>
        </el-col>
        <el-col :span="5">
          <span :title="tituloEnVivo"><strong>Utilidad Real:</strong> <span style="color:#67C23A;">S/ {{ resumenCostoReal.utilidadReal.toFixed(2) }}</span></span>
        </el-col>
      </el-row>
      <el-row :gutter="15" class="mb-2" style="margin-top:6px;">
        <el-col :span="4">
          <span :title="tituloEnVivo"><strong>Margen Real:</strong> {{ margenRealTotalPct.toFixed(2) }}%</span>
        </el-col>
        <el-col :span="5">
          <span :title="tituloEnVivo"><strong>Movilidad:</strong> S/ {{ resumenCostoReal.movilidad.toFixed(2) }}</span>
        </el-col>
        <el-col :span="4">
          <span :title="tituloEnVivo"><strong>Empaque:</strong> S/ {{ resumenCostoReal.empaque.toFixed(2) }}</span>
        </el-col>
        <el-col :span="6">
          <span :title="tituloEnVivo"><strong>Seguro:</strong> S/ {{ resumenCostoReal.seguro.toFixed(2) }}</span>
        </el-col>
        <el-col :span="5">
          <strong style="font-size:16px;">TOTAL: S/ {{ parseFloat(prequote.total).toFixed(2) }}</strong>
        </el-col>
      </el-row>

      <!-- S2 — Imagen de referencia del requerimiento (multimodal AIEngine) -->
      <el-card v-if="prequote" shadow="never" class="mb-2 image-ref-card">
        <div slot="header" class="image-ref-header">
          <span><i class="el-icon-picture-outline"></i> <strong>Imagen del requerimiento (opcional)</strong></span>
          <small style="color:#909399;">Si subes una imagen, la IA la usa para inferir mejor material, técnica, medidas y variantes.</small>
        </div>

        <div v-if="!prequote.reference_image_path" class="image-ref-empty">
          <el-button v-if="isEditable" size="small" type="primary" plain icon="el-icon-upload"
                     @click="triggerImageUpload" :loading="uploadingImage">
            Subir imagen
          </el-button>
          <span v-else style="color:#909399;">Sin imagen.</span>
        </div>

        <div v-else class="image-ref-loaded">
          <img :src="referenceImageUrl" :alt="'Imagen de referencia'"
               class="image-ref-thumb" @click="openImagePreview"/>
          <div class="image-ref-actions">
            <el-button v-if="isEditable" size="mini" icon="el-icon-refresh-right"
                       @click="triggerImageUpload" :loading="uploadingImage">Cambiar</el-button>
            <el-button v-if="isEditable" size="mini" type="danger" plain icon="el-icon-delete"
                       @click="deleteReferenceImage" :loading="deletingImage">Quitar</el-button>
            <el-button v-if="isEditable" size="mini" type="success" icon="el-icon-magic-stick"
                       @click="reinterpretWithImage" :loading="reinterpretingImage">
              Re-interpretar con imagen
            </el-button>
            <small style="color:#909399; display:block; margin-top:4px;">
              "Re-interpretar" reemplaza los items actuales con los detectados por el LLM viendo la imagen.
            </small>
          </div>
        </div>

        <input ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif"
               style="display:none;" @change="onImageSelected"/>
      </el-card>

      <!-- Input file compartido para la imagen POR ÍTEM (mismo patrón que la del requerimiento) -->
      <input ref="itemImageInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif"
             style="display:none;" @change="onItemImageSelected"/>

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
      <el-table :data="prequote.items" stripe border size="small" class="mb-2" row-key="id">

        <!-- Fila expandible: detalles técnicos del ítem con badge "Inferido por IA" -->
        <el-table-column type="expand" width="40">
          <template slot-scope="props">
            <div class="ai-inferred-details">
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

              <el-row :gutter="12" type="flex" style="flex-wrap:wrap;" class="detalles-tecnicos-row">
                <el-col :span="8">
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
                </el-col>
                <el-col :span="8">
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
                </el-col>
                <el-col :span="8">
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
                </el-col>
                <el-col :span="6">
                  <div class="ai-field ai-field--with-outside-icon">
                    <label class="lbl-help" title="Cantidad de colores de la impresión (afecta el costo).">Cant. colores <i class="el-icon-info field-tip-icon"></i></label>
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
                </el-col>
                <el-col :span="6">
                  <div class="ai-field ai-field--with-outside-icon">
                    <label class="lbl-help" title="Cantidad de lados o caras a personalizar.">Cant. lados <i class="el-icon-info field-tip-icon"></i></label>
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
                </el-col>
                <el-col :span="6">
                  <div class="ai-field">
                    <label class="lbl-help" title="Factor manual de la impresión (millares). Multiplica el Costo Total de Impresión: costo × colores × lados × millar. Por defecto 1.">Cant. Millar <i class="el-icon-info field-tip-icon"></i></label>
                    <el-input-number v-model="props.row.cantidad_millar" size="mini" :precision="2" :step="1" :min="0"
                                     controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                  </div>
                </el-col>
                <el-col :span="12">
                  <div class="ai-field">
                    <label class="lbl-help" title="Notas internas de esta línea; no se muestran al cliente.">Observaciones <i class="el-icon-info field-tip-icon"></i></label>
                    <el-input v-model="props.row.observaciones" size="mini" :disabled="!isEditable"
                              type="textarea" :autosize="{minRows: 2, maxRows: 6}"
                              placeholder="Notas internas para esta línea (opcional)">
                    </el-input>
                  </div>
                </el-col>
              </el-row>

              <!-- Agosto 2026 (punto 2) — kits y servicio de armado.
                   Un kit son varios productos agrupados solo para esta cotización
                   y el armado es un servicio puntual: ninguno es un producto del
                   catálogo. Marcado, al convertir NO se le crea un producto propio.
                   La línea de la cotización conserva igual este nombre. -->
              <el-row :gutter="12" type="flex" style="flex-wrap:wrap; margin-top:4px;">
                <el-col :span="24">
                  <el-checkbox v-model="props.row.excluir_catalogo" :disabled="!isEditable">
                    No registrar como producto del catálogo
                  </el-checkbox>
                  <el-tooltip placement="top"
                              content="Márcalo en kits y en servicios como el armado: son varios productos o un servicio que existen solo para esta cotización. Así no se crea un producto nuevo en el catálogo. El nombre se sigue mostrando igual en la propuesta.">
                    <i class="el-icon-info field-tip-icon" style="margin-left:4px;"></i>
                  </el-tooltip>
                </el-col>
              </el-row>

              <!-- ───────────── Sección de COSTEO (Fase A) ───────────── -->
              <div class="costeo-section">
                <div class="costeo-section__title">
                  <i class="el-icon-coin"></i> <strong>Costeo del ítem</strong>
                  <span class="costeo-section__hint">Muestra y movilidad se cargan 1 vez por pedido; impresión y empaque pueden ser por unidad. Impresión = costo × colores × lados × millar. El Seguro SCTR normal se mantiene para el Total sin IGV; el Costo Real Total usa una base de costo real propia.</span>
                </div>

                <el-row :gutter="12" type="flex" style="flex-wrap:wrap;">
                  <el-col :span="6">
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
                  </el-col>
                  <el-col :span="6">
                    <div class="ai-field">
                      <label class="lbl-help" title="Costo de movilidad o envío. Se carga una sola vez por pedido.">Costo movilidad (S/) <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.costo_movilidad" size="mini" :precision="2" :step="1" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                    </div>
                  </el-col>
                  <el-col :span="6">
                    <div class="ai-field">
                      <label class="lbl-help" title="Costo de impresión. El Costo Total de Impresión = costo × cant. colores × cant. lados × cantidad por millar. Marca «por unidad» si ese total se cobra por cada unidad del pedido en lugar de una sola vez.">Costo impresión (S/) <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.costo_impresion" size="mini" :precision="2" :step="0.5" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                      <!-- COM-02: el factor "Cant. Millar" se edita arriba, en Detalles técnicos, junto a colores y lados. -->
                      <!-- Agosto 2026 (punto 3): mismo comportamiento que su par de empaque. -->
                      <el-checkbox v-model="props.row.impresion_por_unidad" :disabled="!isEditable"
                                   style="margin-top:2px;">por unidad (× cant.)</el-checkbox>
                    </div>
                  </el-col>
                  <el-col :span="6">
                    <div class="ai-field">
                      <label class="lbl-help" title="Costo de empaque. Puede ser fijo o por unidad.">Costo empaque (S/) <i class="el-icon-info field-tip-icon"></i></label>
                      <el-input-number v-model="props.row.costo_empaque" size="mini" :precision="2" :step="0.5" :min="0"
                                       controls-position="right" :disabled="!isEditable" style="width:100%;"></el-input-number>
                      <el-checkbox v-model="props.row.empaque_por_unidad" :disabled="!isEditable"
                                   style="margin-top:2px;">por unidad (× cant.)</el-checkbox>
                    </div>
                  </el-col>
                </el-row>

                <!-- SCTR por producto (COM-02 unitario, override sobre el % de la cotización) -->
                <el-row :gutter="12" type="flex" style="flex-wrap:wrap; margin-top:6px;">
                  <el-col :span="6">
                    <div class="ai-field">
                      <label class="lbl-help" title="Seguro SCTR de este producto. Si lo dejas vacío, hereda el % de la cotización.">SCTR (%) <i class="el-icon-info field-tip-icon"></i></label>
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
                  </el-col>
                </el-row>

                <!-- Mini-desglose en vivo del costeo.
                     El v-for sobre [costeoBreakdown(...)] calcula el desglose
                     UNA sola vez y lo expone como `bd` (truco de Vue 2 para
                     variable local en template). -->
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
              </div>

              <!-- ─────── Fase C: Escalones de precio por volumen ─────── -->
              <div v-if="props.row.precios_volumen_disponibles && props.row.precios_volumen_disponibles.length > 0"
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

        <el-table-column prop="item_order" label="#" width="40" align="center"></el-table-column>

        <el-table-column label="Producto solicitado" min-width="120">
          <template slot-scope="scope">
            <el-input v-model="scope.row.product_name" size="mini" :disabled="!isEditable"></el-input>
          </template>
        </el-table-column>

        <!-- Columna NUEVA: foto editable POR ÍTEM (multimodal). Mismo patrón que la imagen del requerimiento. -->
        <el-table-column label="Foto" width="92" align="center">
          <template slot="header">
            <span class="col-tip" title="Imagen de referencia de este producto. La IA puede usarla para inferir mejor material, técnica y medidas.">Foto <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <div class="item-foto-cell">
              <img v-if="itemImageUrl(scope.row)" :src="itemImageUrl(scope.row)" alt="foto"
                   class="item-foto-thumb"/>
              <span v-else class="item-foto-empty"><i class="el-icon-picture-outline"></i></span>

              <div v-if="isEditable" class="item-foto-actions">
                <el-button size="mini" type="text" icon="el-icon-upload2"
                           :loading="uploadingItemImageId === scope.row.id"
                           @click="triggerItemImageUpload(scope.row)">
                  {{ itemImageUrl(scope.row) ? 'Cambiar' : 'Subir' }}
                </el-button>
                <el-button v-if="itemImageUrl(scope.row)" size="mini" type="text" class="item-foto-del"
                           icon="el-icon-delete"
                           @click="deleteItemImage(scope.row)">
                  Quitar
                </el-button>
              </div>
            </div>
          </template>
        </el-table-column>

        <!-- Columna NUEVA: producto del catálogo + badge de matching -->
        <el-table-column label="Producto del catálogo" min-width="180">
          <template slot="header">
            <span class="col-tip" title="Producto del catálogo de Compipro asociado a lo que pidió el cliente (sugerido por la IA).">Producto del catálogo <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <div>
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
                <i class="el-icon-search"></i> Sin match — buscar
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
          </template>
        </el-table-column>

        <el-table-column label="Cant." width="110">
          <template slot-scope="scope">
            <el-input-number v-model="scope.row.quantity" size="mini" :min="1" :max="999999"
                             controls-position="right" :disabled="!isEditable" class="campo-precio"></el-input-number>
          </template>
        </el-table-column>
        <el-table-column label="Personalización" width="140">
          <template slot="header">
            <span class="col-tip" title="Detalle de la personalización solicitada (texto, logo, color, etc.).">Personalización <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <el-input v-model="scope.row.personalizacion" size="mini" :disabled="!isEditable" class="campo-descriptivo"></el-input>
          </template>
        </el-table-column>
        <el-table-column label="Técnica" width="120">
          <template slot="header">
            <span class="col-tip" title="Técnica de impresión o aplicación (serigrafía, bordado, sublimado, etc.).">Técnica <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <el-input v-model="scope.row.tecnica_impresion" size="mini" :disabled="!isEditable" class="campo-descriptivo"></el-input>
          </template>
        </el-table-column>
        <el-table-column label="Costo Base" width="145" align="right">
          <template slot="header">
            <span class="col-tip" title="Costo unitario base del producto, antes de aplicar el margen.">Costo Base <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <el-input-number v-model="scope.row.costo_base" size="mini" :precision="2" :step="0.5"
                             controls-position="right" :disabled="!isEditable" class="campo-precio"></el-input-number>
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
        </el-table-column>
        <el-table-column label="Margen %" width="135" align="right">
          <template slot="header">
            <span class="col-tip" title="Porcentaje de ganancia aplicado sobre el costo base.">Margen % <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <el-tooltip
              :disabled="!isUnderMinMargin(scope.row)"
              :content="minMarginTooltip(scope.row)"
              placement="top" effect="dark">
              <el-input-number v-model="scope.row.margen_porcentaje" size="mini" :precision="1" :step="5"
                               :min="0" :max="999" controls-position="right" :disabled="!isEditable"
                               class="campo-precio"
                               :class="{ 'campo-margen-bajo': isUnderMinMargin(scope.row) }">
              </el-input-number>
            </el-tooltip>
            <div v-if="isUnderMinMargin(scope.row)" class="campo-margen-bajo__hint">
              <i class="el-icon-warning"></i> Bajo el mínimo ({{ scope.row.min_margin_aplicable }}%)
            </div>
          </template>
        </el-table-column>
        <el-table-column label="Margen (S/)" width="110" align="right">
          <template slot="header">
            <span class="col-tip" title="Ganancia en soles del ítem (markup sobre el costo × cantidad). Solo lectura.">Margen (S/) <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <span class="campo-calculado campo-margen-soles">S/ {{ parseFloat(scope.row.ganancia_absoluta || 0).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="V. Unit." width="90" align="right">
          <template slot="header">
            <span class="col-tip" title="Valor unitario de venta (costo base + margen), sin IGV.">V. Unit. <i class="el-icon-info"></i></span>
          </template>
          <template slot-scope="scope">
            <span class="campo-calculado">S/ {{ parseFloat(scope.row.valor_unitario).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="Total" width="100" align="right">
          <template slot-scope="scope">
            <span class="campo-calculado"><strong>S/ {{ parseFloat(scope.row.total_item).toFixed(2) }}</strong></span>
          </template>
        </el-table-column>
        <el-table-column label="" width="128" align="center">
          <template slot-scope="scope">
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
    <el-dialog :title="matchDialogTitle" :visible.sync="matchDialogVisible" width="700px"
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

        <!-- Búsqueda manual -->
        <div style="margin-bottom:8px;">
          <strong>Buscar otro producto en el catálogo:</strong>
        </div>
        <el-autocomplete
          v-model="manualQuery"
          :fetch-suggestions="fetchManualResults"
          placeholder="Escribe nombre o descripción para buscar (mínimo 2 caracteres)"
          style="width:100%;"
          @select="onManualSelect"
          :debounce="350"
          popper-class="product-match-autocomplete">
          <template slot-scope="{ item }">
            <div style="line-height:1.3;">
              <strong>{{ item.name }}</strong>
              <span style="float:right; font-size:11px; color:#909399;">
                {{ Math.round((parseFloat(item.score) || 0) * 100) }}% · S/ {{ parseFloat(item.sale_unit_price || 0).toFixed(2) }}
              </span>
              <div v-if="item.description" style="font-size:11px; color:#909399;">
                {{ truncate(item.description, 80) }}
              </div>
            </div>
          </template>
        </el-autocomplete>
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
      // Match dialog
      matchDialogVisible: false,
      matchTarget: null,        // PreQuoteItem actual
      matchSuggestions: [],     // Lista de candidatos del catálogo
      manualQuery: '',
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
    productosSinProveedor() {
      if (!this.prequote || !this.prequote.items) return 0
      return this.prequote.items.filter(function (it) {
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
    }
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

    async saveItem(item) {
      try {
        var response = await this.$http.put(
          '/marketing/quotation/prequotes/' + this.prequote.id + '/item/' + item.id,
          {
            product_name: item.product_name,
            quantity: item.quantity,
            personalizacion: item.personalizacion,
            tecnica_impresion: item.tecnica_impresion,
            // Detalles técnicos editables desde la fila expandible (badge "Inferido por IA")
            material: item.material,
            medida: item.medida,
            empaque: item.empaque,
            cantidad_colores: item.cantidad_colores,
            cantidad_lados: item.cantidad_lados,
            observaciones: item.observaciones,
            costo_base: item.costo_base,
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

    precioMuestraEfectivo(item) {
      if (!item) return 0
      var propio = item.precio_muestra
      if (propio !== null && propio !== undefined && propio !== '') {
        var n = parseFloat(propio)
        if (!isNaN(n)) return n
      }
      return parseFloat(item.costo_base) || 0
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
          this.$set(item, 'precio_muestra', parseFloat(item.costo_base) || 0)
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
    costeoBreakdown(item) {
      if (!item) return null
      var q = parseInt(item.quantity) || 1
      if (q < 1) q = 1
      var costoBase = parseFloat(item.costo_base) || 0
      var markup    = parseFloat(item.margen_porcentaje) || 0
      // Agosto 2026 (punto 4): N° muestras × precio de la muestra. Cae al costo
      // base cuando el ítem no tiene un precio propio (comportamiento histórico).
      var muestra   = (parseInt(item.numero_muestras) || 0) * this.precioMuestraEfectivo(item)
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
      this.matchTarget = item
      this.matchSuggestions = Array.isArray(item.product_suggestions) ? item.product_suggestions : []
      this.manualQuery = ''
      this.actingSuggestionId = null
      this.matchDialogVisible = true
    },

    onMatchDialogClose() {
      this.matchTarget = null
      this.matchSuggestions = []
      this.manualQuery = ''
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
     * Re-ejecutar matching automático (después de editar product_name).
     */
    async rematchWithAI() {
      if (!this.matchTarget) return
      this.rematchLoading = true
      try {
        var resp = await this.$http.post(
          '/marketing/quotation/prequote-items/' + this.matchTarget.id + '/rematch'
        )
        if (resp.data && resp.data.ok) {
          this.$message.success('Matching re-ejecutado')
          this.updateLocalItem(resp.data.item)
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
     * Búsqueda manual en el catálogo (debounced via el-autocomplete).
     */
    fetchManualResults(query, cb) {
      query = (query || '').trim()
      if (query.length < 2) { cb([]); return }
      this.$http.get('/marketing/quotation/products/search', {
        params: { q: query, limit: 15 }
      }).then(function (resp) {
        cb(resp.data && resp.data.results ? resp.data.results : [])
      }).catch(function () {
        cb([])
      })
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
  /* Sticky: queda pegado al borde izquierdo del viewport visible aunque
   * la tabla padre tenga scroll horizontal. */
  position: sticky;
  left: 12px;
  /* Ancho controlado: ocupa hasta ~100vw menos el espacio del sidebar
   * lateral de Bitácora (~360px). Si no hay sidebar visible, el max-width
   * lo frena en un ancho razonable. min-width asegura legibilidad. */
  width: calc(100vw - 360px);
  max-width: 1100px;
  min-width: 680px;
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
.ai-inferred-input.el-input-number .el-input__inner {
  background-color: #fffbf0 !important;
  border-color: #f5c842 !important;
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

/* ⚪ Descriptivo — tabla principal (Personalización, Técnica) + detalles técnicos */
.campo-descriptivo .el-input__inner,
.detalles-tecnicos-row .el-input__inner,
.detalles-tecnicos-row .el-textarea__inner {
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
