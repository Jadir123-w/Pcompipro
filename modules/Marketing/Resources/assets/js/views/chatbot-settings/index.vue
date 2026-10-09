<template>
  <div class="chatbot-settings">
    <div class="cs-header">
      <div>
        <h2 class="cs-title">💬 Configuración del Chatbot (MKT-01)</h2>
        <p class="cs-subtitle">Widget web, catálogos, WhatsApp Cloud API y triage de correo. Las credenciales sensibles se guardan cifradas en BD.</p>
      </div>
    </div>

    <el-tabs v-model="activeTab" type="border-card" v-loading="loading">

      <!-- ═════════════════════════════════════════════════════════════════ -->
      <!-- 1. General                                                        -->
      <!-- ═════════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="general">
        <span slot="label"><i class="el-icon-setting"></i> General</span>
        <p class="cs-tip">Comportamiento general del widget web del chatbot.</p>

        <el-form label-position="top" size="small" class="cs-form">
          <el-form-item v-for="row in rowsByGroup.general" :key="row.id" :label="row.display_label || row.config_key">
            <!-- bool → switch -->
            <el-switch v-if="row.value_type === 'bool'"
                       v-model="drafts[row.config_key]"
                       @change="markDirty(row.config_key)"></el-switch>

            <!-- int → input-number -->
            <el-input-number v-else-if="row.value_type === 'int'"
                             v-model.number="drafts[row.config_key]"
                             :step="1" style="width: 200px;"
                             @change="markDirty(row.config_key)"></el-input-number>

            <!-- text → textarea -->
            <el-input v-else-if="row.value_type === 'text'"
                      type="textarea"
                      v-model="drafts[row.config_key]"
                      :rows="3"
                      @input="markDirty(row.config_key)"></el-input>

            <!-- string → input -->
            <el-input v-else
                      v-model="drafts[row.config_key]"
                      @input="markDirty(row.config_key)"></el-input>

            <small v-if="row.description" class="cs-hint">{{ row.description }}</small>
          </el-form-item>

          <div class="cs-actions">
            <el-button type="primary" icon="el-icon-check" @click="saveTab('general')" :loading="saving.general">Guardar general</el-button>
          </div>
        </el-form>
      </el-tab-pane>

      <!-- ═════════════════════════════════════════════════════════════════ -->
      <!-- 2. Catálogos                                                      -->
      <!-- ═════════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="catalogos">
        <span slot="label"><i class="el-icon-collection"></i> Catálogos</span>

        <p class="cs-tip">URLs de catálogos por línea de producto. El bot envía estos enlaces cuando el cliente pide ver el catálogo de una línea específica.</p>

        <div style="margin-bottom: 12px; text-align: right;">
          <el-button type="primary" size="small" icon="el-icon-plus" @click="openCatalogForm()">Nuevo catálogo</el-button>
        </div>

        <el-table :data="catalogs" border size="mini" v-loading="loadingCatalogs" empty-text="Sin catálogos. Crea uno con el botón de arriba.">
          <el-table-column label="Línea" min-width="150">
            <template slot-scope="scope">
              <strong v-if="!scope.row.is_general">{{ scope.row.line_name }}</strong>
              <el-tag v-else size="mini" type="warning">CATÁLOGO GENERAL</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="URL" min-width="280">
            <template slot-scope="scope">
              <a :href="scope.row.catalog_url" target="_blank" rel="noopener">{{ scope.row.catalog_url }}</a>
            </template>
          </el-table-column>
          <el-table-column label="Orden" width="80" align="center" prop="sort_order"></el-table-column>
          <el-table-column label="Activo" width="80" align="center">
            <template slot-scope="scope">
              <el-switch v-model="scope.row.active" @change="toggleCatalog(scope.row)"></el-switch>
            </template>
          </el-table-column>
          <el-table-column label="Acciones" width="140" align="center">
            <template slot-scope="scope">
              <el-button size="mini" icon="el-icon-edit" @click="openCatalogForm(scope.row)"></el-button>
              <el-button size="mini" type="danger" icon="el-icon-delete" @click="confirmDeleteCatalog(scope.row)"></el-button>
            </template>
          </el-table-column>
        </el-table>

        <!-- Dialog catálogo -->
        <el-dialog :title="catalogForm.id ? 'Editar catálogo' : 'Nuevo catálogo'"
                   :visible.sync="catalogDialogVisible" width="540px"
                   custom-class="cs-form-dialog" append-to-body>
          <el-form label-position="top" size="small" v-loading="savingCatalog">
            <el-form-item label="Línea" required>
              <el-input v-model="catalogForm.line_name" placeholder="Textil, Impresos, Eco, General…"></el-input>
            </el-form-item>
            <el-form-item label="URL del catálogo PDF" required>
              <el-input v-model="catalogForm.catalog_url" placeholder="https://compipro.com/catalogo-textil.pdf"></el-input>
            </el-form-item>
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item label="¿Es catálogo general?">
                  <el-switch v-model="catalogForm.is_general" active-text="Sí (cubre todas las líneas)"></el-switch>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="Orden">
                  <el-input-number v-model="catalogForm.sort_order" :min="0" style="width: 100%;"></el-input-number>
                </el-form-item>
              </el-col>
            </el-row>
            <el-form-item label="Activo">
              <el-switch v-model="catalogForm.active"></el-switch>
            </el-form-item>
          </el-form>
          <div slot="footer">
            <el-button @click="catalogDialogVisible = false">Cancelar</el-button>
            <el-button type="primary" @click="saveCatalog" :loading="savingCatalog" icon="el-icon-check">
              {{ catalogForm.id ? 'Guardar cambios' : 'Crear catálogo' }}
            </el-button>
          </div>
        </el-dialog>
      </el-tab-pane>

      <!-- ═════════════════════════════════════════════════════════════════ -->
      <!-- 3. WhatsApp                                                        -->
      <!-- ═════════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="whatsapp">
        <span slot="label"><i class="el-icon-mobile-phone"></i> WhatsApp</span>

        <el-alert type="warning" show-icon :closable="false" style="margin-bottom: 12px;">
          <template slot="title">
            <strong>Antes de activar WhatsApp</strong>
          </template>
          <div style="font-size: 13px;">
            Necesitas tener una cuenta verificada en
            <a href="https://business.facebook.com" target="_blank" rel="noopener">Meta Business</a>,
            un número dedicado al bot, y haber generado un Access Token (preferentemente de System User para no expirar).
            Después debes registrar la URL del webhook en el panel de Meta:<br>
            <code>https://compipro.erp.prodixperu.com/api/marketing/chatbot/whatsapp/webhook</code>
          </div>
        </el-alert>

        <p class="cs-tip">El Access Token se guarda <strong>cifrado en BD</strong>. Los demás son metadata privada (no secret).</p>

        <el-form label-position="top" size="small" class="cs-form">
          <el-form-item v-for="row in rowsByGroup.whatsapp" :key="row.id" :label="row.display_label || row.config_key">
            <!-- sensitive → password con show-password -->
            <template v-if="row.is_sensitive">
              <el-input type="password"
                        v-model="drafts[row.config_key]"
                        :placeholder="row.is_set ? 'Configurado (●●●●●●●●). Ingresa nuevo valor para cambiar.' : 'No configurado. Pegá el valor aquí.'"
                        show-password
                        @input="markDirty(row.config_key)"></el-input>
              <div style="margin-top: 4px;">
                <el-tag v-if="row.is_set" size="mini" type="success">Configurado (cifrado)</el-tag>
                <el-tag v-else size="mini" type="danger">No configurado</el-tag>
              </div>
            </template>
            <el-switch v-else-if="row.value_type === 'bool'"
                       v-model="drafts[row.config_key]"
                       @change="markDirty(row.config_key)"></el-switch>
            <el-input-number v-else-if="row.value_type === 'int'"
                             v-model.number="drafts[row.config_key]"
                             :step="1" style="width: 200px;"
                             @change="markDirty(row.config_key)"></el-input-number>
            <el-input v-else-if="row.value_type === 'text'"
                      type="textarea"
                      v-model="drafts[row.config_key]"
                      :rows="3"
                      @input="markDirty(row.config_key)"></el-input>
            <el-select v-else-if="row.value_type === 'select' && row.options && row.options.length"
                       v-model="drafts[row.config_key]"
                       @change="markDirty(row.config_key)"
                       style="width: 100%;">
              <el-option v-for="opt in row.options" :key="opt.value"
                         :value="opt.value" :label="opt.label"></el-option>
            </el-select>
            <el-input v-else
                      v-model="drafts[row.config_key]"
                      @input="markDirty(row.config_key)"></el-input>

            <small v-if="row.description" class="cs-hint">{{ row.description }}</small>
          </el-form-item>

          <div class="cs-actions">
            <el-button type="primary" icon="el-icon-check" @click="saveTab('whatsapp')" :loading="saving.whatsapp">Guardar WhatsApp</el-button>
            <el-button type="success" plain icon="el-icon-connection" @click="testConnection('whatsapp')" :loading="testing.whatsapp">Probar conexión</el-button>
          </div>

          <!-- Resultado del test de conexión WhatsApp -->
          <div v-if="testResult.whatsapp" class="cs-test-result" style="margin-top: 16px;">
            <el-alert :type="testResult.whatsapp.ok ? 'success' : 'error'"
                      :title="testResult.whatsapp.message"
                      :closable="false"
                      show-icon>
              <template>
                <ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 13px;">
                  <li v-for="(c, i) in (testResult.whatsapp.checks || [])" :key="i">
                    <i :class="c.ok ? 'el-icon-success' : 'el-icon-error'"
                       :style="{ color: c.ok ? '#67c23a' : '#f56c6c' }"></i>
                    <strong>{{ c.titulo }}</strong>
                    <span v-if="c.detalle" style="color: #606266;"> — {{ c.detalle }}</span>
                  </li>
                </ul>
                <div v-if="testResult.whatsapp.details && testResult.whatsapp.details.display_phone"
                     style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #ebeef5; font-size: 13px;">
                  <strong>Número conectado:</strong> {{ testResult.whatsapp.details.display_phone }}
                  <span v-if="testResult.whatsapp.details.verified_name"> ({{ testResult.whatsapp.details.verified_name }})</span>
                </div>
              </template>
            </el-alert>
          </div>
        </el-form>
      </el-tab-pane>

      <!-- ═════════════════════════════════════════════════════════════════ -->
      <!-- 4. Correo                                                          -->
      <!-- ═════════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="email">
        <span slot="label"><i class="el-icon-message"></i> Correo (Triage)</span>

        <el-alert type="info" show-icon :closable="false" style="margin-bottom: 12px;">
          <template slot="title">
            <strong>Triage de correo (Email Triage)</strong>
          </template>
          <div style="font-size: 13px;">
            Cuando esté activo, un job en background lee la bandeja IMAP cada N minutos, clasifica los correos con IA
            (RFQ / Consulta / No relacionado), crea leads y prepara borradores de respuesta para que la ejecutiva apruebe.<br>
            La <strong>contraseña</strong> se guarda <strong>cifrada en BD</strong>; los demás campos son metadata.
          </div>
        </el-alert>

        <p class="cs-tip">Configuración para servidores cPanel típicos: IMAP 993 SSL, SMTP 465 SSL.</p>

        <el-form label-position="top" size="small" class="cs-form">
          <el-form-item v-for="row in rowsByGroup.email" :key="row.id" :label="row.display_label || row.config_key">
            <template v-if="row.is_sensitive">
              <el-input type="password"
                        v-model="drafts[row.config_key]"
                        :placeholder="row.is_set ? 'Configurado (●●●●●●●●). Ingresa nuevo valor para cambiar.' : 'No configurado. Pegá la contraseña aquí.'"
                        show-password
                        @input="markDirty(row.config_key)"></el-input>
              <div style="margin-top: 4px;">
                <el-tag v-if="row.is_set" size="mini" type="success">Configurado (cifrado)</el-tag>
                <el-tag v-else size="mini" type="danger">No configurado</el-tag>
              </div>
            </template>
            <el-switch v-else-if="row.value_type === 'bool'"
                       v-model="drafts[row.config_key]"
                       @change="markDirty(row.config_key)"></el-switch>
            <el-input-number v-else-if="row.value_type === 'int'"
                             v-model.number="drafts[row.config_key]"
                             :step="1" style="width: 200px;"
                             @change="markDirty(row.config_key)"></el-input-number>
            <el-input v-else-if="row.value_type === 'text'"
                      type="textarea"
                      v-model="drafts[row.config_key]"
                      :rows="3"
                      @input="markDirty(row.config_key)"></el-input>
            <el-select v-else-if="row.value_type === 'select' && row.options && row.options.length"
                       v-model="drafts[row.config_key]"
                       @change="markDirty(row.config_key)"
                       style="width: 100%;">
              <el-option v-for="opt in row.options" :key="opt.value"
                         :value="opt.value" :label="opt.label"></el-option>
            </el-select>
            <el-input v-else
                      v-model="drafts[row.config_key]"
                      @input="markDirty(row.config_key)"></el-input>

            <small v-if="row.description" class="cs-hint">{{ row.description }}</small>
          </el-form-item>

          <div class="cs-actions">
            <el-button type="primary" icon="el-icon-check" @click="saveTab('email')" :loading="saving.email">Guardar correo</el-button>
            <el-button type="success" plain icon="el-icon-connection" @click="testConnection('email')" :loading="testing.email">Probar conexión</el-button>
          </div>

          <!-- Resultado del test de conexión Email Triage -->
          <div v-if="testResult.email" class="cs-test-result" style="margin-top: 16px;">
            <el-alert :type="testResult.email.ok ? 'success' : 'error'"
                      :title="testResult.email.message"
                      :closable="false"
                      show-icon>
              <template>
                <ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 13px;">
                  <li v-for="(c, i) in (testResult.email.checks || [])" :key="i">
                    <i :class="c.ok ? 'el-icon-success' : 'el-icon-error'"
                       :style="{ color: c.ok ? '#67c23a' : '#f56c6c' }"></i>
                    <strong>{{ c.titulo }}</strong>
                    <span v-if="c.detalle" style="color: #606266;"> — {{ c.detalle }}</span>
                  </li>
                </ul>
              </template>
            </el-alert>
          </div>
        </el-form>
      </el-tab-pane>

      <!-- ═════════════════════════════════════════════════════════════════ -->
      <!-- 4.4. IA / Modelos — provider de texto + modelos                    -->
      <!-- ═════════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="ia">
        <span slot="label"><i class="el-icon-cpu"></i> IA / Modelos</span>

        <el-alert type="info" show-icon :closable="false" style="margin-bottom: 12px;">
          <template slot="title">
            <strong>Proveedor de IA y modelos</strong>
          </template>
          <div style="font-size: 13px;">
            Estos parámetros controlan el motor de IA usado por defecto en los engines del módulo
            Marketing (chatbot, cotizador, social media). Antes vivían en el .env; ahora son
            configurables desde acá. Las API keys siguen en .env porque son secretos.
            <br><br>
            <strong>Tip:</strong> los engines nuevos (PieceProducer, Quotation) pueden override este
            provider/modelo desde "Config Agentes IA" (panel separado) si querés mezclar providers.
          </div>
        </el-alert>

        <el-form label-position="top" size="small" class="cs-form">
          <el-form-item v-for="row in rowsByGroup.ia" :key="row.id" :label="row.display_label || row.config_key">
            <template v-if="row.is_sensitive">
              <el-input type="password"
                        v-model="drafts[row.config_key]"
                        :placeholder="row.is_set ? 'Configurado (●●●●●●●●). Ingresa nuevo valor para cambiar.' : 'No configurado. Pegá el valor aquí.'"
                        show-password
                        @input="markDirty(row.config_key)"></el-input>
              <div style="margin-top: 4px;">
                <el-tag v-if="row.is_set" size="mini" type="success">Configurado (cifrado)</el-tag>
                <el-tag v-else size="mini" type="danger">No configurado</el-tag>
              </div>
            </template>
            <el-switch v-else-if="row.value_type === 'bool'"
                       v-model="drafts[row.config_key]"
                       @change="markDirty(row.config_key)"></el-switch>
            <el-input-number v-else-if="row.value_type === 'int'"
                             v-model.number="drafts[row.config_key]"
                             :step="1" style="width: 200px;"
                             @change="markDirty(row.config_key)"></el-input-number>
            <el-input v-else-if="row.value_type === 'text'"
                      type="textarea"
                      v-model="drafts[row.config_key]"
                      :rows="3"
                      @input="markDirty(row.config_key)"></el-input>
            <el-select v-else-if="row.value_type === 'select' && row.options && row.options.length"
                       v-model="drafts[row.config_key]"
                       @change="markDirty(row.config_key)"
                       style="width: 100%;">
              <el-option v-for="opt in row.options" :key="opt.value"
                         :value="opt.value" :label="opt.label"></el-option>
            </el-select>
            <el-input v-else
                      v-model="drafts[row.config_key]"
                      @input="markDirty(row.config_key)"></el-input>

            <small v-if="row.description" class="cs-hint">{{ row.description }}</small>
          </el-form-item>

          <div class="cs-actions">
            <el-button type="primary" icon="el-icon-check" @click="saveTab('ia')" :loading="saving.ia">Guardar IA</el-button>
          </div>
        </el-form>
      </el-tab-pane>

      <!-- ═════════════════════════════════════════════════════════════════ -->
      <!-- 4.5. MKT-03 (Social Media) — provider y modelo de imagen           -->
      <!-- ═════════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="mkt03">
        <span slot="label"><i class="el-icon-picture-outline"></i> MKT-03 (Imagen)</span>

        <el-alert type="info" show-icon :closable="false" style="margin-bottom: 12px;">
          <template slot="title">
            <strong>Configuración del agente MKT-03 (Social Media)</strong>
          </template>
          <div style="font-size: 13px;">
            Estos parámetros controlan cómo el agente genera las imágenes de las piezas.
            <strong>Antes vivían en el .env</strong>; ahora son configurables desde acá sin
            necesidad de DevOps.
            <ul style="margin: 6px 0 0 18px; padding: 0;">
              <li><strong>DALL-E 3</strong>: estable, no requiere verificar organización en OpenAI.</li>
              <li><strong>GPT-Image-2</strong>: mejor calidad y soporta imágenes de referencia, pero requiere
                  <em>API Organization Verification</em> en platform.openai.com.</li>
            </ul>
          </div>
        </el-alert>

        <el-form label-position="top" size="small" class="cs-form">
          <el-form-item v-for="row in rowsByGroup.mkt03" :key="row.id" :label="row.display_label || row.config_key">
            <template v-if="row.is_sensitive">
              <el-input type="password"
                        v-model="drafts[row.config_key]"
                        :placeholder="row.is_set ? 'Configurado (●●●●●●●●). Ingresa nuevo valor para cambiar.' : 'No configurado. Pegá el valor aquí.'"
                        show-password
                        @input="markDirty(row.config_key)"></el-input>
              <div style="margin-top: 4px;">
                <el-tag v-if="row.is_set" size="mini" type="success">Configurado (cifrado)</el-tag>
                <el-tag v-else size="mini" type="danger">No configurado</el-tag>
              </div>
            </template>
            <el-switch v-else-if="row.value_type === 'bool'"
                       v-model="drafts[row.config_key]"
                       @change="markDirty(row.config_key)"></el-switch>
            <el-input-number v-else-if="row.value_type === 'int'"
                             v-model.number="drafts[row.config_key]"
                             :step="1" style="width: 200px;"
                             @change="markDirty(row.config_key)"></el-input-number>
            <el-input v-else-if="row.value_type === 'text'"
                      type="textarea"
                      v-model="drafts[row.config_key]"
                      :rows="3"
                      @input="markDirty(row.config_key)"></el-input>
            <el-select v-else-if="row.value_type === 'select' && row.options && row.options.length"
                       v-model="drafts[row.config_key]"
                       @change="markDirty(row.config_key)"
                       style="width: 100%;">
              <el-option v-for="opt in row.options" :key="opt.value"
                         :value="opt.value" :label="opt.label"></el-option>
            </el-select>
            <el-input v-else
                      v-model="drafts[row.config_key]"
                      @input="markDirty(row.config_key)"></el-input>

            <small v-if="row.description" class="cs-hint">{{ row.description }}</small>
          </el-form-item>

          <div class="cs-actions">
            <el-button type="primary" icon="el-icon-check" @click="saveTab('mkt03')" :loading="saving.mkt03">Guardar MKT-03</el-button>
          </div>
        </el-form>
      </el-tab-pane>

      <!-- ═════════════════════════════════════════════════════════════════ -->
      <!-- 5. FAQs                                                            -->
      <!-- ═════════════════════════════════════════════════════════════════ -->
      <el-tab-pane name="faqs">
        <span slot="label"><i class="el-icon-question"></i> FAQs</span>

        <p class="cs-tip">Preguntas frecuentes y sus respuestas oficiales. El chatbot las usa como fuente de verdad cuando el usuario consulta sobre tiempos, mínimos, pagos, personalización, envíos, etc. Las inactivas no se inyectan en el prompt.</p>

        <el-row :gutter="12" style="margin-bottom: 12px;">
          <el-col :span="14">
            <el-input size="small" placeholder="Buscar pregunta o palabra clave…" v-model="faqSearch" clearable prefix-icon="el-icon-search"></el-input>
          </el-col>
          <el-col :span="6">
            <el-select size="small" v-model="faqCategoryFilter" placeholder="Todas las categorías" clearable style="width: 100%;">
              <el-option v-for="c in faqCategories" :key="c" :label="c" :value="c"></el-option>
            </el-select>
          </el-col>
          <el-col :span="4" style="text-align: right;">
            <el-button type="primary" size="small" icon="el-icon-plus" @click="openFaqForm()">Nueva FAQ</el-button>
          </el-col>
        </el-row>

        <el-table :data="filteredFaqs" border size="mini" v-loading="loadingFaqs" empty-text="Sin FAQs. Crea la primera con el botón de arriba.">
          <el-table-column label="Categoría" width="140">
            <template slot-scope="scope">
              <el-tag v-if="scope.row.category" size="mini" type="info">{{ scope.row.category }}</el-tag>
              <span v-else style="color:#c0c4cc;">—</span>
            </template>
          </el-table-column>
          <el-table-column label="Pregunta" min-width="240">
            <template slot-scope="scope">
              <strong>{{ scope.row.question }}</strong>
              <div v-if="scope.row.keywords" style="color:#909399; font-size:11px; margin-top:2px;">
                <i class="el-icon-price-tag"></i> {{ scope.row.keywords }}
              </div>
            </template>
          </el-table-column>
          <el-table-column label="Respuesta" min-width="280">
            <template slot-scope="scope">
              <div class="faq-answer-preview">{{ scope.row.answer }}</div>
            </template>
          </el-table-column>
          <el-table-column label="Orden" width="70" align="center" prop="sort_order"></el-table-column>
          <el-table-column label="Activa" width="80" align="center">
            <template slot-scope="scope">
              <el-switch v-model="scope.row.active" @change="toggleFaq(scope.row)"></el-switch>
            </template>
          </el-table-column>
          <el-table-column label="Acciones" width="120" align="center">
            <template slot-scope="scope">
              <el-button size="mini" icon="el-icon-edit" @click="openFaqForm(scope.row)"></el-button>
              <el-button size="mini" type="danger" icon="el-icon-delete" @click="confirmDeleteFaq(scope.row)"></el-button>
            </template>
          </el-table-column>
        </el-table>

        <!-- Dialog FAQ -->
        <el-dialog :title="faqForm.id ? 'Editar FAQ' : 'Nueva FAQ'"
                   :visible.sync="faqDialogVisible" width="640px"
                   custom-class="cs-form-dialog" append-to-body>
          <el-form label-position="top" size="small" v-loading="savingFaq">
            <el-form-item label="Pregunta">
              <el-input v-model="faqForm.question"
                        placeholder="¿Cuánto demora la producción de mochilas?"
                        maxlength="500" show-word-limit></el-input>
            </el-form-item>
            <el-form-item label="Respuesta">
              <el-input type="textarea" :rows="5"
                        v-model="faqForm.answer"
                        placeholder="Para mochilas estándar el tiempo es de 10–15 días hábiles desde la aprobación de muestra."></el-input>
            </el-form-item>
            <el-form-item label="Palabras clave (opcional, separadas por coma)">
              <el-input v-model="faqForm.keywords"
                        placeholder="tiempo, demora, plazo, entrega, días, fecha"
                        maxlength="500"></el-input>
              <small class="cs-hint">Ayudan al bot a identificar variaciones de la pregunta.</small>
            </el-form-item>
            <el-row :gutter="16">
              <el-col :span="14">
                <el-form-item label="Categoría (opcional)">
                  <el-autocomplete
                    v-model="faqForm.category"
                    :fetch-suggestions="fetchCategorySuggestions"
                    placeholder="Tiempos, Pagos, Personalización, Envíos…"
                    style="width: 100%;"
                    clearable></el-autocomplete>
                </el-form-item>
              </el-col>
              <el-col :span="5">
                <el-form-item label="Orden">
                  <el-input-number v-model="faqForm.sort_order" :min="0" style="width: 100%;"></el-input-number>
                </el-form-item>
              </el-col>
              <el-col :span="5">
                <el-form-item label="Activa">
                  <el-switch v-model="faqForm.active"></el-switch>
                </el-form-item>
              </el-col>
            </el-row>
          </el-form>
          <div slot="footer">
            <el-button @click="faqDialogVisible = false">Cancelar</el-button>
            <el-button type="primary" @click="saveFaq" :loading="savingFaq" icon="el-icon-check">
              {{ faqForm.id ? 'Guardar cambios' : 'Crear FAQ' }}
            </el-button>
          </div>
        </el-dialog>
      </el-tab-pane>

    </el-tabs>
  </div>
</template>

<script>
export default {
  data() {
    return {
      activeTab: 'general',
      loading: false,
      rowsByGroup: { general: [], whatsapp: [], email: [], mkt03: [], ia: [] },
      drafts: {},   // map config_key → valor actual del input
      dirty: {},    // map config_key → true si fue modificado
      saving: { general: false, whatsapp: false, email: false, mkt03: false, ia: false },

      // Test de conexión (botones "Probar conexión")
      testing: { whatsapp: false, email: false },
      testResult: { whatsapp: null, email: null }, // {ok, message, checks, details}

      // Catálogos
      catalogs: [],
      loadingCatalogs: false,
      catalogDialogVisible: false,
      savingCatalog: false,
      catalogForm: this.emptyCatalogForm(),

      // FAQs
      faqs: [],
      loadingFaqs: false,
      faqDialogVisible: false,
      savingFaq: false,
      faqForm: this.emptyFaqForm(),
      faqSearch: '',
      faqCategoryFilter: null,
      faqCategories: [],   // listado distinct para autocompletado y filtro
    }
  },
  computed: {
    /**
     * Aplica filtros (search libre + categoría) sobre this.faqs.
     */
    filteredFaqs() {
      const term = (this.faqSearch || '').trim().toLowerCase()
      const cat  = this.faqCategoryFilter
      return (this.faqs || []).filter(f => {
        if (cat && f.category !== cat) return false
        if (!term) return true
        const hay = (
          (f.question || '') + ' ' +
          (f.answer || '') + ' ' +
          (f.keywords || '') + ' ' +
          (f.category || '')
        ).toLowerCase()
        return hay.indexOf(term) !== -1
      })
    },
  },
  created() {
    this.loadConfig()
    this.loadCatalogs()
    this.loadFaqs()
    this.loadFaqCategories()
  },
  methods: {
    emptyCatalogForm() {
      return {
        id: null,
        line_name: '',
        catalog_url: '',
        is_general: false,
        active: true,
        sort_order: 0,
      }
    },

    emptyFaqForm() {
      return {
        id: null,
        question: '',
        answer: '',
        keywords: '',
        category: '',
        active: true,
        sort_order: 0,
      }
    },

    markDirty(key) {
      this.$set(this.dirty, key, true)
    },

    initialDraftValue(row) {
      if (row.is_sensitive) return ''  // Sensitive: input vacío. Si user pega algo, se cambia.
      if (row.value_type === 'bool') return !!row.typed_value
      if (row.value_type === 'int') {
        return (row.typed_value !== null && row.typed_value !== undefined && row.typed_value !== '')
          ? parseInt(row.typed_value, 10)
          : null
      }
      return row.typed_value !== null && row.typed_value !== undefined ? String(row.typed_value) : ''
    },

    async loadConfig() {
      this.loading = true
      try {
        const r = await this.$http.get('/marketing/chatbot/settings/config')
        const groups = r.data.groups || {}
        const drafts = {}
        for (const g of ['general', 'whatsapp', 'email', 'mkt03', 'ia']) {
          let arr = groups[g] || []
          if (!Array.isArray(arr)) arr = Object.values(arr)
          // Inicializar el draft con el valor actual
          arr.forEach(row => { drafts[row.config_key] = this.initialDraftValue(row) })
          this.$set(this.rowsByGroup, g, arr)
        }
        this.drafts = drafts
        this.dirty = {}
      } catch (e) {
        this.$message.error('Error cargando configuración: ' + (e.message || ''))
      } finally {
        this.loading = false
      }
    },

    /**
     * Botón "Probar conexión" para WhatsApp y Email Triage.
     *
     * Llama al endpoint correspondiente y guarda el resultado en testResult[group]
     * para que la UI lo pinte como una checklist con success/error por chequeo.
     *
     * Si el usuario dejó cambios sin guardar (dirty), avisa primero — el test usa
     * los valores en BD, no los del form.
     */
    async testConnection(group) {
      const dirtyInGroup = (this.rowsByGroup[group] || []).some(r => this.dirty[r.config_key])
      if (dirtyInGroup) {
        const ok = await this.$confirm(
          'Hay cambios sin guardar en esta pestaña. El test usará los valores actualmente guardados en BD, no los del formulario. ¿Probar igual?',
          'Cambios sin guardar',
          { confirmButtonText: 'Probar igual', cancelButtonText: 'Cancelar', type: 'warning' }
        ).catch(() => false)
        if (!ok) return
      }

      const url = group === 'whatsapp'
        ? '/marketing/chatbot/whatsapp/test-connection'
        : '/marketing/chatbot/email-triage/test-connection'

      this.$set(this.testing, group, true)
      this.$set(this.testResult, group, null)

      try {
        const resp = await this.$http.post(url)
        this.$set(this.testResult, group, resp.data)
        if (resp.data.ok) {
          this.$message.success('Conexión OK')
        } else {
          this.$message.warning('La conexión falló — revisá el detalle abajo')
        }
      } catch (e) {
        this.$set(this.testResult, group, {
          ok: false,
          message: 'Error inesperado al probar conexión: ' + (e.message || 'sin detalle'),
          checks: [],
        })
        this.$message.error('Error al probar conexión')
      } finally {
        this.$set(this.testing, group, false)
      }
    },

    async saveTab(group) {
      this.$set(this.saving, group, true)
      try {
        const items = []
        for (const row of this.rowsByGroup[group] || []) {
          if (!this.dirty[row.config_key]) continue
          let value = this.drafts[row.config_key]

          // Sensitive: si quedó vacío, NO lo enviamos (significa "no cambies, dejá lo que estaba")
          if (row.is_sensitive && (value === null || value === '')) continue

          if (row.value_type === 'bool') value = value ? '1' : '0'
          if (row.value_type === 'int')  value = (value === null || value === '') ? '' : String(value)

          items.push({
            key: row.config_key,
            value: value === null ? '' : String(value),
          })
        }
        if (items.length === 0) {
          this.$message.info('No hay cambios para guardar en esta pestaña.')
          this.$set(this.saving, group, false)
          return
        }
        await this.$http.post('/marketing/chatbot/settings/config/bulk', { items })
        this.$message.success(`Guardado: ${items.length} valor(es)`)
        await this.loadConfig()
      } catch (e) {
        const msg = (e.response && e.response.data && e.response.data.message) || 'Error guardando'
        this.$message.error(msg)
      } finally {
        this.$set(this.saving, group, false)
      }
    },

    // ── Catálogos ──
    async loadCatalogs() {
      this.loadingCatalogs = true
      try {
        const r = await this.$http.get('/marketing/chatbot/catalogs')
        let arr = (r.data.data && r.data.data.data) || (r.data.catalogs && r.data.catalogs.data) || r.data.catalogs || r.data || []
        if (!Array.isArray(arr) && typeof arr === 'object') arr = Object.values(arr)
        arr.forEach(c => {
          c.active = !!c.active
          c.is_general = !!c.is_general
        })
        this.catalogs = arr
      } catch (e) {
        // ignorar
      } finally {
        this.loadingCatalogs = false
      }
    },

    openCatalogForm(row) {
      this.catalogForm = row ? Object.assign(this.emptyCatalogForm(), row) : this.emptyCatalogForm()
      this.catalogDialogVisible = true
    },

    async saveCatalog() {
      if (!this.catalogForm.line_name) {
        this.$message.error('Falta el nombre de la línea (usa "General" si el catálogo cubre todas las líneas)')
        return
      }
      if (!this.catalogForm.catalog_url) {
        this.$message.error('Falta la URL del catálogo')
        return
      }
      this.savingCatalog = true
      try {
        const payload = Object.assign({}, this.catalogForm)
        if (this.catalogForm.id) {
          await this.$http.put('/marketing/chatbot/catalogs/' + this.catalogForm.id, payload)
        } else {
          await this.$http.post('/marketing/chatbot/catalogs', payload)
        }
        this.catalogDialogVisible = false
        await this.loadCatalogs()
        this.$message.success('Catálogo guardado')
      } catch (e) {
        this.$message.error((e.response && e.response.data && e.response.data.message) || 'Error guardando catálogo')
      } finally {
        this.savingCatalog = false
      }
    },

    async toggleCatalog(row) {
      try {
        await this.$http.put('/marketing/chatbot/catalogs/' + row.id, { active: row.active })
      } catch (e) {
        row.active = !row.active
        this.$message.error('Error cambiando estado')
      }
    },

    confirmDeleteCatalog(row) {
      this.$confirm(`¿Eliminar el catálogo "${row.line_name || 'general'}"?`, 'Confirmar',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
      ).then(async () => {
        try {
          await this.$http.delete('/marketing/chatbot/catalogs/' + row.id)
          await this.loadCatalogs()
          this.$message.success('Catálogo eliminado')
        } catch (e) {
          this.$message.error('Error eliminando')
        }
      }).catch(() => {})
    },

    // ── FAQs ──
    async loadFaqs() {
      this.loadingFaqs = true
      try {
        const r = await this.$http.get('/marketing/chatbot/faqs')
        let arr = (r.data && r.data.faqs) || r.data || []
        if (!Array.isArray(arr) && typeof arr === 'object') arr = Object.values(arr)
        arr.forEach(f => { f.active = !!f.active })
        this.faqs = arr
      } catch (e) {
        // silenciar si la tabla aún no existe en el tenant
      } finally {
        this.loadingFaqs = false
      }
    },

    async loadFaqCategories() {
      try {
        const r = await this.$http.get('/marketing/chatbot/faqs/categories')
        const cats = (r.data && r.data.categories) || []
        this.faqCategories = Array.isArray(cats) ? cats : []
      } catch (e) {
        this.faqCategories = []
      }
    },

    fetchCategorySuggestions(query, cb) {
      const q = (query || '').toLowerCase()
      const list = (this.faqCategories || [])
        .filter(c => !q || c.toLowerCase().indexOf(q) !== -1)
        .map(c => ({ value: c }))
      cb(list)
    },

    openFaqForm(row) {
      this.faqForm = row ? Object.assign(this.emptyFaqForm(), row) : this.emptyFaqForm()
      this.faqDialogVisible = true
    },

    async saveFaq() {
      if (!this.faqForm.question || !this.faqForm.question.trim()) {
        this.$message.error('La pregunta es obligatoria')
        return
      }
      if (!this.faqForm.answer || !this.faqForm.answer.trim()) {
        this.$message.error('La respuesta es obligatoria')
        return
      }
      this.savingFaq = true
      try {
        const payload = Object.assign({}, this.faqForm)
        // Limpiar strings vacíos para que vayan como null al backend
        if (payload.keywords === '') payload.keywords = null
        if (payload.category === '') payload.category = null

        if (this.faqForm.id) {
          await this.$http.put('/marketing/chatbot/faqs/' + this.faqForm.id, payload)
        } else {
          await this.$http.post('/marketing/chatbot/faqs', payload)
        }
        this.faqDialogVisible = false
        await this.loadFaqs()
        await this.loadFaqCategories()
        this.$message.success('FAQ guardada')
      } catch (e) {
        const data = e.response && e.response.data
        if (data && data.errors) {
          const first = Object.values(data.errors)[0]
          this.$message.error(Array.isArray(first) ? first[0] : 'Error guardando')
        } else {
          this.$message.error((data && data.message) || 'Error guardando FAQ')
        }
      } finally {
        this.savingFaq = false
      }
    },

    async toggleFaq(row) {
      try {
        await this.$http.put('/marketing/chatbot/faqs/' + row.id, { active: row.active })
      } catch (e) {
        row.active = !row.active
        this.$message.error('Error cambiando estado')
      }
    },

    confirmDeleteFaq(row) {
      const preview = (row.question || '').substring(0, 60)
      this.$confirm(`¿Eliminar la FAQ "${preview}…"?`, 'Confirmar',
        { confirmButtonText: 'Eliminar', cancelButtonText: 'Cancelar', type: 'warning' }
      ).then(async () => {
        try {
          await this.$http.delete('/marketing/chatbot/faqs/' + row.id)
          await this.loadFaqs()
          await this.loadFaqCategories()
          this.$message.success('FAQ eliminada')
        } catch (e) {
          this.$message.error('Error eliminando')
        }
      }).catch(() => {})
    },
  },
}
</script>

<style scoped>
.chatbot-settings { padding: 16px; }
.cs-header { margin-bottom: 16px; }
.cs-title { margin: 0 0 4px 0; font-size: 22px; font-weight: 600; }
.cs-subtitle { margin: 0; color: #909399; font-size: 13px; }
.cs-form { max-width: 900px; }
.cs-tip {
  background: #f4f8fb; border-left: 3px solid #409EFF; color: #606266;
  padding: 10px 14px; font-size: 13px; margin: 0 0 16px 0; border-radius: 0 4px 4px 0;
}
.cs-hint {
  display: block; color: #909399; font-size: 11px; margin-top: 2px; line-height: 1.3;
}
.cs-actions {
  margin-top: 18px; padding-top: 12px; border-top: 1px solid #ebeef5; text-align: right;
}
.faq-answer-preview {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  color: #606266;
  font-size: 12px;
  line-height: 1.45;
  white-space: pre-wrap;
}
</style>

<style>
/* No-scoped: penetra Element UI internals */
.chatbot-settings .el-form-item__label,
.cs-form-dialog .el-form-item__label {
  width: 100% !important; float: none !important; text-align: left !important;
  padding: 0 !important; line-height: 1.4 !important; font-weight: 500; margin-bottom: 4px;
}
.chatbot-settings .el-form-item__content,
.cs-form-dialog .el-form-item__content {
  margin-left: 0 !important; line-height: 1.4 !important;
}
.chatbot-settings .el-form-item { margin-bottom: 18px; }
</style>
