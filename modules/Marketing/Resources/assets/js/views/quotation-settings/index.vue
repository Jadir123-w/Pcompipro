<template>
  <div class="quotation-settings">
    <div class="qs-header">
      <div>
        <h2 class="qs-title">💼 Configuración del Cotizador (COM-02)</h2>
        <p class="qs-subtitle">Datos de empresa, numeración, plantillas, condiciones y reglas de margen que el agente usa al generar cotizaciones.</p>
      </div>
    </div>

    <el-tabs v-model="activeTab" type="border-card" v-loading="loading">

      <!-- ════════════════════════════════════════════════════════════ -->
      <!-- 1. Empresa                                                    -->
      <!-- ════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="empresa">
        <span slot="label"><i class="el-icon-office-building"></i> Empresa</span>

        <el-form label-position="top" size="small" class="qs-form">
          <p class="qs-tip">Datos que aparecen en la cabecera de cada cotización emitida.</p>

          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="Razón social">
                <el-input v-model="cfg.empresa_razon_social"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="RUC">
                <el-input v-model="cfg.empresa_ruc" maxlength="11"></el-input>
              </el-form-item>
            </el-col>
          </el-row>

          <el-form-item label="Dirección fiscal">
            <el-input type="textarea" v-model="cfg.empresa_direccion" :rows="2"></el-input>
          </el-form-item>

          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="Teléfono(s)">
                <el-input v-model="cfg.empresa_telefono"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="Email institucional">
                <el-input v-model="cfg.empresa_email_info" type="email"></el-input>
              </el-form-item>
            </el-col>
          </el-row>

          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="Web principal">
                <el-input v-model="cfg.empresa_web_principal" placeholder="www.compipro.com"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="12">
              <el-form-item label="Facebook">
                <el-input v-model="cfg.empresa_facebook" placeholder="COMPIPRO"></el-input>
              </el-form-item>
            </el-col>
          </el-row>

          <el-form-item label="URL del catálogo público (PDF)">
            <el-input v-model="cfg.empresa_catalogo_url" placeholder="https://compipro.com/catalogo.pdf"></el-input>
          </el-form-item>

          <el-divider></el-divider>

          <el-form-item label="Correo interno para pruebas">
            <el-input v-model="cfg.correo_prueba_interno" type="email"
                      placeholder="interno.pruebas@compipro.net"></el-input>
            <small class="qs-hint">
              Dirección a la que llega la propuesta al pulsar "Enviar correo de prueba" en la Cotización Final.
              Sirve para revisar cómo le llegará al cliente antes de emitir. No afecta el envío real al cliente.
            </small>
          </el-form-item>

          <div class="qs-actions">
            <el-button type="primary" icon="el-icon-check" @click="saveTab('empresa')" :loading="saving.empresa">Guardar empresa</el-button>
          </div>
        </el-form>
      </el-tab-pane>

      <!-- ════════════════════════════════════════════════════════════ -->
      <!-- 2. Numeración                                                  -->
      <!-- ════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="numeracion">
        <span slot="label"><i class="el-icon-document"></i> Numeración</span>

        <el-form label-position="top" size="small" class="qs-form">
          <p class="qs-tip">Cómo se numeran y rotulan las cotizaciones que envía el agente.</p>

          <el-row :gutter="16">
            <el-col :span="12">
              <el-form-item label="Prefijo default">
                <el-input v-model="cfg.cotizacion_prefijo_default" maxlength="5" style="width: 200px;"></el-input>
                <small class="qs-hint">Ej: MAP. Aparece al inicio del número de cada cotización.</small>
              </el-form-item>
            </el-col>
          </el-row>

          <el-form-item label="Prefijos permitidos">
            <el-select v-model="prefijosArr" multiple filterable allow-create default-first-option
                       placeholder="Agregá los códigos de prefijo válidos (Enter para agregar)" style="width: 100%;">
              <el-option v-for="p in prefijosArr" :key="p" :label="p" :value="p"></el-option>
            </el-select>
            <small class="qs-hint">Lista de prefijos que el agente puede asignar a una cotización. Ej: MAP, SOL, ECO.</small>
          </el-form-item>

          <div class="qs-actions">
            <el-button type="primary" icon="el-icon-check" @click="saveTab('numeracion')" :loading="saving.numeracion">Guardar numeración</el-button>
          </div>
        </el-form>
      </el-tab-pane>

      <!-- ════════════════════════════════════════════════════════════ -->
      <!-- 3. Precios y envío                                             -->
      <!-- ════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="precios">
        <span slot="label"><i class="el-icon-money"></i> Precios y envío</span>

        <el-form label-position="top" size="small" class="qs-form">
          <p class="qs-tip">Parámetros usados al calcular costos en cada cotización.</p>

          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="IGV (%)">
                <el-input-number v-model.number="cfgNumeric.igv_percentage" :min="0" :max="100" :step="1" style="width: 100%;"></el-input-number>
                <small class="qs-hint">% por defecto en Perú: 18</small>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="Moneda">
                <el-select v-model="cfg.currency_type_id" style="width: 100%;">
                  <el-option label="PEN — Soles" value="PEN"></el-option>
                  <el-option label="USD — Dólares" value="USD"></el-option>
                </el-select>
              </el-form-item>
            </el-col>
          </el-row>

          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="Umbral envío gratis Lima (S/)">
                <el-input-number v-model.number="cfgNumeric.cotizacion_envio_umbral_lima" :min="0" :step="50" style="width: 100%;"></el-input-number>
                <small class="qs-hint">Subtotal mínimo para envío gratis dentro de Lima</small>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="Costo envío adicional (S/)">
                <el-input-number v-model.number="cfgNumeric.cotizacion_envio_adicional" :min="0" :step="5" style="width: 100%;"></el-input-number>
                <small class="qs-hint">Costo cuando el subtotal está por debajo del umbral</small>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="Margen default (%)">
                <el-input-number v-model.number="cfgNumeric.default_margin_percentage" :min="0" :max="100" :step="5" style="width: 100%;"></el-input-number>
                <small class="qs-hint">Cuando no hay regla específica para producto/categoría</small>
              </el-form-item>
            </el-col>
          </el-row>

          <div class="qs-actions">
            <el-button type="primary" icon="el-icon-check" @click="saveTab('precios')" :loading="saving.precios">Guardar precios</el-button>
          </div>
        </el-form>
      </el-tab-pane>

      <!-- ════════════════════════════════════════════════════════════ -->
      <!-- 4. Plantillas                                                  -->
      <!-- ════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="plantillas">
        <span slot="label"><i class="el-icon-edit-outline"></i> Plantillas</span>

        <el-form label-position="top" size="small" class="qs-form">
          <p class="qs-tip">Textos genéricos que el agente inserta en cada cotización emitida.</p>

          <el-form-item label="Saludo inicial">
            <el-input type="textarea" v-model="cfg.plantilla_saludo" :rows="3"
                      placeholder="Me es grato dirigirme a Ud. ..."></el-input>
            <small class="qs-hint">Aparece como párrafo de apertura en el PDF de la cotización</small>
          </el-form-item>

          <el-form-item label="Despedida">
            <el-input type="textarea" v-model="cfg.plantilla_despedida" :rows="3"
                      placeholder="Esperando que esta propuesta sea de su agrado ..."></el-input>
            <small class="qs-hint">Aparece como párrafo de cierre antes de la firma</small>
          </el-form-item>

          <el-form-item label="Validez de la oferta (texto)">
            <el-input type="textarea" v-model="cfg.cotizacion_validez_default" :rows="3" :autosize="{ minRows: 2, maxRows: 8 }"
                      placeholder="&gt; **5 a 7 días útiles**, después de ese tiempo…"></el-input>
            <small class="qs-hint">
              Texto de la sección "Validez de la oferta" del PDF. Mismo formato que las Condiciones:
              una línea = una viñeta, <code>&gt;</code> al inicio = párrafo, <code>**negrita**</code>.
            </small>
          </el-form-item>

          <div class="qs-actions">
            <el-button type="primary" icon="el-icon-check" @click="saveTab('plantillas')" :loading="saving.plantillas">Guardar plantillas</el-button>
          </div>
        </el-form>
      </el-tab-pane>

      <!-- ════════════════════════════════════════════════════════════ -->
      <!-- 5. Condiciones default                                         -->
      <!-- ════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="condiciones">
        <span slot="label"><i class="el-icon-document-checked"></i> Condiciones</span>

        <el-form label-position="top" size="small" class="qs-form">
          <p class="qs-tip">Textos por defecto de las secciones de "Términos y Condiciones Generales" del PDF. La IA decide por cotización qué secciones aparecen; el texto sale siempre de aquí (la ejecutiva puede editarlo o borrarlo por cotización).</p>

          <p class="qs-tip" style="border-left-color:#67c23a; background:#f4fbf4;">
            <strong>Formato de texto:</strong> cada línea es una <strong>viñeta</strong>.
            Escribe <code>&gt;</code> y un espacio al inicio de una línea para un
            <strong>párrafo sin viñeta</strong> (intros y notas). Envuelve texto en
            <code>**dobles asteriscos**</code> para ponerlo en <strong>negrita</strong>.
            Deja una sección vacía para <strong>ocultarla</strong> en el PDF.
          </p>

          <el-form-item label="Costo del servicio">
            <el-input type="textarea" v-model="cfg.condiciones_default_costo_servicio" :rows="4" :autosize="{ minRows: 3, maxRows: 12 }"></el-input>
          </el-form-item>

          <el-form-item label="Forma de pago">
            <el-input type="textarea" v-model="cfg.condiciones_default_forma_pago" :rows="5" :autosize="{ minRows: 3, maxRows: 14 }"></el-input>
          </el-form-item>

          <el-form-item label="Tiempo de ejecución">
            <el-input type="textarea" v-model="cfg.condiciones_default_tiempo_ejecucion" :rows="5" :autosize="{ minRows: 3, maxRows: 14 }"></el-input>
          </el-form-item>

          <el-form-item label="Consideraciones de producción">
            <el-input type="textarea" v-model="cfg.condiciones_default_consideraciones_produccion" :rows="5" :autosize="{ minRows: 3, maxRows: 14 }"></el-input>
          </el-form-item>

          <el-form-item label="Texto de moneda">
            <el-input type="textarea" v-model="cfg.condiciones_default_moneda_texto" :rows="2" :autosize="{ minRows: 2, maxRows: 8 }"
                      placeholder="Todos los precios son expresados en Soles."></el-input>
          </el-form-item>

          <el-form-item label="Consideraciones para entrega de producto">
            <el-input type="textarea" v-model="cfg.condiciones_default_consideraciones_entrega" :rows="6" :autosize="{ minRows: 3, maxRows: 16 }"></el-input>
          </el-form-item>

          <el-form-item label="Términos para servicio instalación">
            <el-input type="textarea" v-model="cfg.condiciones_default_terminos_instalacion" :rows="6" :autosize="{ minRows: 3, maxRows: 16 }"></el-input>
          </el-form-item>

          <el-form-item label="Garantía del servicio">
            <el-input type="textarea" v-model="cfg.condiciones_default_garantia_servicio" :rows="2" :autosize="{ minRows: 2, maxRows: 8 }"></el-input>
          </el-form-item>

          <el-form-item label="Servicio complementario (SCTR, etc.)">
            <el-input type="textarea" v-model="cfg.condiciones_default_servicio_complementario" :rows="2" :autosize="{ minRows: 2, maxRows: 8 }"></el-input>
          </el-form-item>

          <el-form-item label="Especificación del servicio UPCYCLING">
            <el-input type="textarea" v-model="cfg.condiciones_default_especificacion_upcycling" :rows="6" :autosize="{ minRows: 3, maxRows: 16 }"></el-input>
          </el-form-item>

          <div class="qs-actions">
            <el-button type="primary" icon="el-icon-check" @click="saveTab('condiciones')" :loading="saving.condiciones">Guardar condiciones</el-button>
          </div>
        </el-form>
      </el-tab-pane>

      <!-- ════════════════════════════════════════════════════════════ -->
      <!-- 6. Defaults Operativos (S3 — sec 21 doc funcional)            -->
      <!-- ════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="defaults">
        <span slot="label"><i class="el-icon-magic-stick"></i> Defaults Operativos</span>

        <p class="qs-tip">
          Valores que el agente de IA usa para inferir datos faltantes en cada ítem
          (sec. 21 del Documento Funcional). Si el LLM no infiere material, técnica,
          empaque o tiempo desde el texto del requerimiento, usa estos valores como
          punto de partida según la categoría detectada del producto.
        </p>

        <el-form label-position="top" size="small" class="qs-form">

          <!-- Tiempos base por categoría -->
          <h4 class="qs-section-title">⏱ Tiempos base de producción (días hábiles)</h4>
          <p class="qs-section-hint">Default sugerido al ítem cuando el LLM no infiere un tiempo específico.</p>
          <el-row :gutter="16">
            <el-col :span="8" :sm="8" :md="4">
              <el-form-item label="Textil">
                <el-input-number v-model.number="cfgNumeric.defop_tiempo_textil_dias" :min="1" :max="120" :step="1" controls-position="right" style="width:100%;"></el-input-number>
              </el-form-item>
            </el-col>
            <el-col :span="8" :sm="8" :md="4">
              <el-form-item label="Impresos">
                <el-input-number v-model.number="cfgNumeric.defop_tiempo_impresos_dias" :min="1" :max="120" :step="1" controls-position="right" style="width:100%;"></el-input-number>
              </el-form-item>
            </el-col>
            <el-col :span="8" :sm="8" :md="4">
              <el-form-item label="Ecológicos">
                <el-input-number v-model.number="cfgNumeric.defop_tiempo_eco_dias" :min="1" :max="120" :step="1" controls-position="right" style="width:100%;"></el-input-number>
              </el-form-item>
            </el-col>
            <el-col :span="8" :sm="8" :md="4">
              <el-form-item label="Packs">
                <el-input-number v-model.number="cfgNumeric.defop_tiempo_packs_dias" :min="1" :max="120" :step="1" controls-position="right" style="width:100%;"></el-input-number>
              </el-form-item>
            </el-col>
            <el-col :span="8" :sm="8" :md="4">
              <el-form-item label="Otros">
                <el-input-number v-model.number="cfgNumeric.defop_tiempo_otros_dias" :min="1" :max="120" :step="1" controls-position="right" style="width:100%;"></el-input-number>
              </el-form-item>
            </el-col>
          </el-row>

          <el-divider></el-divider>

          <!-- Técnicas sugeridas por categoría -->
          <h4 class="qs-section-title">🎨 Técnicas de personalización por categoría</h4>
          <p class="qs-section-hint">El LLM sugiere esta técnica cuando el cliente no la especifica.</p>
          <el-row :gutter="16">
            <el-col :span="12" :md="6">
              <el-form-item label="Textil">
                <el-input v-model="cfg.defop_tecnica_textil" placeholder="bordado, sublimación…"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="12" :md="6">
              <el-form-item label="Impresos">
                <el-input v-model="cfg.defop_tecnica_impresos" placeholder="full color, offset…"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="12" :md="6">
              <el-form-item label="Ecológicos">
                <el-input v-model="cfg.defop_tecnica_eco" placeholder="serigrafía, grabado láser…"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="12" :md="6">
              <el-form-item label="Packs">
                <el-input v-model="cfg.defop_tecnica_packs" placeholder="serigrafía, etiqueta…"></el-input>
              </el-form-item>
            </el-col>
          </el-row>

          <el-divider></el-divider>

          <!-- Empaques sugeridos por categoría -->
          <h4 class="qs-section-title">📦 Empaques sugeridos por categoría</h4>
          <p class="qs-section-hint">Empaque default que el sistema propone al ítem.</p>
          <el-row :gutter="16">
            <el-col :span="12" :md="6">
              <el-form-item label="Textil">
                <el-input v-model="cfg.defop_empaque_textil" placeholder="bolsa individual"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="12" :md="6">
              <el-form-item label="Impresos">
                <el-input v-model="cfg.defop_empaque_impresos" placeholder="caja x 50 unidades"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="12" :md="6">
              <el-form-item label="Ecológicos">
                <el-input v-model="cfg.defop_empaque_eco" placeholder="bolsa kraft individual"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="12" :md="6">
              <el-form-item label="Packs">
                <el-input v-model="cfg.defop_empaque_packs" placeholder="caja personalizada"></el-input>
              </el-form-item>
            </el-col>
          </el-row>

          <el-divider></el-divider>

          <!-- Reglas de inferencia adicionales -->
          <h4 class="qs-section-title">🧩 Reglas de inferencia generales</h4>
          <p class="qs-section-hint">Valores aplicados a TODO ítem cuando el LLM no infiere algo más específico.</p>
          <el-row :gutter="16">
            <el-col :span="12" :md="8">
              <el-form-item label="Material por defecto">
                <el-input v-model="cfg.defop_default_material" placeholder="estándar"></el-input>
              </el-form-item>
            </el-col>
            <el-col :span="12" :md="8">
              <el-form-item label="Unidad de medida default">
                <el-input v-model="cfg.defop_default_unidad" placeholder="UND, KG, M2…"></el-input>
              </el-form-item>
            </el-col>
          </el-row>

          <el-divider></el-divider>

          <!-- Histórico como contexto -->
          <h4 class="qs-section-title">🗂 Histórico como referencia para inferencia</h4>
          <p class="qs-section-hint">
            El AIEngine puede usar cotizaciones previas similares como contexto para sugerir proveedor,
            costo y tiempo más realistas. Cuando esté activo, se inyectan al prompt junto con los catálogos.
          </p>
          <el-row :gutter="16">
            <el-col :span="8">
              <el-form-item label="¿Inyectar histórico al prompt?">
                <el-switch v-model="cfgBool.defop_historico_enabled"
                           active-text="Sí"
                           inactive-text="No"></el-switch>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="Meses hacia atrás">
                <el-input-number v-model.number="cfgNumeric.defop_historico_meses_lookback" :min="1" :max="60" :step="1" controls-position="right" style="width:100%;"></el-input-number>
                <small class="qs-hint">Cuántos meses de cotizaciones previas se consultan</small>
              </el-form-item>
            </el-col>
            <el-col :span="8">
              <el-form-item label="Score mínimo (0–1)">
                <el-input-number v-model.number="cfgNumeric.defop_historico_min_score" :min="0" :max="1" :step="0.05" :precision="2" controls-position="right" style="width:100%;"></el-input-number>
                <small class="qs-hint">Similitud mínima para considerar histórico relevante</small>
              </el-form-item>
            </el-col>
          </el-row>

          <div class="qs-actions">
            <el-button type="primary" icon="el-icon-check" @click="saveTab('defaults')" :loading="saving.defaults">Guardar defaults</el-button>
          </div>
        </el-form>
      </el-tab-pane>

      <!-- ════════════════════════════════════════════════════════════ -->
      <!-- 7. Sugerencias del sistema (S4 — sec 30-31 Doc Funcional)    -->
      <!-- ════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="suggestions">
        <span slot="label">
          <i class="el-icon-magic-stick"></i> Sugerencias
          <el-badge v-if="suggestionsPendingCount > 0" :value="suggestionsPendingCount"
                    type="warning" style="margin-left:6px;"></el-badge>
        </span>

        <p class="qs-tip">
          El sistema analiza el histórico de cotizaciones validadas y propone ajustes a
          los defaults operativos cuando detecta patrones recurrentes.  Revisá cada
          sugerencia y decidí aplicar, rechazar o postergar.
        </p>

        <div style="margin-bottom: 12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
          <el-radio-group v-model="suggestionsFilter" size="small" @change="loadSuggestions">
            <el-radio-button label="pending_effective">Pendientes</el-radio-button>
            <el-radio-button label="applied">Aplicadas</el-radio-button>
            <el-radio-button label="rejected">Rechazadas</el-radio-button>
            <el-radio-button label="snoozed">Pospuestas</el-radio-button>
            <el-radio-button label="all">Todas</el-radio-button>
          </el-radio-group>
          <el-button type="primary" size="small" icon="el-icon-magic-stick"
                     @click="analyzeSuggestionsNow" :loading="analyzingNow">
            Analizar patrones ahora
          </el-button>
        </div>

        <el-table :data="suggestions" border size="small" v-loading="loadingSuggestions"
                  empty-text="Sin sugerencias para este filtro.">
          <el-table-column label="Tipo" width="170">
            <template slot-scope="s">
              <el-tag size="mini" :type="suggestionTypeTag(s.row.type)">
                {{ suggestionTypeLabel(s.row.type) }}
              </el-tag>
              <div v-if="s.row.category" style="font-size:11px; color:#909399; margin-top:2px;">
                {{ s.row.category }}
              </div>
            </template>
          </el-table-column>
          <el-table-column label="Sugerencia" min-width="300">
            <template slot-scope="s">
              <div style="font-size:12px; color:#606266; line-height:1.5;">
                {{ s.row.reasoning }}
              </div>
              <div style="margin-top:4px; font-size:13px;">
                <code style="background:#f4f4f5; padding:2px 6px; border-radius:3px;">{{ s.row.target_config_key }}</code>:
                <span style="color:#f56c6c; text-decoration:line-through;">{{ s.row.current_value }}</span>
                <i class="el-icon-right" style="margin:0 4px; color:#909399;"></i>
                <strong style="color:#67c23a;">{{ s.row.suggested_value }}</strong>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="Evidencia" width="100" align="center">
            <template slot-scope="s">
              <strong>{{ s.row.evidence_count }}</strong>
              <div style="font-size:10px; color:#909399;">cotizaciones</div>
            </template>
          </el-table-column>
          <el-table-column label="Confianza" width="110" align="center">
            <template slot-scope="s">
              <el-tag :type="confidenceTag(s.row.confidence)" size="mini" effect="plain">
                {{ Math.round((parseFloat(s.row.confidence) || 0) * 100) }}%
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="Estado" width="120" align="center">
            <template slot-scope="s">
              <el-tag size="mini" :type="suggestionStatusTag(s.row.status)" effect="plain">
                {{ suggestionStatusLabel(s.row) }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="Acciones" width="220" align="center">
            <template slot-scope="s">
              <template v-if="!isSuggestionTerminal(s.row.status) && isPendingEffective(s.row)">
                <el-button size="mini" type="success" icon="el-icon-check"
                           :loading="actingSuggestionRow === s.row.id"
                           @click="applySuggestion(s.row)">Aplicar</el-button>
                <el-button size="mini" icon="el-icon-time"
                           :loading="actingSuggestionRow === s.row.id"
                           @click="snoozeSuggestion(s.row)">30d</el-button>
                <el-button size="mini" type="danger" plain icon="el-icon-close"
                           :loading="actingSuggestionRow === s.row.id"
                           @click="rejectSuggestion(s.row)">Rechazar</el-button>
              </template>
              <span v-else-if="s.row.status === 'applied'" style="font-size:11px; color:#909399;">
                <i class="el-icon-success" style="color:#67c23a;"></i>
                Aplicada
              </span>
              <span v-else-if="s.row.status === 'rejected'" style="font-size:11px; color:#909399;">
                <i class="el-icon-close" style="color:#f56c6c;"></i>
                Rechazada
                <el-tooltip v-if="s.row.rejection_reason" :content="s.row.rejection_reason">
                  <i class="el-icon-info" style="color:#909399;"></i>
                </el-tooltip>
              </span>
              <span v-else-if="s.row.status === 'snoozed'" style="font-size:11px; color:#e6a23c;">
                <i class="el-icon-time"></i>
                Hasta {{ formatDate(s.row.snoozed_until) }}
              </span>
            </template>
          </el-table-column>
        </el-table>

        <!-- Dialog rechazar con razón -->
        <el-dialog title="Rechazar sugerencia" :visible.sync="rejectDialogVisible"
                   width="440px" custom-class="qs-margin-dialog" append-to-body>
          <p style="color:#909399; font-size:13px; margin-top:0;">
            La sugerencia no se aplicará. El motivo es opcional, queda como auditoría.
          </p>
          <el-input v-model="rejectReason" placeholder="Motivo (opcional)" :maxlength="200" show-word-limit></el-input>
          <div slot="footer">
            <el-button @click="rejectDialogVisible = false">Cancelar</el-button>
            <el-button type="danger" icon="el-icon-close"
                       :loading="actingSuggestionRow === (rejectTargetId)"
                       @click="submitReject">Rechazar</el-button>
          </div>
        </el-dialog>
      </el-tab-pane>

      <!-- ════════════════════════════════════════════════════════════ -->
      <!-- 8. Reglas de margen                                            -->
      <!-- ════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="margen">
        <span slot="label"><i class="el-icon-data-line"></i> Reglas de margen</span>

        <p class="qs-tip">Reglas que el cotizador aplica al calcular precio de venta sobre el costo. Si una cotización no matchea ninguna regla, usa el margen default ({{ cfgNumeric.default_margin_percentage }}%).</p>

        <div style="margin-bottom: 12px; text-align: right;">
          <el-button type="primary" size="small" icon="el-icon-plus" @click="openMarginForm()">Nueva regla</el-button>
        </div>

        <el-table :data="margins" border size="mini" v-loading="loadingMargins" empty-text="Sin reglas. Usa el botón para crear una.">
          <el-table-column label="Categoría" width="140">
            <template slot-scope="scope">
              <el-tag size="mini" type="info">{{ scope.row.category || '—' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="Producto" min-width="160">
            <template slot-scope="scope">
              {{ scope.row.product_name || '(toda la categoría)' }}
            </template>
          </el-table-column>
          <el-table-column label="Tramo de cantidad" width="180" align="center">
            <template slot-scope="scope">
              {{ scope.row.quantity_from }} – {{ scope.row.quantity_to || '∞' }}
            </template>
          </el-table-column>
          <el-table-column label="Margen" width="100" align="right">
            <template slot-scope="scope">
              <strong>{{ scope.row.margin_percentage }}%</strong>
            </template>
          </el-table-column>
          <el-table-column label="Mínimo" width="100" align="right">
            <template slot-scope="scope">
              <el-tooltip
                v-if="scope.row.min_margin_percentage !== null && scope.row.min_margin_percentage !== undefined"
                content="Si la ejecutiva ingresa un margen menor a este, se muestra una alerta visual (no bloquea el guardado)." placement="top">
                <span style="color:#e6a23c;">⚠ {{ scope.row.min_margin_percentage }}%</span>
              </el-tooltip>
              <span v-else style="color:#c0c4cc;">—</span>
            </template>
          </el-table-column>
          <el-table-column label="Activo" width="80" align="center">
            <template slot-scope="scope">
              <el-switch v-model="scope.row.is_active" @change="toggleMargin(scope.row)"></el-switch>
            </template>
          </el-table-column>
          <el-table-column label="Acciones" width="140" align="center">
            <template slot-scope="scope">
              <el-button size="mini" icon="el-icon-edit" @click="openMarginForm(scope.row)"></el-button>
              <el-button size="mini" type="danger" icon="el-icon-delete" @click="confirmDeleteMargin(scope.row)"></el-button>
            </template>
          </el-table-column>
        </el-table>

        <!-- Dialog crear/editar regla de margen -->
        <el-dialog :title="marginForm.id ? 'Editar regla' : 'Nueva regla de margen'" :visible.sync="marginDialogVisible"
                   width="540px" custom-class="qs-margin-dialog" append-to-body>
          <el-form label-position="top" size="small" v-loading="savingMargin">
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item label="Categoría" required>
                  <el-input v-model="marginForm.category" placeholder="Textil, Impresos, Eco…"></el-input>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="Producto (opcional)">
                  <el-input v-model="marginForm.product_name" placeholder="Vacío = toda la categoría"></el-input>
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="16">
              <el-col :span="8">
                <el-form-item label="Cantidad desde" required>
                  <el-input-number v-model.number="marginForm.quantity_from" :min="1" :step="50" style="width: 100%;"></el-input-number>
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="Cantidad hasta">
                  <el-input-number v-model.number="marginForm.quantity_to" :min="1" :step="50" style="width: 100%;"></el-input-number>
                  <small class="qs-hint">Vacío = sin límite superior (∞)</small>
                </el-form-item>
              </el-col>
              <el-col :span="8">
                <el-form-item label="Margen %" required>
                  <el-input-number v-model.number="marginForm.margin_percentage" :min="0" :max="500" :step="5" style="width: 100%;"></el-input-number>
                </el-form-item>
              </el-col>
            </el-row>

            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item>
                  <span slot="label">
                    Margen mínimo % (opcional)
                    <el-tooltip
                      content="Si la ejecutiva ingresa un margen por debajo de este valor al editar un ítem, el sistema muestra una alerta visual. NO bloquea el guardado — solo advierte. Deja vacío si no quieres alerta para esta regla."
                      placement="top">
                      <i class="el-icon-question" style="color:#909399; margin-left:4px;"></i>
                    </el-tooltip>
                  </span>
                  <el-input-number
                    v-model.number="marginForm.min_margin_percentage"
                    :min="0" :max="500" :step="5"
                    placeholder="Sin alerta"
                    :controls="true"
                    style="width: 100%;"></el-input-number>
                  <small class="qs-hint" v-if="marginMinExceedsMargin">
                    <span style="color:#f56c6c;">⚠ El mínimo no puede ser mayor al margen sugerido.</span>
                  </small>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="Activa">
                  <el-switch v-model="marginForm.is_active"></el-switch>
                </el-form-item>
              </el-col>
            </el-row>
          </el-form>

          <div slot="footer">
            <el-button @click="marginDialogVisible = false">Cancelar</el-button>
            <el-button type="primary" @click="saveMargin" :loading="savingMargin" icon="el-icon-check">
              {{ marginForm.id ? 'Guardar cambios' : 'Crear regla' }}
            </el-button>
          </div>
        </el-dialog>
      </el-tab-pane>

    </el-tabs>
  </div>
</template>

<script>
const TAB_FIELDS = {
  empresa:    ['empresa_razon_social','empresa_ruc','empresa_direccion','empresa_telefono','empresa_email_info','empresa_web_principal','empresa_facebook','empresa_catalogo_url','correo_prueba_interno'],
  numeracion: ['cotizacion_prefijo_default','cotizacion_prefijos_permitidos'],
  precios:    ['igv_percentage','currency_type_id','cotizacion_envio_umbral_lima','cotizacion_envio_adicional','default_margin_percentage'],
  plantillas: ['plantilla_saludo','plantilla_despedida','cotizacion_validez_default'],
  condiciones:['condiciones_default_costo_servicio','condiciones_default_forma_pago','condiciones_default_tiempo_ejecucion','condiciones_default_consideraciones_produccion','condiciones_default_moneda_texto','condiciones_default_consideraciones_entrega','condiciones_default_terminos_instalacion','condiciones_default_garantia_servicio','condiciones_default_servicio_complementario','condiciones_default_especificacion_upcycling'],
  defaults:   [
    'defop_tiempo_textil_dias','defop_tiempo_impresos_dias','defop_tiempo_eco_dias','defop_tiempo_packs_dias','defop_tiempo_otros_dias',
    'defop_tecnica_textil','defop_tecnica_impresos','defop_tecnica_eco','defop_tecnica_packs',
    'defop_empaque_textil','defop_empaque_impresos','defop_empaque_eco','defop_empaque_packs',
    'defop_default_material','defop_default_unidad',
    'defop_historico_enabled','defop_historico_meses_lookback','defop_historico_min_score',
  ],
}

const NUMERIC_KEYS = new Set([
  'igv_percentage', 'cotizacion_envio_umbral_lima', 'cotizacion_envio_adicional', 'default_margin_percentage',
  // Defaults Operativos numéricos
  'defop_tiempo_textil_dias', 'defop_tiempo_impresos_dias', 'defop_tiempo_eco_dias',
  'defop_tiempo_packs_dias', 'defop_tiempo_otros_dias',
  'defop_historico_meses_lookback', 'defop_historico_min_score',
])

const BOOLEAN_KEYS = new Set([
  'defop_historico_enabled',
])

const JSON_ARRAY_KEYS = new Set([
  'cotizacion_prefijos_permitidos',
])

export default {
  data() {
    return {
      activeTab: 'empresa',
      loading: false,
      cfg: {},          // strings
      cfgNumeric: {},   // numéricos parseados
      cfgBool: {},      // booleanos
      saving: { empresa: false, numeracion: false, precios: false, plantillas: false, condiciones: false, defaults: false },

      // Numeración (json array)
      prefijosArr: [],

      // Margen
      margins: [],
      loadingMargins: false,
      marginDialogVisible: false,
      savingMargin: false,
      marginForm: this.emptyMarginForm(),

      // S4 — Sugerencias del sistema
      suggestions: [],
      loadingSuggestions: false,
      suggestionsFilter: 'pending_effective',
      suggestionsPendingCount: 0,
      analyzingNow: false,
      actingSuggestionRow: null,
      rejectDialogVisible: false,
      rejectTargetId: null,
      rejectReason: '',
    }
  },
  created() {
    this.loadConfig()
    this.loadMargins()
    this.loadSuggestionsPendingCount()
  },
  watch: {
    activeTab(newTab) {
      if (newTab === 'suggestions') {
        this.loadSuggestions()
      }
    },
  },
  computed: {
    // Fase B — feedback inline en el dialog de regla de margen.
    // Si el mínimo está definido y es mayor al sugerido, marcamos el error
    // antes de pulsar Guardar (el backend también valida y rechaza con 422).
    marginMinExceedsMargin() {
      var min = this.marginForm.min_margin_percentage
      var sug = this.marginForm.margin_percentage
      if (min === null || min === undefined || min === '') return false
      if (sug === null || sug === undefined || sug === '') return false
      return Number(min) > Number(sug)
    },
  },
  methods: {
    emptyMarginForm() {
      return {
        id: null,
        category: '',
        product_name: '',
        quantity_from: 1,
        quantity_to: null,
        margin_percentage: 30,
        // Fase B — margen mínimo opcional. Null = no dispara alerta visual.
        min_margin_percentage: null,
        is_active: true,
      }
    },

    async loadConfig() {
      this.loading = true
      try {
        const r = await this.$http.get('/marketing/quotation/config')
        const list = r.data.data || r.data.configs || r.data || []
        // El endpoint retorna array de filas; armamos un mapa por config_key
        const cfg = {}
        const cfgNumeric = {}
        const cfgBool = {}
        for (const row of list) {
          const k = row.config_key
          let v = row.config_value
          if (BOOLEAN_KEYS.has(k)) {
            cfgBool[k] = v === '1' || v === 'true' || v === true
          } else if (NUMERIC_KEYS.has(k)) {
            cfgNumeric[k] = v !== null && v !== '' ? parseFloat(v) : 0
          } else if (JSON_ARRAY_KEYS.has(k)) {
            try { cfg[k] = typeof v === 'string' ? JSON.parse(v) : (v || []) } catch (e) { cfg[k] = [] }
          } else {
            cfg[k] = v
          }
        }
        this.cfg = cfg
        this.cfgNumeric = cfgNumeric
        this.cfgBool = cfgBool
        this.prefijosArr = Array.isArray(this.cfg.cotizacion_prefijos_permitidos)
          ? this.cfg.cotizacion_prefijos_permitidos.slice() : []
        // Retro-compat: si "consideraciones de entrega" aún viene como JSON array
        // (config viejo, antes de la migración a markdown-lite), mostrarlo como
        // texto (una línea por item) para no ensuciar el textarea.
        const entrega = this.cfg.condiciones_default_consideraciones_entrega
        if (typeof entrega === 'string' && entrega.trim().charAt(0) === '[') {
          try {
            const arr = JSON.parse(entrega)
            if (Array.isArray(arr)) this.cfg.condiciones_default_consideraciones_entrega = arr.join('\n')
          } catch (e) { /* dejar el valor tal cual */ }
        }
      } catch (e) {
        this.$message.error('Error cargando configuración: ' + (e.message || ''))
      } finally {
        this.loading = false
      }
    },

    async saveTab(tab) {
      this.$set(this.saving, tab, true)
      try {
        const fields = TAB_FIELDS[tab] || []
        // Sincronizar arrays (Numeración)
        if (tab === 'numeracion') this.cfg.cotizacion_prefijos_permitidos = this.prefijosArr

        const payloads = []
        for (const k of fields) {
          let v
          if (BOOLEAN_KEYS.has(k)) {
            v = this.cfgBool[k] ? '1' : '0'
          } else if (NUMERIC_KEYS.has(k)) {
            v = this.cfgNumeric[k] != null ? String(this.cfgNumeric[k]) : ''
          } else if (JSON_ARRAY_KEYS.has(k)) {
            v = JSON.stringify(this.cfg[k] || [])
          } else {
            v = this.cfg[k] != null ? this.cfg[k] : ''
          }
          payloads.push({ key: k, value: v })
        }

        // Update secuencial (puede paralelizarse, pero secuencial es más fácil de debuggear)
        for (const p of payloads) {
          await this.$http.put('/marketing/quotation/config/' + encodeURIComponent(p.key), { config_value: p.value })
        }
        this.$message.success('Configuración guardada')
      } catch (e) {
        const msg = (e.response && e.response.data && e.response.data.message) || 'Error guardando'
        this.$message.error(msg)
      } finally {
        this.$set(this.saving, tab, false)
      }
    },

    // ── Margins ──
    async loadMargins() {
      this.loadingMargins = true
      try {
        const r = await this.$http.get('/marketing/quotation/margins')
        this.margins = r.data.data || r.data.margins || r.data || []
      } catch (e) {
        // ignorar
      } finally {
        this.loadingMargins = false
      }
    },

    openMarginForm(row) {
      this.marginForm = row
        ? Object.assign(this.emptyMarginForm(), row)
        : this.emptyMarginForm()
      this.marginDialogVisible = true
    },

    async saveMargin() {
      if (!this.marginForm.category) {
        this.$message.error('Falta la categoría')
        return
      }
      // Fase B — validación cliente del mínimo (el backend también valida).
      if (this.marginMinExceedsMargin) {
        this.$message.error('El margen mínimo no puede ser mayor al margen sugerido.')
        return
      }
      this.savingMargin = true
      try {
        const payload = Object.assign({}, this.marginForm)
        // El input-number puede devolver undefined al limpiarse. El backend
        // espera null explícito para "sin alerta".
        if (payload.min_margin_percentage === undefined || payload.min_margin_percentage === '') {
          payload.min_margin_percentage = null
        }
        if (this.marginForm.id) {
          await this.$http.put('/marketing/quotation/margins/' + this.marginForm.id, payload)
        } else {
          await this.$http.post('/marketing/quotation/margins', payload)
        }
        this.marginDialogVisible = false
        await this.loadMargins()
        this.$message.success('Regla guardada')
      } catch (e) {
        const msg = (e.response && e.response.data && e.response.data.message) || 'Error guardando regla'
        this.$message.error(msg)
      } finally {
        this.savingMargin = false
      }
    },

    async toggleMargin(row) {
      try {
        await this.$http.put('/marketing/quotation/margins/' + row.id, {
          is_active: row.is_active,
        })
      } catch (e) {
        row.is_active = !row.is_active
        this.$message.error('Error cambiando estado')
      }
    },

    confirmDeleteMargin(row) {
      this.$confirm(
        `¿Eliminar regla? (${row.category} ${row.product_name || ''} ${row.quantity_from}-${row.quantity_to || '∞'} → ${row.margin_percentage}%)`,
        'Confirmar',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
      ).then(async () => {
        try {
          await this.$http.delete('/marketing/quotation/margins/' + row.id)
          await this.loadMargins()
          this.$message.success('Regla eliminada')
        } catch (e) {
          this.$message.error('Error eliminando')
        }
      }).catch(() => {})
    },

    /*
    |------------------------------------------------------------------
    | S4 — Sugerencias del sistema
    |------------------------------------------------------------------
    */

    async loadSuggestions() {
      this.loadingSuggestions = true
      try {
        const r = await this.$http.get('/marketing/quotation/suggestions', {
          params: { status: this.suggestionsFilter }
        })
        this.suggestions = (r.data && r.data.suggestions) || []
        if (this.suggestionsFilter === 'pending_effective') {
          this.suggestionsPendingCount = this.suggestions.length
        }
      } catch (e) {
        // ignorar
      } finally {
        this.loadingSuggestions = false
      }
    },

    async loadSuggestionsPendingCount() {
      try {
        const r = await this.$http.get('/marketing/quotation/suggestions', {
          params: { status: 'pending_effective' }
        })
        this.suggestionsPendingCount = (r.data && r.data.count) || 0
      } catch (e) {
        this.suggestionsPendingCount = 0
      }
    },

    async analyzeSuggestionsNow() {
      this.analyzingNow = true
      try {
        const r = await this.$http.post('/marketing/quotation/suggestions/analyze-now')
        if (r.data && r.data.ok) {
          this.$message.success(r.data.message || 'Análisis completado')
          await this.loadSuggestions()
          await this.loadSuggestionsPendingCount()
        } else {
          this.$message.error((r.data && r.data.message) || 'Error en el análisis')
        }
      } catch (e) {
        this.$message.error('Error ejecutando análisis')
      } finally {
        this.analyzingNow = false
      }
    },

    async applySuggestion(row) {
      try {
        await this.$confirm(
          `Aplicar sugerencia: actualizar ${row.target_config_key} a "${row.suggested_value}"?`,
          'Confirmar',
          { confirmButtonText: 'Aplicar', cancelButtonText: 'Cancelar', type: 'success' }
        )
      } catch (e) { return }

      this.actingSuggestionRow = row.id
      try {
        const r = await this.$http.post('/marketing/quotation/suggestions/' + row.id + '/apply')
        if (r.data && r.data.ok) {
          this.$message.success(r.data.message || 'Sugerencia aplicada')
          await this.loadSuggestions()
          await this.loadSuggestionsPendingCount()
          // Recargar config porque cambió
          await this.loadConfig()
        } else {
          this.$message.error((r.data && r.data.message) || 'No se pudo aplicar')
        }
      } catch (e) {
        this.$message.error('Error aplicando')
      } finally {
        this.actingSuggestionRow = null
      }
    },

    rejectSuggestion(row) {
      this.rejectTargetId = row.id
      this.rejectReason = ''
      this.rejectDialogVisible = true
    },

    async submitReject() {
      if (!this.rejectTargetId) return
      this.actingSuggestionRow = this.rejectTargetId
      try {
        const r = await this.$http.post(
          '/marketing/quotation/suggestions/' + this.rejectTargetId + '/reject',
          { reason: this.rejectReason || null }
        )
        if (r.data && r.data.ok) {
          this.$message.info(r.data.message || 'Sugerencia rechazada')
          this.rejectDialogVisible = false
          await this.loadSuggestions()
          await this.loadSuggestionsPendingCount()
        } else {
          this.$message.error((r.data && r.data.message) || 'Error rechazando')
        }
      } catch (e) {
        this.$message.error('Error rechazando')
      } finally {
        this.actingSuggestionRow = null
      }
    },

    async snoozeSuggestion(row) {
      this.actingSuggestionRow = row.id
      try {
        const r = await this.$http.post(
          '/marketing/quotation/suggestions/' + row.id + '/snooze',
          { days: 30 }
        )
        if (r.data && r.data.ok) {
          this.$message.info(r.data.message || 'Sugerencia pospuesta 30 días')
          await this.loadSuggestions()
          await this.loadSuggestionsPendingCount()
        }
      } catch (e) {
        this.$message.error('Error posponiendo')
      } finally {
        this.actingSuggestionRow = null
      }
    },

    /* ── Helpers de presentación ── */
    suggestionTypeLabel(type) {
      const map = {
        tiempo_categoria:  '⏱ Tiempo',
        tecnica_categoria: '🎨 Técnica',
        empaque_categoria: '📦 Empaque',
        margen_categoria:  '💰 Margen',
      }
      return map[type] || type
    },

    suggestionTypeTag(type) {
      const map = {
        tiempo_categoria:  'primary',
        tecnica_categoria: 'success',
        empaque_categoria: 'warning',
        margen_categoria:  'danger',
      }
      return map[type] || 'info'
    },

    suggestionStatusTag(status) {
      const map = {
        pending:  'warning',
        applied:  'success',
        rejected: 'info',
        snoozed:  'info',
      }
      return map[status] || 'info'
    },

    suggestionStatusLabel(row) {
      const map = {
        pending:  'Pendiente',
        applied:  'Aplicada',
        rejected: 'Rechazada',
        snoozed:  'Pospuesta',
      }
      return map[row.status] || row.status
    },

    isSuggestionTerminal(status) {
      return ['applied', 'rejected'].indexOf(status) !== -1
    },

    isPendingEffective(row) {
      if (row.status === 'pending') return true
      if (row.status === 'snoozed' && row.snoozed_until) {
        return new Date(row.snoozed_until) <= new Date()
      }
      return false
    },

    confidenceTag(c) {
      const v = parseFloat(c) || 0
      if (v >= 0.85) return 'success'
      if (v >= 0.60) return 'warning'
      return 'info'
    },

    formatDate(iso) {
      if (!iso) return ''
      const d = new Date(iso.replace(' ', 'T'))
      if (isNaN(d.getTime())) return iso
      return d.toLocaleDateString()
    },
  },
}
</script>

<style scoped>
.quotation-settings {
  padding: 16px;
}
.qs-header {
  margin-bottom: 16px;
}
.qs-title {
  margin: 0 0 4px 0;
  font-size: 22px;
  font-weight: 600;
}
.qs-subtitle {
  margin: 0;
  color: #909399;
  font-size: 13px;
}
.qs-form {
  max-width: 900px;
}
.qs-tip {
  background: #f4f8fb;
  border-left: 3px solid #409EFF;
  color: #606266;
  padding: 10px 14px;
  font-size: 13px;
  margin: 0 0 16px 0;
  border-radius: 0 4px 4px 0;
}
.qs-hint {
  display: block;
  color: #909399;
  font-size: 11px;
  margin-top: 2px;
  line-height: 1.3;
}
.qs-actions {
  margin-top: 18px;
  padding-top: 12px;
  border-top: 1px solid #ebeef5;
  text-align: right;
}
.qs-section-title {
  margin: 18px 0 4px 0;
  font-size: 14px;
  color: #303133;
  font-weight: 600;
}
.qs-section-hint {
  margin: 0 0 14px 0;
  color: #909399;
  font-size: 12px;
}
</style>

<style>
/* No scoped: necesario para penetrar a los componentes hijos de Element UI
   (tanto los que están en el árbol del componente como los que se renderizan
   con append-to-body como el-dialog). */

/* Labels en label-position=top: forzar full-width para que no se corten a media palabra */
.quotation-settings .el-form-item__label,
.qs-margin-dialog .el-form-item__label {
  width: 100% !important;
  float: none !important;
  text-align: left !important;
  padding: 0 !important;
  line-height: 1.4 !important;
  font-weight: 500;
  margin-bottom: 4px;
}
.quotation-settings .el-form-item__content,
.qs-margin-dialog .el-form-item__content {
  margin-left: 0 !important;
  line-height: 1.4 !important;
}
.quotation-settings .el-form-item {
  margin-bottom: 18px;
}
</style>
