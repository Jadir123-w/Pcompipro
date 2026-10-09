<template>
    <el-dialog
      :title="titleDialog"
      :visible="showDialog"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="handleCloseDialog"
      @open="onOpen"
      width="1100px"
      custom-class="dialog-with-bitacora">
      
        <!-- Contenedor principal con flexbox -->
        <div class="dialog-content-wrapper">
            <!-- Contenido principal con tabs -->
            <div class="form-main-content">
                <el-tabs v-model="activeTab" type="card">
                    <!-- Tab 1: Datos del contacto -->
                    <el-tab-pane label="Datos" name="datos">
                        <form autocomplete="off" @submit.prevent="submit">
                            <div class="form-body">
                                
                                <!-- ID Contacto (solo en edición) -->
                                <div class="row" v-if="recordId">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="control-label">ID Contacto</label>
                                            <el-input :value="form.id" disabled></el-input>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 1: Empresa, Nombres, Apellidos -->
                                <div class="row">
                                    <!-- Mostrar nombre de empresa como campo disabled cuando viene desde empresas -->
                                    <div class="col-md-5" v-if="context === 'empresas'">
                                        <div class="form-group">
                                            <label class="control-label">Empresa</label>
                                            <div style="display: flex; align-items: center; padding: 10px 15px; background: #f5f7fa; border: 1px solid #dcdfe6; border-radius: 4px;">
                                                <i class="fa fa-building" style="color: #606266; margin-right: 10px;"></i>
                                                <span style="color: #303133; font-weight: 500;">
                                                    {{ currentEmpresa ? currentEmpresa.razon_social : 'Cargando...' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Solo mostrar selector de empresa si NO viene desde contexto de empresas -->
                                    <div class="col-md-5" v-else>
                                        <div :class="{'has-danger': errors.empresa_id}"
                                             class="form-group">
                                            <label class="control-label">
                                                Empresa <span class="text-danger">*</span>
                                                <el-button 
                                                    v-if="!empresaId"
                                                    type="text" 
                                                    size="mini" 
                                                    style="padding: 0; margin-left: 10px;">
                                                    + Nuevo
                                                </el-button>
                                            </label>
                                            <el-select
                                                v-model="form.empresa_id"
                                                clearable
                                                filterable
                                                placeholder="Ingrese al menos 3 caracteres para buscar"
                                                remote
                                                :remote-method="getEmpresas"
                                                :loading="loading"
                                                :automatic-dropdown="false"
                                                :disabled="!!empresaId"
                                                @focus="handleFocus"
                                            >
                                                <el-option
                                                    v-for="option in list_empresas"
                                                    :key="option.id"
                                                    :label="option.razon_social"
                                                    :value="option.id">
                                                </el-option>

                                                <div slot="empty" class="text-center p-2">
                                                    <template v-if="loading">
                                                        Cargando...
                                                    </template>
                                                    <template v-else>
                                                        {{ list_empresas.length === 0 ? 'Ingrese al menos 3 caracteres para buscar' : 'No se encontraron resultados' }}
                                                    </template>
                                                </div>
                                            </el-select>
                                            <small v-if="errors.empresa_id"
                                                   class="form-control-feedback"
                                                   v-text="errors.empresa_id[0]"></small>
                                        </div>
                                    </div>
                                    <!-- Ajustar tamaño de columnas -->
                                    <div class="col-md-4">
                                        <div class="form-group" :class="{'has-danger': errors.nombres}">
                                            <label class="control-label">Nombres <span class="text-danger">*</span></label>
                                            <el-input v-model="form.nombres"></el-input>
                                            <small class="form-control-feedback" v-if="errors.nombres" v-text="errors.nombres[0]"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group" :class="{'has-danger': errors.apellidos}">
                                            <label class="control-label">Apellidos</label>
                                            <el-input v-model="form.apellidos"></el-input>
                                            <small class="form-control-feedback" v-if="errors.apellidos" v-text="errors.apellidos[0]"></small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 2: Cargo, DNI, Tipo contacto -->
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group" :class="{'has-danger': errors.cargo}">
                                            <label class="control-label">Cargo</label>
                                            <el-input v-model="form.cargo"></el-input>
                                            <small class="form-control-feedback" v-if="errors.cargo" v-text="errors.cargo[0]"></small>
                                        </div>
                                    </div>
                                    <!-- DNI oculto temporalmente -->
                                    <!--
                                    <div class="col-md-4">
                                        <div class="form-group" :class="{'has-danger': errors.dni}">
                                            <label class="control-label">Nº Identidad (DNI)</label>
                                            <el-input v-model="form.dni" maxlength="20"></el-input>
                                            <small class="form-control-feedback" v-if="errors.dni" v-text="errors.dni[0]"></small>
                                        </div>
                                    </div>
                                    -->
                                    <div class="col-md-4">
                                        <div class="form-group" :class="{'has-danger': errors.tipo_contacto}">
                                            <label class="control-label">Detalle de contacto</label>
                                            <el-input v-model="form.tipo_contacto"></el-input>
                                            <small class="form-control-feedback" v-if="errors.tipo_contacto" v-text="errors.tipo_contacto[0]"></small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 3: Origen, Fecha cumpleaños -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group" :class="{'has-danger': errors.origen}">
                                            <label class="control-label">Origen</label>
                                            <el-select v-model="form.origen" clearable filterable placeholder="Seleccione" style="width: 100%;">
                                                <el-option 
                                                    v-for="option in list_origenes" 
                                                    :key="option.id" 
                                                    :label="option.name" 
                                                    :value="option.id">
                                                </el-option>
                                            </el-select>
                                            <small class="form-control-feedback" v-if="errors.origen" v-text="errors.origen[0]"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group" :class="{'has-danger': errors.fecha_cumpleanos}">
                                            <label class="control-label">Fecha cumpleaños</label>
                                            <el-date-picker 
                                                v-model="form.fecha_cumpleanos" 
                                                type="date" 
                                                value-format="yyyy-MM-dd" 
                                                format="dd/MM/yyyy" 
                                                :clearable="true"
                                                placeholder="dd/mm/aaaa"
                                                style="width: 100%;">
                                            </el-date-picker>
                                            <small class="form-control-feedback" v-if="errors.fecha_cumpleanos" v-text="errors.fecha_cumpleanos[0]"></small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 4: Celular, Teléfono fijo, Email principal -->
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="control-label">Celular principal</label>
                                            <div class="input-with-select">
                                                <el-select v-model="celularTipo" style="width: 100px;" size="small">
                                                    <el-option label="Trabajo" value="trabajo"></el-option>
                                                    <el-option label="Personal" value="personal"></el-option>
                                                </el-select>
                                                <el-input v-model="celularPrincipal" placeholder="Celular"></el-input>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="control-label">Teléfono fijo</label>
                                            <div class="input-with-select">
                                                <el-select v-model="telefonoTipo" style="width: 100px;" size="small">
                                                    <el-option label="Trabajo" value="trabajo"></el-option>
                                                    <el-option label="Personal" value="personal"></el-option>
                                                </el-select>
                                                <el-input v-model="telefonoFijo" placeholder="Teléfono fijo"></el-input>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="control-label">Email principal</label>
                                            <div class="input-with-select">
                                                <el-select v-model="emailTipo" style="width: 90px;" size="small">
                                                    <el-option label="Trabajo" value="trabajo"></el-option>
                                                    <el-option label="Personal" value="personal"></el-option>
                                                </el-select>
                                                <el-input v-model="emailPrincipal" placeholder="Email principal"></el-input>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 5: Dirección (textarea) -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group" :class="{'has-danger': errors.direccion}">
                                            <label class="control-label">Dirección oficina del contacto</label>
                                            <el-input
                                                type="textarea"
                                                v-model="form.direccion"
                                                :rows="2"
                                                resize="vertical"
                                                placeholder="Dirección oficina del contacto"></el-input>
                                            <small class="form-control-feedback" v-if="errors.direccion" v-text="errors.direccion[0]"></small>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 6: Referencia -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="control-label">Referencia oficina del contacto</label>
                                            <el-input v-model="form.referencia" placeholder="Referencia oficina del contacto"></el-input>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fila 6b: Adicionales -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="control-label">Adicionales</label>
                                            <el-input
                                                type="textarea"
                                                v-model="form.adicionales"
                                                :rows="2"
                                                resize="vertical"
                                                placeholder="Notas adicionales del contacto"></el-input>
                                        </div>
                                    </div>
                                </div>

                                <!-- Teléfonos adicionales y Emails adicionales -->
                                <div class="row mt-3">
                                    <div class="col-md-6">
                                        <div class="additional-section">
                                            <span class="section-label">Teléfonos adicionales</span>
                                            <el-button type="primary" size="mini" @click="clickAddTelefono">Agregar</el-button>
                                        </div>
                                        <div v-for="(row, index) in form.telefonos" :key="'tel-' + index" class="additional-item">
                                            <el-select v-model="row.tipo" style="width: 80px;" size="small">
                                                <el-option v-for="option in list_tipos_telefono" :key="option.id" :label="option.name" :value="option.id"></el-option>
                                            </el-select>
                                            <el-input v-model="row.telefono" placeholder="Teléfono" size="small" style="flex: 1; margin: 0 5px;"></el-input>
                                            <el-button type="danger" icon="el-icon-delete" size="mini" circle @click="clickRemoveTelefono(index)"></el-button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="additional-section">
                                            <span class="section-label">Emails adicionales (tipo / email)</span>
                                            <el-button type="primary" size="mini" @click="clickAddEmail">Agregar</el-button>
                                        </div>
                                        <div v-for="(row, index) in form.emails" :key="'email-' + index" class="additional-item">
                                            <el-select v-model="row.tipo" style="width: 90px;" size="small">
                                                <el-option label="Trabajo" value="trabajo"></el-option>
                                                <el-option label="Personal" value="personal"></el-option>
                                            </el-select>
                                            <el-input v-model="row.email" placeholder="Email" size="small" style="flex: 1; margin: 0 5px;"></el-input>
                                            <el-button type="danger" icon="el-icon-delete" size="mini" circle @click="clickRemoveEmail(index)"></el-button>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="form-actions text-right m-t-20">
                                <el-button @click.prevent="close()">Cancelar</el-button>
                                <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
                            </div>
                        </form>
                    </el-tab-pane>

                    <!-- Tab 2: Interacciones (solo en modo edición) -->
                    <el-tab-pane label="Interacciones" name="interacciones" :disabled="!recordId">
                        <tab-interacciones-contacto
                            v-if="recordId"
                            :contacto-id="recordId"
                            @interaccion-added="onInteraccionAdded"
                        />
                        <el-alert
                            v-else
                            title="Guarda el contacto primero para poder agregar interacciones"
                            type="info"
                            :closable="false"
                            style="margin: 20px;"
                        />
                    </el-tab-pane>
                </el-tabs>
            </div>

            <!-- Panel de Bitácora (solo en modo edición) -->
            <bitacora-logs
                v-if="recordId"
                entity-type="contacto"
                :entity-id="recordId"
                :show="!!recordId"
                :related-entities="bitacoraRelatedEntities"
            ></bitacora-logs>
        </div>
    </el-dialog>
</template>

<script>
import BitacoraLogs from './BitacoraLogs.vue'
import TabInteraccionesContacto from './TabInteraccionesContacto.vue'

export default {
    props: ['showDialog', 'recordId', 'empresaId', 'context', 'empresaEntity'],
    components: { BitacoraLogs, TabInteraccionesContacto },
    data() {
        return {
            activeTab: 'datos',
            loading: false,
            loading_submit: false,
            resource: 'comercial/contactos',
            errors: {},
            list_empresas: [],
            list_estados_interno: [],
            list_tipos_telefono: [],
            list_origenes: [],
            form: {},
            originalForm: '',
            isInitialized: false,
            lastSearch: '',
            searchTimeout: null,
            empresaEntityLocal: null,
            // Campos principales de contacto
            celularPrincipal: '',
            celularTipo: 'trabajo',
            celularPrincipalId: null,
            telefonoFijo: '',
            telefonoTipo: 'trabajo',
            telefonoFijoId: null,
            emailPrincipal: '',
            emailTipo: 'trabajo',
            emailPrincipalId: null,
            interaccionIds: []
        }
    },
    computed: {
        titleDialog() {
            return this.recordId ? 'Editar Contacto' : 'Nuevo Contacto'
        },
        currentEmpresa() {
            return this.empresaEntity || this.empresaEntityLocal
        },
        bitacoraRelatedEntities() {
            if (!this.interaccionIds.length) return []
            return [{
                type: 'contacto_interaccion',
                ids: this.interaccionIds,
                label: 'Interacción'
            }]
        }
    },
    created() {
        this.initForm()
    },
    watch: {
        showDialog: {
            immediate: true,
            handler(newVal) {
                if (newVal && !this.isInitialized) {
                    this.$nextTick(() => {
                        this.onOpen()
                    })
                }
                if (!newVal) {
                    // Reset tab when dialog closes
                    this.activeTab = 'datos'
                }
            }
        }
    },
    methods: {
        async onOpen() {
            if (this.isInitialized) return
            
            this.isInitialized = true
            this.loading = true
            this.activeTab = 'datos'

            try {
                // Cargar tables (listas)
                const tablesResponse = await this.$http.get(`/${this.resource}/tables`)
                
                this.list_empresas = tablesResponse.data.list_empresas || []
                this.list_estados_interno = tablesResponse.data.list_estados_interno || []
                this.list_tipos_telefono = tablesResponse.data.list_tipos_telefono || []
                this.list_origenes = tablesResponse.data.list_origenes || tablesResponse.data.origenes || []

                // Si se pasa empresaId desde el contexto, pre-seleccionar
                if (this.empresaId) {
                    this.form.empresa_id = this.empresaId
                    const empresa = this.list_empresas.find(e => e.id === this.empresaId)
                    if (empresa) {
                        this.empresaEntityLocal = empresa
                    }
                }

                // Si es edición, cargar datos
                if (this.recordId) {
                    await this.loadRecord()
                    await this.loadInteraccionIds()
                }

                // Guardar estado original
                this.$nextTick(() => {
                    this.originalForm = JSON.stringify(this.form)
                })
            } catch (error) {
                this.$message.error('Error al cargar datos iniciales')
            } finally {
                this.loading = false
            }
        },
        
        async loadRecord() {
            try {
                const response = await this.$http.get(`/${this.resource}/record/${this.recordId}`)
                const data = response.data.data || response.data

                this.form = {
                    id: data.id,
                    empresa_id: data.empresa_id,
                    nombres: data.nombres,
                    apellidos: data.apellidos,
                    dni: data.dni,
                    cargo: data.cargo,
                    origen: data.origen,
                    tipo_contacto: data.tipo_contacto,
                    fecha_cumpleanos: data.fecha_cumpleanos,
                    direccion: data.direccion,
                    referencia: data.referencia,
                    adicionales: data.adicionales,
                    estado: data.estado,
                    telefonos: [],
                    emails: []
                }

                // Procesar teléfonos.
                //
                // `mapTipo` mapea valores viejos al subset que aceptan los dropdowns
                // de "Celular principal" y "Teléfono fijo" (solo Trabajo / Personal).
                // SOLO se aplica a esos dos slots. NO debe aplicarse a los teléfonos
                // adicionales, cuyo dropdown soporta el catálogo completo
                // (Móvil/Fijo/Trabajo/Casa/Otro): si se aplicara, valores válidos como
                // 'otro' se reescribirían a 'personal' silenciosamente en cada edición.
                var mapTipo = function(tipo) {
                    var mapa = { 'movil': 'trabajo', 'fijo': 'trabajo', 'casa': 'personal', 'otro': 'personal' }
                    return mapa[tipo] || tipo || 'trabajo'
                }
                // Cargar teléfonos por SLOT explícito (no por es_principal/posición).
                // Esto evita el bug "todo se corre" cuando el usuario elimina el
                // celular principal: cada teléfono lleva su rol guardado en BD.
                //
                // Fallback para datos legacy sin slot (pre-migración 2026-05-28):
                // se infiere igual que antes (es_principal → celular_principal,
                // primer no-principal → fijo, resto → adicional).
                const telefonos = data.telefonos || []
                const celularPrincipal = telefonos.find(t => t.slot === 'celular_principal')
                    || telefonos.find(t => t.es_principal)  // fallback legacy
                if (celularPrincipal) {
                    this.celularPrincipal = celularPrincipal.telefono
                    this.celularTipo = mapTipo(celularPrincipal.tipo)
                    this.celularPrincipalId = celularPrincipal.id
                }

                const telefonoFijo = telefonos.find(t => t.slot === 'fijo')
                    || telefonos.filter(t => !t.es_principal && t.slot == null)[0]  // fallback legacy
                if (telefonoFijo) {
                    this.telefonoFijo = telefonoFijo.telefono
                    this.telefonoTipo = mapTipo(telefonoFijo.tipo)
                    this.telefonoFijoId = telefonoFijo.id
                }

                // Adicionales: todo lo que NO es celular_principal ni fijo.
                // NO se aplica mapTipo aquí — el dropdown de adicionales soporta el
                // catálogo completo (list_tipos_telefono) y mapear destruiría tipos
                // válidos como 'otro' en cada edición.
                this.form.telefonos = telefonos
                    .filter(t => {
                        if (t.id === (celularPrincipal && celularPrincipal.id)) return false
                        if (t.id === (telefonoFijo && telefonoFijo.id)) return false
                        return true
                    })
                    .map(t => ({
                        id: t.id,
                        telefono: t.telefono,
                        tipo: t.tipo,
                        slot: 'adicional'
                    }))

                // Procesar emails (mismo patrón que telefonos)
                const emails = data.emails || []
                const emailPrinc = emails.find(e => e.es_principal)
                if (emailPrinc) {
                    this.emailPrincipal = emailPrinc.email
                    this.emailTipo = emailPrinc.tipo || 'trabajo'
                    this.emailPrincipalId = emailPrinc.id  // FIX: conservar ID
                }
                // Emails adicionales
                this.form.emails = emails
                    .filter(e => !e.es_principal)
                    .map(e => ({
                        id: e.id,
                        email: e.email,
                        tipo: e.tipo || 'trabajo',
                        es_principal: false
                    }))

                // Agregar empresa a la lista si no está
                if (data.empresa_id && data.razon_social) {
                    const empresaExiste = this.list_empresas.find(e => e.id === data.empresa_id)
                    if (!empresaExiste) {
                        this.list_empresas.unshift({
                            id: data.empresa_id,
                            razon_social: data.razon_social
                        })
                    }
                    this.empresaEntityLocal = { 
                        id: data.empresa_id, 
                        razon_social: data.razon_social 
                    }
                }
            } catch (error) {
                this.$message.error('No se pudo cargar el registro')
                this.forceCloseDialog()
            }
        },

        handleCloseDialog() {
            if (this.hasUnsavedChanges()) {
                this.$confirm('¿Cerrar sin guardar? Se perderán los cambios.', 'Confirmar', {
                    confirmButtonText: 'Cerrar',
                    cancelButtonText: 'Cancelar',
                    type: 'warning'
                }).then(() => {
                    this.forceCloseDialog()
                }).catch(() => {})
            } else {
                this.forceCloseDialog()
            }
        },

        forceCloseDialog() {
            this.initForm()
            this.isInitialized = false
            this.activeTab = 'datos'
            this.$emit('update:showDialog', false)
            this.$emit('update:recordId', null)
        },

        hasUnsavedChanges() {
            return JSON.stringify(this.form) !== this.originalForm
        },

        async getEmpresas(query) {
            if (query === this.lastSearch) return

            if (!query || query.length < 3) {
                if (!this.form.empresa_id) {
                    this.list_empresas = []
                }
                this.lastSearch = query
                return
            }

            if (this.searchTimeout) {
                clearTimeout(this.searchTimeout)
            }

            this.searchTimeout = setTimeout(async () => {
                this.loading = true
                this.lastSearch = query

                try {
                    const response = await this.$http.get(`/comercial/empresas/search`, {
                        params: { search: query, limit: 50 }
                    })

                    this.list_empresas = response.data.data || response.data

                    // Preservar empresa pre-seleccionada
                    if (this.form.empresa_id && this.empresaEntityLocal) {
                        const exists = this.list_empresas.find(e => e.id === this.form.empresa_id)
                        if (!exists) {
                            this.list_empresas.unshift(this.empresaEntityLocal)
                        }
                    }
                } catch (error) {
                    this.list_empresas = []
                } finally {
                    this.loading = false
                }
            }, 400)
        },

        handleFocus() {
            if (!this.form.empresa_id && this.list_empresas.length === 0) {
                this.getEmpresas('')
            }
        },

        initForm() {
            this.errors = {}
            this.form = {
                id: null,
                empresa_id: (this.context === 'empresas' && this.empresaId) ? this.empresaId : null,
                nombres: null,
                apellidos: null,
                dni: null,
                cargo: null,
                origen: null,
                tipo_contacto: null,
                fecha_cumpleanos: null,
                direccion: null,
                referencia: null,
                adicionales: null,
                estado: 1,
                telefonos: [],
                emails: []
            }
            this.celularPrincipal = ''
            this.celularTipo = 'trabajo'
            this.celularPrincipalId = null
            this.telefonoFijo = ''
            this.telefonoTipo = 'trabajo'
            this.telefonoFijoId = null
            this.emailPrincipal = ''
            this.emailTipo = 'trabajo'
            this.emailPrincipalId = null
            this.originalForm = JSON.stringify(this.form)
        },

        async submit() {
            // Evitar doble envío (doble clic / Enter repetido) que generaba contactos duplicados.
            if (this.loading_submit) return

            // Construir arrays de teléfonos y emails
            const telefonos = []
            const emails = []

            // Cada teléfono se envía con su SLOT explícito. El backend deriva
            // es_principal del slot ('celular_principal' → es_principal=1).
            // Si un campo queda vacío, NO se envía → el backend lo soft-deletea
            // (NO se promueve ningún otro al slot vacante).
            if (this.celularPrincipal) {
                telefonos.push({
                    id: this.celularPrincipalId,
                    telefono: this.celularPrincipal,
                    tipo: this.celularTipo,
                    slot: 'celular_principal'
                })
            }

            if (this.telefonoFijo) {
                telefonos.push({
                    id: this.telefonoFijoId,
                    telefono: this.telefonoFijo,
                    tipo: this.telefonoTipo,
                    slot: 'fijo'
                })
            }

            this.form.telefonos.forEach(t => {
                if (t.telefono) {
                    telefonos.push({
                        id: t.id,
                        telefono: t.telefono,
                        tipo: t.tipo,
                        slot: 'adicional'
                    })
                }
            })

            // Agregar email principal
            // FIX: incluir ID si existe (edición) para que el backend haga UPDATE en vez de INSERT.
            if (this.emailPrincipal) {
                emails.push({
                    id: this.emailPrincipalId,
                    email: this.emailPrincipal,
                    tipo: this.emailTipo,
                    es_principal: true
                })
            }

            // Agregar emails adicionales
            this.form.emails.forEach(e => {
                if (e.email) {
                    emails.push({
                        id: e.id,
                        email: e.email,
                        tipo: e.tipo,
                        es_principal: false
                    })
                }
            })

            // Validar que al menos haya un teléfono o email
            if (telefonos.length === 0 && emails.length === 0) {
                this.$message.warning('Debe agregar al menos un teléfono o un email')
                return
            }

            // El celular principal y el teléfono fijo no pueden ser el mismo número.
            if (this.celularPrincipal && this.telefonoFijo
                && this.celularPrincipal.trim() === this.telefonoFijo.trim()) {
                this.$message.warning('El celular principal y el teléfono fijo no pueden ser el mismo número.')
                return
            }

            this.loading_submit = true
            
            const dataToSend = {
                ...this.form,
                telefonos,
                emails
            }
            
            try {
                const response = await this.$http.post(`/${this.resource}`, dataToSend)
                
                if (response.data.success) {
                    this.$message.success(response.data.message)
                    
                    if (this.context === 'empresas') {
                        this.$emit('contactSaved', this.empresaId || this.form.empresa_id)
                    } else {
                        this.$eventHub.$emit('reloadData')
                    }
                    
                    this.close()
                } else {
                    this.$message.error(response.data.message)
                }
            } catch (error) {
                if (error.response && error.response.status === 422) {
                    this.errors = error.response.data.errors || error.response.data
                    
                    if (error.response.data.message) {
                        this.$message.error(error.response.data.message)
                    }
                } else {
                    this.$message.error('Error al guardar el contacto')
                }
            } finally {
                this.loading_submit = false
            }
        },

        close() {
            this.$emit('update:showDialog', false)
            this.$emit('update:recordId', null)
            this.initForm()
            this.isInitialized = false
            this.activeTab = 'datos'
        },

        clickAddTelefono() {
            this.form.telefonos.push({
                id: null,
                telefono: '',
                tipo: 'movil',
                es_principal: false
            })
        },

        clickRemoveTelefono(index) {
            this.form.telefonos.splice(index, 1)
        },

        clickAddEmail() {
            this.form.emails.push({
                id: null,
                email: '',
                tipo: 'trabajo',
                es_principal: false
            })
        },

        clickRemoveEmail(index) {
            this.form.emails.splice(index, 1)
        },

        onInteraccionAdded() {
            this.loadInteraccionIds()
        },
        async loadInteraccionIds() {
            if (!this.recordId) return
            try {
                const response = await this.$http.get(`/${this.resource}/${this.recordId}/interacciones`)
                const items = response.data.data || response.data || []
                this.interaccionIds = items.map(i => i.id).filter(Boolean)
            } catch (e) {
                this.interaccionIds = []
            }
        }
    }
}
</script>

<style scoped>
/* Estilos para el dialog con bitácora */
.dialog-content-wrapper {
    display: flex;
    min-height: 400px;
}

.form-main-content {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    max-height: 70vh;
}

.input-with-select {
    display: flex;
    align-items: center;
}

.input-with-select .el-select {
    margin-right: 5px;
}

.input-with-select .el-input {
    flex: 1;
}

.additional-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    padding-bottom: 5px;
    border-bottom: 1px solid #ebeef5;
}

.section-label {
    font-weight: 500;
    color: #606266;
    font-size: 13px;
}

.additional-item {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
}

.mt-3 {
    margin-top: 15px;
}

.m-t-20 {
    margin-top: 20px;
}

.text-right {
    text-align: right;
}

.text-center {
    text-align: center;
}

.p-2 {
    padding: 8px;
}
</style>

<style>
/* Estilos globales para el dialog */
.dialog-with-bitacora .el-dialog__body {
    padding: 0 !important;
}
</style>
