<template>
    <el-dialog :close-on-click-modal="false"
               :title="titleDialog"
               :visible="showDialog"
               :append-to-body="true"
               @open="create"
               @opened="opened"
               :close-on-press-escape="false"
               @close="handleCloseDialog"
               width="900px"
               custom-class="dialog-with-bitacora">
        
        <!-- Contenedor principal con flexbox -->
        <div class="dialog-content-wrapper">
            <!-- Formulario principal -->
            <div class="form-main-content">
                <form autocomplete="off"
                      @submit.prevent="submit">
                    <div class="form-body">
                        <el-tabs v-model="activeName">
                            <el-tab-pane class
                                         name="first">
                                <span slot="label">{{ titleTabDialog }}</span>
                                
                                <!-- ID solo lectura (solo en edición) -->
                                <div class="row" v-if="recordId">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="control-label">ID (solo lectura)</label>
                                            <el-input :value="form.id" disabled></el-input>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div :class="{'has-danger': errors.ruc}"
                                             class="form-group">
                                            <label class="control-label">RUC <span class="text-danger">*</span></label>

                                            <div v-if="api_service_token != false">
                                                <x-input-service v-model="form.ruc"
                                                                 :identity_document_type_id="form.identity_document_type_id"
                                                                 @search="searchNumber"></x-input-service>
                                            </div>
                                            <div v-else>
                                                <el-input v-model="form.ruc"
                                                          :maxlength="maxLength"
                                                          dusk="ruc">
                                                    <template
                                                        v-if="form.identity_document_type_id === '6' || form.identity_document_type_id === '1'">
                                                        <el-button slot="append"
                                                                   :loading="loading_search"
                                                                   icon="el-icon-search"
                                                                   type="primary"
                                                                   @click.prevent="searchCustomer">
                                                            <template v-if="form.identity_document_type_id === '6'">
                                                                SUNAT
                                                            </template>
                                                            <template v-if="form.identity_document_type_id === '1'">
                                                                RENIEC
                                                            </template>
                                                        </el-button>
                                                    </template>
                                                </el-input>
                                            </div>

                                            <small v-if="errors.ruc"
                                                   class="form-control-feedback"
                                                   v-text="errors.ruc[0]"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div :class="{'has-danger': errors.razon_social}"
                                             class="form-group">
                                            <label class="control-label">Razón Social <span class="text-danger">*</span></label>
                                            <el-input v-model="form.razon_social"
                                                      dusk="razon_social"></el-input>
                                            <small v-if="errors.razon_social"
                                                   class="form-control-feedback"
                                                   v-text="errors.razon_social[0]"></small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div :class="{'has-danger': errors.nombre_comercial}"
                                             class="form-group">
                                            <label class="control-label">Nombre comercial</label>
                                            <el-input v-model="form.nombre_comercial"
                                                      dusk="nombre_comercial"></el-input>
                                            <small v-if="errors.nombre_comercial"
                                                   class="form-control-feedback"
                                                   v-text="errors.nombre_comercial[0]"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div :class="{'has-danger': errors.rubro}"
                                           class="form-group">
                                        <label class="control-label">Rubro</label>
                                        <el-input v-model="form.rubro"
                                                  dusk="rubro"></el-input>
                                        <small v-if="errors.rubro"
                                               class="form-control-feedback"
                                               v-text="errors.rubro[0]"></small>
                                      </div>
                                    </div>
                                </div>

                                <div class="row">
                                  <div class="col-md-4">
                                    <div :class="{'has-danger': errors.tipo_empresa}"
                                         class="form-group">
                                      <label class="control-label">
                                        Tipo de Empresa
                                      </label>
                                      <el-select v-model="form.tipo_empresa"
                                                 clearable
                                                 filterable>
                                        <el-option v-for="option in tipos_de_empresas"
                                                   :key="option.id"
                                                   :label="option.id"
                                                   :value="option.id"></el-option>
                                      </el-select>
                                      <small v-if="errors.tipo_empresa"
                                             class="form-control-feedback"
                                             v-text="errors.tipo_empresa[0]"></small>
                                    </div>
                                  </div>

                                  <div class="col-md-4">
                                    <div :class="{'has-danger': errors.n_trabajadores}"
                                         class="form-group">
                                      <label class="control-label">Número de Trabajadores</label>
                                      <el-input v-model="form.n_trabajadores"
                                                dusk="n_trabajadores"></el-input>
                                      <small v-if="errors.n_trabajadores"
                                             class="form-control-feedback"
                                             v-text="errors.n_trabajadores[0]"></small>
                                    </div>
                                  </div>

                                  <div class="col-md-4">
                                    <div :class="{'has-danger': errors.estado_contribuyente}"
                                         class="form-group">
                                      <label class="control-label">
                                        Estado Contribuyente (SUNAT)
                                      </label>
                                      <el-select v-model="form.estado_contribuyente"
                                                 clearable
                                                 filterable>
                                        <el-option v-for="option in list_estados_contribuyentes"
                                                   :key="option.id"
                                                   :label="option.id"
                                                   :value="option.id"></el-option>
                                      </el-select>
                                      <small v-if="errors.estado_contribuyente"
                                             class="form-control-feedback"
                                             v-text="errors.estado_contribuyente[0]"></small>
                                    </div>
                                  </div>
                                </div>

                                <div class="row">
                                  <div class="col-md-4">
                                    <div :class="{'has-danger': errors.condicion_contribuyente}"
                                         class="form-group">
                                      <label class="control-label">
                                        Condición Contribuyente (SUNAT)
                                      </label>
                                      <el-select v-model="form.condicion_contribuyente"
                                                 clearable
                                                 filterable>
                                        <el-option v-for="option in list_condiciones_contribuyentes"
                                                   :key="option.id"
                                                   :label="option.id"
                                                   :value="option.id"></el-option>
                                      </el-select>
                                      <small v-if="errors.condicion_contribuyente"
                                             class="form-control-feedback"
                                             v-text="errors.condicion_contribuyente[0]"></small>
                                    </div>
                                  </div>
                                </div>

                            </el-tab-pane>

                            <el-tab-pane class
                                         name="second">
                                <span slot="label">Dirección</span>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div :class="{'has-danger': errors.location_id}"
                                             class="form-group">
                                            <label class="control-label">
                                                Ubigeo
                                                <span v-if="form.country_id === 'PE'" class="text-danger">*</span>
                                            </label>
                                            <el-cascader v-model="form.location_id"
                                                         :clearable="true"
                                                         :options="locations"
                                                         filterable
                                                         :disabled="form.country_id !== 'PE'"
                                                         :filter-method="customFilterMethod"></el-cascader>
                                            <small v-if="errors.location_id"
                                                   class="form-control-feedback"
                                                   v-text="errors.location_id[0]"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div :class="{'has-danger': errors.direccion}"
                                             class="form-group">
                                            <label class="control-label">Dirección</label>
                                            <el-input type="textarea"
                                                      v-model="form.direccion"
                                                      :rows="2"
                                                      resize="vertical"
                                                      dusk="direccion"></el-input>
                                            <small v-if="errors.direccion"
                                                   class="form-control-feedback"
                                                   v-text="errors.direccion[0]"></small>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                      <div :class="{'has-danger': errors.referencia}"
                                           class="form-group">
                                        <label class="control-label">Referencia</label>
                                        <el-input v-model="form.referencia"></el-input>
                                        <small v-if="errors.referencia"
                                               class="form-control-feedback"
                                               v-text="errors.referencia[0]"></small>
                                      </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                      <h5 class="section-title">Contacto y Web</h5>
                                    </div>
                                    <div class="col-md-6">
                                        <div :class="{'has-danger': errors.telefono}"
                                             class="form-group">
                                            <label class="control-label">Teléfono</label>
                                            <el-input v-model="form.telefono"
                                                      dusk="telefono"></el-input>
                                            <small v-if="errors.telefono"
                                                   class="form-control-feedback"
                                                   v-text="errors.telefono[0]"></small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                      <div :class="{'has-danger': errors.celular}"
                                           class="form-group">
                                        <label class="control-label">Celular</label>
                                        <el-input v-model="form.celular"
                                                  dusk="celular"></el-input>
                                        <small v-if="errors.celular"
                                               class="form-control-feedback"
                                               v-text="errors.celular[0]"></small>
                                      </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div :class="{'has-danger': errors.email}"
                                             class="form-group">
                                            <label class="control-label">Correo electrónico</label>
                                            <el-input v-model="form.email"
                                                      dusk="email"></el-input>
                                            <small v-if="errors.email"
                                                   class="form-control-feedback"
                                                   v-text="errors.email[0]"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                      <div :class="{'has-danger': errors.website}"
                                           class="form-group">
                                        <label class="control-label">Página Web</label>
                                        <el-input v-model="form.website" placeholder="https://"></el-input>
                                        <small v-if="errors.website"
                                               class="form-control-feedback"
                                               v-text="errors.website[0]"></small>
                                      </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div :class="{'has-danger': errors.origen}"
                                            class="form-group">
                                            <label class="control-label">Origen</label>
                                            <el-select v-model="form.origen"
                                                    clearable
                                                    filterable
                                                    placeholder="Seleccione origen">
                                            <el-option v-for="option in origenes"
                                                        :key="option.id"
                                                        :label="option.name"
                                                        :value="option.id"></el-option>
                                            </el-select>
                                            <small v-if="errors.origen"
                                                class="form-control-feedback"
                                                v-text="errors.origen[0]"></small>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                      <div class="form-group" :class="{'has-danger': errors.aniversario}">
                                        <label class="control-label">Fecha Aniversario</label>
                                        <el-date-picker v-model="form.aniversario" type="date" value-format="yyyy-MM-dd" :clearable="true" style="width: 100%;"></el-date-picker>
                                        <small class="form-control-feedback" v-if="errors.aniversario" v-text="errors.aniversario[0]"></small>
                                      </div>
                                    </div>
                                </div>
                            </el-tab-pane>

                            <el-tab-pane class
                                         name="third">
                                <span slot="label">Otros Datos</span>
                                <div class="row ">
                                  <div class="col-12">
                                    <h5 class="section-title">Políticas, procesos y perfil</h5>
                                  </div>

                                  <div class="col-md-6">
                                    <div :class="{'has-danger': errors.politica_pago}"
                                         class="form-group">
                                      <label class="control-label">Política de Pago</label>
                                      <el-input type="textarea" v-model="form.politica_pago" :rows="2" resize="vertical" placeholder="Crédito 15/30 días, etc."></el-input>
                                      <small v-if="errors.politica_pago"
                                             class="form-control-feedback"
                                             v-text="errors.politica_pago[0]"></small>
                                    </div>
                                  </div>

                                  <div class="col-md-6">
                                    <div :class="{'has-danger': errors.procedimiento_especial}"
                                         class="form-group">
                                      <label class="control-label">Procedimiento Especial</label>
                                      <el-input type="textarea" v-model="form.procedimiento_especial" :rows="2" resize="vertical" placeholder="Requisitos de ingreso, entregas, etc."></el-input>
                                      <small v-if="errors.procedimiento_especial"
                                             class="form-control-feedback"
                                             v-text="errors.procedimiento_especial[0]"></small>
                                    </div>
                                  </div>

                                  <div class="col-md-6">
                                    <div :class="{'has-danger': errors.frecuencia_compra}"
                                         class="form-group">
                                      <label class="control-label">Frecuencia de Compra</label>
                                      <el-input type="textarea" v-model="form.frecuencia_compra" :rows="2" resize="vertical" placeholder="Semanal/Mensual/Trimestral"></el-input>
                                      <small v-if="errors.frecuencia_compra"
                                             class="form-control-feedback"
                                             v-text="errors.frecuencia_compra[0]"></small>
                                    </div>
                                  </div>

                                  <div class="col-md-6">
                                    <div :class="{'has-danger': errors.adicionales}"
                                         class="form-group">
                                      <label class="control-label">Adicionales</label>
                                      <el-input type="textarea" v-model="form.adicionales" :rows="2" resize="vertical" placeholder="Notas y observaciones"></el-input>
                                      <small v-if="errors.adicionales"
                                             class="form-control-feedback"
                                             v-text="errors.adicionales[0]"></small>
                                    </div>
                                  </div>

                                  <div class="col-md-6">
                                    <div :class="{'has-danger': errors.trabaja_con_proveedores}"
                                         class="form-group">
                                      <label class="control-label">Trabaja con Proveedores</label>
                                      <el-checkbox v-model="form.trabaja_con_proveedores">Sí</el-checkbox>
                                      <small v-if="errors.trabaja_con_proveedores"
                                             class="form-control-feedback"
                                             v-text="errors.trabaja_con_proveedores[0]"></small>
                                    </div>
                                  </div>
                                </div>
                            </el-tab-pane>
                        </el-tabs>
                    </div>
                    <div class="form-actions form-acciones-fijas text-right">
                        <el-button class="second-buton" @click.prevent="handleCloseDialog()">Cancelar</el-button>
                        <el-button :loading="loading_submit"
                                   native-type="submit"
                                   type="primary">Guardar
                        </el-button>
                    </div>
                </form>
            </div>

            <!-- Panel de Bitácora (solo en modo edición) -->
            <bitacora-logs
                v-if="recordId"
                entity-type="empresa"
                :entity-id="recordId"
                :show="!!recordId"
            ></bitacora-logs>
        </div>
        
        <consigned-form
            :recordId="recordId"
            :showDialog.sync="showDialogConsignedForm"
        ></consigned-form>
    </el-dialog>
</template>

<script>

import {mapActions, mapState} from "vuex/dist/vuex.mjs";
import {serviceNumber} from '../../../../../../resources/js/mixins/functions'
import ConsignedForm from './partials/consigned.vue'
import BitacoraLogs from './BitacoraLogs.vue'

export default {
    mixins: [serviceNumber],
    props: [
        'showDialog',
        'type',
        'recordId',
        'external',
        'document_type_id',
        'input_person',
        'parentId',
        'is_dispatch'
    ],
    components: {ConsignedForm, BitacoraLogs},
    data() {
        return {
            parent: null,
            loading_submit: false,
            titleDialog: null,
            titleTabDialog: null,
            resource: 'comercial/empresas',
            showDialogConsignedForm: false,
            errors: {},
            api_service_token: false,
            form: {
                optional_email: []
            },
            temp_optional_email: [],
            temp_email: null,
            countries: [],
            zones: [],
            all_departments: [],
            all_provinces: [],
            all_districts: [],
            provinces: [],
            districts: [],
            locations: [],
            consigneds: [],
            tipos_de_empresas: [],
            identity_document_types: [],
            activeName: 'first',
            config_regex_password_user: false,
            list_estados_contribuyentes: [],
            list_condiciones_contribuyentes: [],
            list_estados_interno: [],
            origenes: [],
            originalForm: ''
        }
    },
    async created() {

        // Inicializar título de inmediato (en caso que @open del diálogo no dispare,
        // p. ej. cuando el padre usa v-if + showDialog=true).
        this.initTitle()

        this.loadConfiguration()
        await this.initForm()
        await this.getConsigneds()
        await this.$http.get(`/${this.resource}/tables`)
            .then(response => {
                this.api_service_token = response.data.api_service_token

                this.countries = response.data.countries
                this.all_departments = response.data.departments;
                this.all_provinces = response.data.provinces;
                this.all_districts = response.data.districts;
                this.identity_document_types = response.data.identity_document_types;
                this.locations = response.data.locations;
                this.tipos_de_empresas = response.data.tipos_de_empresas;
                this.list_condiciones_contribuyentes = response.data.list_condiciones_contribuyentes;
                this.list_estados_contribuyentes = response.data.list_estados_contribuyentes;
                this.list_estados_interno = response.data.list_estados_interno;
                this.origenes = response.data.origenes;
            })
            .finally(() => {
                if (this.api_service_token === false) {
                    if (this.config.api_service_token !== undefined) {
                        this.api_service_token = this.config.api_service_token
                    }
                }
            })

        this.$eventHub.$on("reloadDataConsigned", () => {
            this.getConsigneds();
        });
    },
    computed: {
        ...mapState([
            'config',
            'person',
            'parentPerson',
        ]),
        maxLength: function () {
            if (this.form.identity_document_type_id === '6') {
                return 11
            }
            if (this.form.identity_document_type_id === '1') {
                return 8
            }
        },
    },
    watch: {
        recordId: function() {
            // Actualizar título cuando cambia entre crear (null) y editar (id).
            this.initTitle()
        },
        'form.country_id': function(newValue) {
            if (newValue !== 'PE' && this.form.location_id && this.form.location_id.length > 0) {
                this.form.location_id = [];
            }
        }
    },
    methods: {
        handleCloseDialog() {
          if (this.hasUnsavedChanges()) {
            this.$confirm('¿Estás seguro de cerrar el formulario? Se perderán los datos no guardados.', 'Confirmar', {
              confirmButtonText: 'Cerrar sin guardar',
              cancelButtonText: 'Cancelar',
              type: 'warning'
            }).then(() => {
              this.forceCloseDialog()
            }).catch(() => {

            });
          } else {
            this.forceCloseDialog()
          }
        },
        getConsigneds() {
            return this.$http
                .get(`/consigneds/data`)
                .then(response => {
                    this.consigneds = response.data.data;
                })
                .catch(error => {})
                .then(() => {

                });
        },
        forceCloseDialog() {
          this.initForm()
          this.$emit('update:showDialog', false)
          this.$emit('update:recordId', null)
        },
        hasUnsavedChanges() {
            return JSON.stringify(this.form) !== this.originalForm
        },
        customFilterMethod(node, keyword) {
            return node.label.toLowerCase().includes(keyword.toLowerCase());
        },

        ...mapActions([
            'loadConfiguration',
        ]),
        initForm() {
            this.errors = {}
            this.form = {
                id: null,
                type: this.type,
                identity_document_type_id: '6',
                ruc: '',
                razon_social: null,
                nombre_comercial: null,
                rubro: null,
                country_id: 'PE',
                nationality_id: 'PE',
                location_id: [],
                direccion: null,
                telefono: null,
                celular: null,
                email: null,
                n_trabajadores: 0,
                tipo_empresa: null,
                estado_contribuyente: null,
                condicion_contribuyente: null,
                website: null,
                origen: null,
                aniversario: null,
                referencia: null,
                politica_pago: null,
                procedimiento_especial: null,
                frecuencia_compra: null,
                adicionales: null,
                trabaja_con_proveedores: false,
                optional_email: []
            }
            this.originalForm = JSON.stringify(this.form)
        },
        create() {
            this.titleDialog = (this.recordId) ? 'Editar Empresa' : 'Nueva Empresa'
            this.titleTabDialog = 'Datos de Empresa'
        },
        initTitle() {
            // Garantiza que el título esté definido aunque el componente se haya
            // montado con showDialog=true (caso al usar v-if desde un padre como Requerimientos).
            this.titleDialog = (this.recordId) ? 'Editar Empresa' : 'Nueva Empresa'
            this.titleTabDialog = 'Datos de Empresa'
        },
        opened() {
            if (this.recordId) {
                this.getRecord()
            }
            this.$nextTick(() => {
                this.originalForm = JSON.stringify(this.form)
            })
        },
        async getRecord() {
            await this.$http.get(`/${this.resource}/record/${this.recordId}`)
                .then(response => {
                    this.form = response.data.data
                    if (this.form.location_id && typeof this.form.location_id === 'string') {
                        this.form.location_id = JSON.parse(this.form.location_id)
                    }
                })
            this.temp_optional_email = this.form.optional_email;
        },
        validateDigits() {
            const pattern_number = new RegExp('^[0-9]+$', 'i');

            if (this.form.identity_document_type_id === '6') {
                if (this.form.ruc.length !== 11) {
                    return {
                        success: false,
                        message: `El campo número debe tener 11 dígitos.`
                    }
                }
                if (!pattern_number.test(this.form.ruc)) {
                    return {
                        success: false,
                        message: `El campo número debe contener solo números`
                    }
                }
            }

            if (this.form.identity_document_type_id === '1') {
                if (this.form.ruc.length !== 8) {
                    return {
                        success: false,
                        message: `El campo número debe tener 8 dígitos.`
                    }
                }
                if (!pattern_number.test(this.form.ruc)) {
                    return {
                        success: false,
                        message: `El campo número debe contener solo números`
                    }
                }
            }

            if (['4', '7', '0'].includes(this.form.identity_document_type_id)) {
                const pattern = new RegExp('^[A-Z0-9\-]+$', 'i');
                if (!pattern.test(this.form.ruc)) {
                    return {
                        success: false,
                        message: `El campo número no cumple con el formato establecido`
                    }
                }
            }

            return {
                success: true
            }
        },
        async submit() {
            let val_digits = await this.validateDigits()
            if (!val_digits.success) {
                return this.$message.error(val_digits.message)
            }

            if (this.is_dispatch) {
               if (this.form.location_id.length !== 3 || !this.form.direccion) {
                    return this.$message.error('Falta agregar Ubigeo o dirección');
               }
            }

            let hasErrorInAdditionalAddresses = false;
            let addressWithError = null;

            if (this.form.addresses && this.form.addresses.length > 0) {
                for (let i = 0; i < this.form.addresses.length; i++) {
                    const address = this.form.addresses[i];
                    if (address.country_id === 'PE' && (!address.location_id || address.location_id.length !== 3)) {
                        hasErrorInAdditionalAddresses = true;
                        addressWithError = i + 1;
                        break;
                    }
                }
            }

            if (hasErrorInAdditionalAddresses) {
                const mensaje = addressWithError - 1 === 0
                    ? `Falta registrar el ubigeo en la Dirección principal`
                    : `Falta registrar el ubigeo en la Dirección secundaria #${addressWithError - 1}`;
                return this.$message.error(mensaje);
            }

            this.loading_submit = true
            this.form.parent_id = parseInt(this.parent);

            if (this.form.id === null) {
              delete this.form.id;
            }

            await this.$http.post(`/${this.resource}`, this.form)
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(response.data.message)
                        if (this.external) {
                            this.$eventHub.$emit('reloadDataPersons', response.data.id)
                        } else {
                            this.$eventHub.$emit('reloadData')
                        }
                        this.close()
                    } else {
                        this.$message.error(response.data.message)
                    }

                })
                .catch(error => {
                    var resp = error.response && error.response.data ? error.response.data : null;

                    // Caso: RUC duplicado → mostrar solo mensaje de rechazo
                    if (error.response && error.response.status === 422 && resp && resp.duplicate) {
                        this.$message({
                            message: resp.message,
                            type: 'warning',
                            duration: 6000,
                            showClose: true
                        })
                        return
                    }

                    if (error.response && error.response.status === 422) {
                        this.errors = resp && resp.errors ? resp.errors : (resp || {})
                        this.goToFirstErrorTab(resp && resp.message ? resp.message : null)
                    } else {
                        this.$message.error('Error al guardar la empresa')
                    }
                })
                .finally(() => {
                    this.loading_submit = false
                })
        },
        // Al fallar el guardado por validación (422), llevar al usuario a la
        // pestaña donde está el primer campo con error y avisar dónde corregir.
        // Los campos ya se resaltan con has-danger; aquí solo navegamos + avisamos.
        goToFirstErrorTab(backendMessage) {
            var fieldTab = {
                // Pestaña "Datos de Empresa"
                ruc: 'first', razon_social: 'first', nombre_comercial: 'first', rubro: 'first',
                tipo_empresa: 'first', n_trabajadores: 'first',
                estado_contribuyente: 'first', condicion_contribuyente: 'first',
                // Pestaña "Dirección"
                location_id: 'second', direccion: 'second', referencia: 'second',
                telefono: 'second', celular: 'second', email: 'second', website: 'second',
                origen: 'second', aniversario: 'second',
                // Pestaña "Otros Datos"
                politica_pago: 'third', procedimiento_especial: 'third',
                frecuencia_compra: 'third', adicionales: 'third', trabaja_con_proveedores: 'third'
            }
            var fieldOrder = [
                'ruc', 'razon_social', 'nombre_comercial', 'rubro', 'tipo_empresa',
                'n_trabajadores', 'estado_contribuyente', 'condicion_contribuyente',
                'location_id', 'direccion', 'referencia', 'telefono', 'celular', 'email',
                'website', 'origen', 'aniversario', 'politica_pago', 'procedimiento_especial',
                'frecuencia_compra', 'adicionales', 'trabaja_con_proveedores'
            ]
            var tabLabel = { first: 'Datos de Empresa', second: 'Dirección', third: 'Otros Datos' }

            // Buscar el primer campo con error siguiendo el orden de las pestañas.
            var firstField = null
            for (var i = 0; i < fieldOrder.length; i++) {
                if (this.errors[fieldOrder[i]]) {
                    firstField = fieldOrder[i]
                    break
                }
            }
            // Fallback: cualquier otra clave de error no mapeada.
            if (!firstField) {
                var keys = Object.keys(this.errors || {})
                if (keys.length > 0) firstField = keys[0]
            }

            var tab = (firstField && fieldTab[firstField]) ? fieldTab[firstField] : null
            if (tab && this.activeName !== tab) {
                this.activeName = tab
            }

            var label = tab ? tabLabel[tab] : null
            var msg = backendMessage ? backendMessage : 'Hay datos por corregir.'
            if (label) {
                msg += ' Revisa los campos marcados en rojo en la pestaña "' + label + '".'
            }
            this.$message({
                message: msg,
                type: 'error',
                duration: 6000,
                showClose: true
            })
        },
        changeIdentityDocType() {
            (this.recordId == null) ? this.setDataDefaultCustomer() : null
        },
        setDataDefaultCustomer() {
            if (this.form.identity_document_type_id == '0') {
                this.form.ruc = '99999999'
                this.form.razon_social = "Clientes - Varios"
            } else {
                this.form.ruc = ''
                this.form.razon_social = null
            }
        },
        close() {
            this.$eventHub.$emit('initInputPerson')
            this.$emit('update:showDialog', false)
            this.$emit('update:recordId', null)
            this.initForm()
        },
        searchCustomer() {
            this.searchServiceNumberByType()
        },
        searchNumber(data) {
            this.form.razon_social = data.name;
            if (data.trade_name) this.form.nombre_comercial = data.trade_name;
            this.form.location_id = data.location_id;
            this.form.direccion = data.address;
            this.form.condicion_contribuyente = data.condition;
            this.form.estado_contribuyente = data.state;
        },
        clickRemoveAddress(index) {
            this.form.addresses.splice(index, 1);
        }
    }
}
</script>

<style>
/* Estilos para el dialog con bitácora */
.dialog-with-bitacora .el-dialog__body {
    padding: 0 !important;
}

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

.section-title {
    color: #303133;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 8px;
    border-bottom: 1px solid #ebeef5;
}
</style>
