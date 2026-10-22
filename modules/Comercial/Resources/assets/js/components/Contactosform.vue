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
                                <!-- Distribución en 3 columnas. Teléfonos y correos: tipo + valor (+ anexo) como un solo campo. -->
                                <div class="form-grid">
                                    <!-- ID Contacto (solo en edición) -->
                                    <div v-if="recordId" class="form-group">
                                        <label class="control-label">ID Contacto</label>
                                        <el-input :value="form.id" disabled></el-input>
                                    </div>

                                    <div :class="recordId ? 'span-2' : 'span-3'">
                                        <!-- Desde Empresas: nombre fijo -->
                                        <div class="form-group" v-if="context === 'empresas'">
                                            <label class="control-label">Empresa</label>
                                            <div style="display: flex; align-items: center; padding: 10px 15px; background: #f5f7fa; border: 1px solid #dcdfe6; border-radius: 4px;">
                                                <i class="fa fa-building" style="color: #606266; margin-right: 10px;"></i>
                                                <span style="color: #303133; font-weight: 500;">
                                                    {{ currentEmpresa ? nombreEmpresa(currentEmpresa) : 'Cargando...' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div v-else :class="{'has-danger': errors.empresa_id}" class="form-group">
                                            <label class="control-label label-con-accion">
                                                <span>Empresa <span class="text-danger">*</span></span>
                                                <el-button
                                                    v-if="!empresaId && !recordId"
                                                    type="text"
                                                    size="mini"
                                                    style="padding: 0; margin-left: 10px;"
                                                    @click="abrirNuevaEmpresa">
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
                                                :disabled="!!empresaId || !!recordId"
                                                style="width: 100%;"
                                                @focus="handleFocus"
                                            >
                                                <el-option
                                                    v-for="option in list_empresas"
                                                    :key="option.id"
                                                    :label="nombreEmpresa(option)"
                                                    :value="option.id">
                                                    <span>{{ nombreEmpresa(option) }}</span>
                                                    <span v-if="nombreEmpresa(option) !== option.razon_social" class="opcion-razon-social">{{ option.razon_social }}</span>
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

                                    <div class="form-group" :class="{'has-danger': errors.nombres}">
                                        <label class="control-label">Nombres <span class="text-danger">*</span></label>
                                        <el-input v-model="form.nombres"></el-input>
                                        <small class="form-control-feedback" v-if="errors.nombres" v-text="errors.nombres[0]"></small>
                                    </div>
                                    <div class="form-group" :class="{'has-danger': errors.apellidos}">
                                        <label class="control-label">Apellidos</label>
                                        <el-input v-model="form.apellidos" placeholder="Apellidos"></el-input>
                                        <small class="form-control-feedback" v-if="errors.apellidos" v-text="errors.apellidos[0]"></small>
                                    </div>
                                    <div class="form-group" :class="{'has-danger': errors.cargo}">
                                        <label class="control-label">Cargo</label>
                                        <el-input v-model="form.cargo" placeholder="Cargo"></el-input>
                                        <small class="form-control-feedback" v-if="errors.cargo" v-text="errors.cargo[0]"></small>
                                    </div>

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
                                    <!-- Detalle de contacto: baja por las filas de origen, teléfonos y email -->
                                    <div class="form-group celda-alta row-span-3" :class="{'has-danger': errors.tipo_contacto}">
                                        <label class="control-label">Detalle de contacto</label>
                                        <el-input type="textarea" v-model="form.tipo_contacto" resize="vertical" placeholder="Escribir detalle de contacto..."></el-input>
                                        <small class="form-control-feedback" v-if="errors.tipo_contacto" v-text="errors.tipo_contacto[0]"></small>
                                    </div>

                                    <div class="form-group">
                                        <label class="control-label">Celular principal</label>
                                        <div class="campo-grupo" :class="{ 'campo-grupo--error': esTelefonoDuplicado(celularPrincipal) }">
                                            <el-select v-model="celularTipo" class="campo-grupo__tipo">
                                                <el-option label="Trab." value="trabajo">Trabajo</el-option>
                                                <el-option label="Pers." value="personal">Personal</el-option>
                                            </el-select>
                                            <el-input :value="celularPrincipal" @input="v => celularPrincipal = formatearTelefono(v)" placeholder="Celular" class="campo-grupo__valor"></el-input>
                                        </div>
                                        <small v-if="esTelefonoDuplicado(celularPrincipal)" class="texto-duplicado">Ya registrado en {{ duplicado.contacto_nombre }}<template v-if="duplicado.empresa_nombre"> · {{ duplicado.empresa_nombre }}</template></small>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label">Teléfono fijo</label>
                                        <div class="campo-grupo" :class="{ 'campo-grupo--error': esTelefonoDuplicado(telefonoFijo) }">
                                            <el-select v-model="telefonoTipo" class="campo-grupo__tipo">
                                                <el-option label="Trab." value="trabajo">Trabajo</el-option>
                                                <el-option label="Pers." value="personal">Personal</el-option>
                                            </el-select>
                                            <el-input :value="telefonoFijo" @input="v => telefonoFijo = formatearTelefono(v)" placeholder="Teléfono" class="campo-grupo__valor"></el-input>
                                            <span class="campo-grupo__texto">Anexo</span>
                                            <el-input :value="telefonoFijoAnexo" @input="v => telefonoFijoAnexo = soloDigitos(v)" class="campo-grupo__anexo"></el-input>
                                        </div>
                                        <small v-if="esTelefonoDuplicado(telefonoFijo)" class="texto-duplicado">Ya registrado en {{ duplicado.contacto_nombre }}<template v-if="duplicado.empresa_nombre"> · {{ duplicado.empresa_nombre }}</template></small>
                                    </div>

                                    <div class="form-group span-2">
                                        <label class="control-label">Email principal</label>
                                        <div class="campo-grupo" :class="{ 'campo-grupo--error': esCorreoDuplicado(emailPrincipal) }">
                                            <el-select v-model="emailTipo" class="campo-grupo__tipo">
                                                <el-option label="Trab." value="trabajo">Trabajo</el-option>
                                                <el-option label="Pers." value="personal">Personal</el-option>
                                            </el-select>
                                            <el-input v-model="emailPrincipal" placeholder="correo@empresa.com" class="campo-grupo__valor"></el-input>
                                        </div>
                                        <small v-if="esCorreoDuplicado(emailPrincipal)" class="texto-duplicado">Ya registrado en {{ duplicado.contacto_nombre }}<template v-if="duplicado.empresa_nombre"> · {{ duplicado.empresa_nombre }}</template></small>
                                    </div>

                                    <div class="form-group" :class="{'has-danger': errors.direccion}">
                                        <label class="control-label">Dirección oficina del contacto</label>
                                        <el-input type="textarea" v-model="form.direccion" :rows="3" maxlength="255" resize="vertical" placeholder="Dirección"></el-input>
                                        <small class="form-control-feedback" v-if="errors.direccion" v-text="errors.direccion[0]"></small>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label">Referencia oficina del contacto</label>
                                        <el-input type="textarea" v-model="form.referencia" :rows="3" maxlength="255" resize="vertical" placeholder="Referencia"></el-input>
                                    </div>
                                    <div class="form-group">
                                        <label class="control-label">Adicionales</label>
                                        <el-input type="textarea" v-model="form.adicionales" :rows="3" resize="vertical" placeholder="Notas adicionales"></el-input>
                                    </div>
                                </div>

                                <!-- Teléfonos adicionales y Emails adicionales -->
                                <div class="form-grid form-grid--2 seccion-adicionales">
                                    <div>
                                        <div class="additional-section">
                                            <span class="section-label">Teléfonos adicionales</span>
                                            <el-button type="primary" size="mini" @click="clickAddTelefono">Agregar</el-button>
                                        </div>
                                        <p v-if="form.telefonos.length === 0" class="texto-vacio">Sin teléfonos adicionales</p>
                                        <div v-for="(row, index) in form.telefonos" :key="'tel-' + index" class="additional-item">
                                            <div class="campo-grupo campo-grupo--small" :class="{ 'campo-grupo--error': esTelefonoDuplicado(row.telefono) }">
                                                <el-select v-model="row.tipo" size="small" class="campo-grupo__tipo">
                                                    <el-option v-for="option in list_tipos_telefono" :key="option.id" :label="option.name" :value="option.id"></el-option>
                                                </el-select>
                                                <el-input :value="row.telefono" @input="v => row.telefono = formatearTelefono(v)" placeholder="Teléfono" size="small" class="campo-grupo__valor"></el-input>
                                                <template v-if="row.tipo === 'fijo'">
                                                    <span class="campo-grupo__texto">Anexo</span>
                                                    <el-input :value="row.anexo" @input="v => row.anexo = soloDigitos(v)" size="small" class="campo-grupo__anexo"></el-input>
                                                </template>
                                            </div>
                                            <el-button type="danger" icon="el-icon-delete" size="mini" circle @click="clickRemoveTelefono(index)"></el-button>
                                            <small v-if="esTelefonoDuplicado(row.telefono)" class="texto-duplicado">Ya registrado en {{ duplicado.contacto_nombre }}<template v-if="duplicado.empresa_nombre"> · {{ duplicado.empresa_nombre }}</template></small>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="additional-section">
                                            <span class="section-label">Emails adicionales</span>
                                            <el-button type="primary" size="mini" @click="clickAddEmail">Agregar</el-button>
                                        </div>
                                        <p v-if="form.emails.length === 0" class="texto-vacio">Sin emails adicionales</p>
                                        <div v-for="(row, index) in form.emails" :key="'email-' + index" class="additional-item">
                                            <div class="campo-grupo campo-grupo--small" :class="{ 'campo-grupo--error': esCorreoDuplicado(row.email) }">
                                                <el-select v-model="row.tipo" size="small" class="campo-grupo__tipo">
                                                    <el-option label="Trab." value="trabajo">Trabajo</el-option>
                                                    <el-option label="Pers." value="personal">Personal</el-option>
                                                </el-select>
                                                <el-input v-model="row.email" placeholder="correo@empresa.com" size="small" class="campo-grupo__valor"></el-input>
                                            </div>
                                            <el-button type="danger" icon="el-icon-delete" size="mini" circle @click="clickRemoveEmail(index)"></el-button>
                                            <small v-if="esCorreoDuplicado(row.email)" class="texto-duplicado">Ya registrado en {{ duplicado.contacto_nombre }}<template v-if="duplicado.empresa_nombre"> · {{ duplicado.empresa_nombre }}</template></small>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="form-actions form-acciones-fijas text-right">
                                <el-button @click.prevent="handleCloseDialog()">Cancelar</el-button>
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

        <!-- "+ Nuevo" junto a Empresa: el mismo formulario de Empresas; al guardar queda seleccionada. -->
        <empresas-form
            v-if="mostrarNuevaEmpresa"
            :showDialog.sync="mostrarNuevaEmpresa"
            :recordId="null"
            :external="true"
        ></empresas-form>
    </el-dialog>
</template>

<script>
import BitacoraLogs from './BitacoraLogs.vue'
import TabInteraccionesContacto from './TabInteraccionesContacto.vue'
import EmpresasForm from './Empresasform.vue'
import { formatearTelefono, separarAnexo, soloDigitos } from '../helpers/telefono'
import { nombreEmpresa } from '../helpers/texto'

export default {
    props: ['showDialog', 'recordId', 'empresaId', 'context', 'empresaEntity'],
    components: { BitacoraLogs, TabInteraccionesContacto, EmpresasForm },
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
            duplicado: null,
            mostrarNuevaEmpresa: false,
            esperandoEmpresaNueva: false,
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
            telefonoFijoAnexo: '',
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
        // Empresasform (external) avisa por este evento el id de la empresa que acaba de crear.
        this.$eventHub.$on('reloadDataPersons', this.empresaCreada)
    },
    beforeDestroy() {
        this.$eventHub.$off('reloadDataPersons', this.empresaCreada)
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
        },
        mostrarNuevaEmpresa(abierto) {
            // Si se cierra sin guardar, deja de esperar la empresa nueva.
            if (!abierto) this.esperandoEmpresaNueva = false
        }
    },
    methods: {
        formatearTelefono,
        soloDigitos,
        nombreEmpresa,
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
                    this.originalForm = this.fotoFormulario()
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
                    this.celularPrincipal = formatearTelefono(celularPrincipal.telefono)
                    this.celularTipo = mapTipo(celularPrincipal.tipo)
                    this.celularPrincipalId = celularPrincipal.id
                }

                const telefonoFijo = telefonos.find(t => t.slot === 'fijo')
                    || telefonos.filter(t => !t.es_principal && t.slot == null)[0]  // fallback legacy
                if (telefonoFijo) {
                    const fijo = separarAnexo(telefonoFijo.telefono)
                    this.telefonoFijo = formatearTelefono(fijo.numero)
                    this.telefonoFijoAnexo = fijo.anexo
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
                    .map(t => {
                        const partes = separarAnexo(t.telefono)
                        return {
                            id: t.id,
                            telefono: formatearTelefono(partes.numero),
                            anexo: partes.anexo,
                            tipo: t.tipo,
                            slot: 'adicional'
                        }
                    })

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
                            razon_social: data.razon_social,
                            nombre_comercial: data.nombre_comercial
                        })
                    }
                    this.empresaEntityLocal = { 
                        id: data.empresa_id, 
                        razon_social: data.razon_social,
                        nombre_comercial: data.nombre_comercial
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

        fotoFormulario() {
            return JSON.stringify({
                form: this.form,
                celular: [this.celularTipo, this.celularPrincipal],
                fijo: [this.telefonoTipo, this.telefonoFijo, this.telefonoFijoAnexo],
                email: [this.emailTipo, this.emailPrincipal]
            })
        },

        // Dato repetido: marca la casilla, explica quién lo tiene y ofrece abrir ese contacto.
        esTelefonoDuplicado(valor) {
            if (!this.duplicado || this.duplicado.campo !== 'telefono' || !valor) return false
            const buscado = String(this.duplicado.valor).replace(/\D/g, '')
            const digitos = String(valor).replace(/\D/g, '')
            return buscado.length >= 6 && digitos.slice(-buscado.length) === buscado
        },

        esCorreoDuplicado(valor) {
            return !!(this.duplicado && this.duplicado.campo === 'email' && valor
                && String(valor).trim().toLowerCase() === String(this.duplicado.valor).toLowerCase())
        },

        mostrarDuplicado(mensaje, duplicado) {
            this.duplicado = duplicado
            this.$confirm(mensaje, 'Dato ya registrado', {
                confirmButtonText: 'Ver contacto',
                cancelButtonText: 'Entendido',
                type: 'warning',
                distinguishCancelAndClose: true,
                closeOnClickModal: false
            }).then(() => this.verContactoDuplicado(duplicado)).catch(() => {})
        },

        // Abre ese contacto en la pantalla de Contactos; antes avisa si se perderían datos.
        verContactoDuplicado(duplicado) {
            const abrir = () => {
                window.location.href = '/comercial/contactos/listado?ver=' + duplicado.contacto_id
            }
            if (!this.hasUnsavedChanges()) {
                abrir()
                return
            }
            this.$confirm(
                'Tienes datos sin guardar en este formulario. Si abres el otro contacto, se perderán.',
                'Datos sin guardar',
                { confirmButtonText: 'Abrir de todos modos', cancelButtonText: 'Seguir editando', type: 'warning' }
            ).then(abrir).catch(() => {})
        },

        hasUnsavedChanges() {
            return this.fotoFormulario() !== this.originalForm
        },

        abrirNuevaEmpresa() {
            this.esperandoEmpresaNueva = true
            this.mostrarNuevaEmpresa = true
        },

        // Empresa recién creada desde "+ Nuevo": se agrega a la lista y queda seleccionada.
        async empresaCreada(empresaId) {
            if (!this.esperandoEmpresaNueva || !empresaId) return
            this.esperandoEmpresaNueva = false
            try {
                const response = await this.$http.get(`/comercial/empresas/record/${empresaId}`)
                const empresa = response.data.data || response.data
                this.list_empresas = [
                    { id: empresa.id, razon_social: empresa.razon_social, nombre_comercial: empresa.nombre_comercial },
                    ...this.list_empresas.filter(e => e.id !== empresa.id)
                ]
                this.form.empresa_id = empresa.id
                this.$delete(this.errors, 'empresa_id')
            } catch (e) {
                this.$message.warning('La empresa se creó. Búscala en la lista para seleccionarla.')
            }
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
            this.telefonoFijoAnexo = ''
            this.emailPrincipal = ''
            this.emailTipo = 'trabajo'
            this.emailPrincipalId = null
            this.originalForm = this.fotoFormulario()
        },

        async submit() {
            // Evitar doble envío (doble clic / Enter repetido) que generaba contactos duplicados.
            if (this.loading_submit) return
            this.duplicado = null

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
                    anexo: this.telefonoFijoAnexo,
                    tipo: this.telefonoTipo,
                    slot: 'fijo'
                })
            }

            this.form.telefonos.forEach(t => {
                if (t.telefono) {
                    telefonos.push({
                        id: t.id,
                        telefono: t.telefono,
                        anexo: t.tipo === 'fijo' ? t.anexo : null,
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
                && this.celularPrincipal.replace(/\D/g, '') === this.telefonoFijo.replace(/\D/g, '')) {
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
                if (error.response && error.response.status === 422 && error.response.data.duplicado) {
                    this.mostrarDuplicado(error.response.data.message, error.response.data.duplicado)
                } else if (error.response && error.response.status === 422) {
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
                anexo: '',
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

/* Distribución del formulario en 3 columnas */
.form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0 16px;
}
.form-grid--2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.span-2 {
    grid-column: span 2;
}
.span-3 {
    grid-column: 1 / -1;
}
.row-span-3 {
    grid-row: span 3;
}
.label-con-accion {
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
}
.celda-alta {
    display: flex;
    flex-direction: column;
}
.celda-alta .el-textarea {
    flex: 1;
}
.celda-alta >>> .el-textarea__inner {
    min-height: 100% !important;
}
/* Cancelar / Guardar siempre visibles al pie del área con scroll */
.form-acciones-fijas {
    position: sticky;
    bottom: -20px;
    z-index: 3;
    margin: 16px -20px -20px;
    padding: 12px 20px;
    background: #fff;
    border-top: 1px solid #ebeef5;
}
/* El contenedor de pestañas trae overflow: hidden, que anula el sticky del pie */
.form-main-content >>> .el-tabs__content {
    overflow: visible;
}
.campo-grupo--error {
    border-color: #f56c6c;
}
.texto-duplicado {
    display: block;
    margin-top: 4px;
    color: #f56c6c;
    font-size: 12px;
}
.additional-item {
    flex-wrap: wrap;
}
.additional-item .texto-duplicado {
    flex-basis: 100%;
    margin-top: 2px;
}
.opcion-razon-social {
    float: right;
    margin-left: 12px;
    color: #909399;
    font-size: 12px;
}
/* Campos bloqueados (ID, empresa al editar): texto legible, no el gris tenue por defecto */
.form-grid >>> .el-input.is-disabled .el-input__inner {
    color: #303133;
    -webkit-text-fill-color: #303133;
    background-color: #f5f7fa;
    cursor: not-allowed;
}
@media (max-width: 768px) {
    .form-grid,
    .form-grid--2 {
        grid-template-columns: minmax(0, 1fr);
    }
    .span-2,
    .row-span-3 {
        grid-column: auto;
        grid-row: auto;
    }
}

/* Tipo + valor (+ "Anexo" + número de anexo) como un solo campo */
.campo-grupo {
    display: flex;
    flex: 1;
    min-width: 0;
    align-items: stretch;
    border: 1px solid #cac8f1;
    border-radius: 4px;
    overflow: hidden;
    background: #fff;
}
.campo-grupo:focus-within {
    border-color: #409eff;
}
.campo-grupo >>> .el-input__inner {
    border: none;
    border-radius: 0;
    padding: 0 8px;
}
.campo-grupo__tipo >>> .el-input__inner {
    padding: 0 20px 0 6px;
}
.campo-grupo__anexo >>> .el-input__inner {
    padding: 0 4px;
    text-align: center;
}
.campo-grupo__tipo >>> .el-input__suffix {
    right: 2px;
}
.campo-grupo__tipo {
    flex: 0 0 62px;
    border-right: 1px solid #cac8f1;
}
.campo-grupo--small .campo-grupo__tipo {
    flex-basis: 92px;
}
.campo-grupo__valor {
    flex: 1;
    min-width: 0;
}
.campo-grupo__texto {
    display: flex;
    align-items: center;
    padding: 0 6px;
    color: #909399;
    background: #f5f7fa;
    border-left: 1px solid #cac8f1;
    border-right: 1px solid #cac8f1;
    white-space: nowrap;
}
.campo-grupo__anexo {
    flex: 0 0 40px;
}
.campo-grupo--small .campo-grupo__texto {
    font-size: 12px;
}

.seccion-adicionales {
    margin-top: 6px;
    padding-top: 14px;
    border-top: 1px solid #ebeef5;
}
.texto-vacio {
    color: #909399;
    font-size: 13px;
    margin: 4px 0 0;
}

.additional-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.section-label {
    font-weight: 500;
    color: #606266;
    font-size: 13px;
}

.additional-item {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 8px;
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
