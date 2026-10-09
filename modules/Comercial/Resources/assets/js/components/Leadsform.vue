<template>
    <el-dialog
      :title="titleDialog"
      :visible="showDialog"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @close="handleCloseDialog"
      @open="onOpen">
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
                <div class="row">
                  <div class="col-md-12">
                    <div :class="{'has-danger': errors.empresa_id}"
                         class="form-group">
                      <label class="control-label">
                        Empresa
                      </label>
                      <el-select v-model="form.empresa_id"
                                 clearable
                                 filterable
                                 allow-create
                                 placeholder="Ingrese el nombre de la empresa o numero de RUC"
                                 remote
                                 :remote-method="getEmpresas"
                                 :loading="loading"
                                 :filterable="true"
                                 :default-first-option="true"
                                 @focus="handleFocus"
                                 @change="handleEmpresaChange"
                      >
                          <el-option v-for="option in list_empresas"
                                     :key="option.id"
                                     :label="option.razon_social"
                                     :value="option.id"></el-option>

                          <div slot="empty" class="text-center p-2">
                            <template v-if="loading">
                              Cargando...
                            </template>
                            <template v-else>
                              <div v-if="currentSearchQuery && currentSearchQuery.length >= 3">
                                <i class="el-icon-warning text-warning"></i>
                                <p class="mb-1">No se encontró "{{ currentSearchQuery }}"</p>
                                <p class="mb-0">
                                  <small class="text-success">
                                    <i class="el-icon-circle-plus"></i>
                                    Presione <kbd>Enter</kbd> para crear como nuevo
                                  </small>
                                </p>
                              </div>
                              <div v-else>
                                <i class="el-icon-search"></i>
                                <p class="mb-0">Ingrese al menos 3 caracteres para buscar</p>
                              </div>
                            </template>
                          </div>
                      </el-select>

                      <div v-if="isCustomValue && form.empresa_id" class="mt-2">
                        <el-alert
                          type="warning"
                          :closable="false"
                          show-icon>
                          <span slot="title">
                            Empresa no encontrada: <strong>"{{ form.empresa_id }}"</strong>
                          </span>
                          <small>Este valor se guardará como una nueva empresa</small>
                        </el-alert>
                      </div>

                      <small v-if="errors.empresa_id"
                             class="form-control-feedback"
                             v-text="errors.empresa_id[0]"></small>
                    </div>
                  </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                      <div :class="{'has-danger': errors.origen}"
                           class="form-group">
                        <label class="control-label">
                          Origen
                        </label>
                        <el-select v-model="form.origen"
                                   clearable
                                   filterable>
                          <el-option v-for="option in list_origenes"
                                     :key="option.id"
                                     :label="option.name"
                                     :value="option.id"></el-option>
                        </el-select>
                        <small v-if="errors.origen"
                               class="form-control-feedback"
                               v-text="errors.origen[0]"></small>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div :class="{'has-danger': errors.etapa}"
                           class="form-group">
                        <label class="control-label">
                          Etapa
                        </label>
                        <el-select v-model="form.etapa"
                                   clearable
                                   filterable>
                          <el-option v-for="option in list_etapas"
                                     :key="option.id"
                                     :label="option.name"
                                     :value="option.id"></el-option>
                        </el-select>
                        <small v-if="errors.etapa"
                               class="form-control-feedback"
                               v-text="errors.etapa[0]"></small>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div :class="{'has-danger': errors.prioridad}"
                           class="form-group">
                        <label class="control-label">
                          Prioridad
                        </label>
                        <el-select v-model="form.prioridad"
                                   clearable
                                   filterable>
                          <el-option v-for="option in list_prioridades"
                                     :key="option.id"
                                     :label="option.name"
                                     :value="option.id"></el-option>
                        </el-select>
                        <small v-if="errors.prioridad"
                               class="form-control-feedback"
                               v-text="errors.prioridad[0]"></small>
                      </div>
                    </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group" :class="{'has-danger': errors.contacto}">
                      <label class="control-label">Contacto</label>
                      <el-input v-model="form.contacto" placeholder="Nombre y Apellido" ></el-input>
                      <small class="form-control-feedback" v-if="errors.contacto" v-text="errors.contacto[0]"></small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group" :class="{'has-danger': errors.email}">
                      <label class="control-label">Email</label>
                      <el-input v-model="form.email"></el-input>
                      <small class="form-control-feedback" v-if="errors.email" v-text="errors.email[0]"></small>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group" :class="{'has-danger': errors.telefono}">
                      <label class="control-label">Telefono</label>
                      <el-input v-model="form.telefono"></el-input>
                      <small class="form-control-feedback" v-if="errors.telefono" v-text="errors.telefono[0]"></small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group" :class="{'has-danger': errors.monto_estimado}">
                      <label class="control-label">Monto estimado</label>
                      <el-input-number v-model="form.monto_estimado" :step="0.1" :precision="2"></el-input-number>
                      <small class="form-control-feedback" v-if="errors.monto_estimado" v-text="errors.monto_estimado[0]"></small>
                    </div>
                  </div>
                </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group" :class="{'has-danger': errors.fecha_estimada}">
                    <label class="control-label">Fecha estimada</label>
                    <el-date-picker v-model="form.fecha_estimada" type="date" value-format="yyyy-MM-dd" :clearable="false"></el-date-picker>
                    <small class="form-control-feedback" v-if="errors.fecha_estimada" v-text="errors.fecha_estimada[0]"></small>
                  </div>
                </div>
                <div class="col-md-6">
                  <div :class="{'has-danger': errors.estado_revision}"
                       class="form-group">
                    <label class="control-label">
                      Estado
                    </label>
                    <el-select v-model="form.estado_revision"
                               clearable
                               filterable>
                      <el-option v-for="option in list_estados_revision"
                                 :key="option.id"
                                 :label="option.name"
                                 :value="option.id"></el-option>
                    </el-select>
                    <small v-if="errors.estado_revision"
                           class="form-control-feedback"
                           v-text="errors.estado_revision[0]"></small>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-12">
                  <div :class="{'has-danger': errors.descripcion}"
                       class="form-group">
                    <label class="control-label">Descripción</label>
                    <el-input type="textarea" v-model="form.descripcion" :rows="2" resize="vertical" placeholder="¿Que necesita el cliente?"></el-input>
                    <small v-if="errors.descripcion"
                           class="form-control-feedback"
                           v-text="errors.descripcion[0]"></small>
                  </div>
                </div>

              </div>
            </div>
            <div class="form-actions text-right pt-2">
                <el-button class="second-buton" @click.prevent="handleCloseDialog()">Cancelar</el-button>
                <el-button type="primary" native-type="submit" :loading="loading_submit">Guardar</el-button>
            </div>
        </form>
    </el-dialog>
</template>

<script>


    export default {
        props: ['showDialog', 'recordId'],
        data() {
            return {
                loading_submit: false,
                titleDialog: null,
                resource: 'comercial/leads',
                errors: {},
                form: {},
                headers: headers_token,
                list_empresas: [],
                list_origenes: [],
                list_etapas: [],
                list_prioridades: [],
                list_estados_revision: [],
                loading: false,
                searchTimeout: null,
                lastSearch: '',
                currentSearchQuery: '',
                isCustomValue: false
            }
        },
        async created() {
          await this.initForm()
          await this.$http.get(`/${this.resource}/tables`)
            .then(response => {
              this.list_empresas = response.data.list_empresas
              this.list_origenes = response.data.list_origenes
              this.list_etapas = response.data.list_etapas
              this.list_prioridades = response.data.list_prioridades
              this.list_estados_revision = response.data.list_estados_revision
            })

        },
        methods: {
            onOpen() {
              this.create();
            },
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
            forceCloseDialog() {
              this.initForm()
              this.$emit('update:showDialog', false)
              this.$emit('update:recordId', null)
            },
            hasUnsavedChanges() {
                return JSON.stringify(this.form) !== this.originalForm
            },
            async getEmpresas(query) {
                this.currentSearchQuery = query;
                if (query === this.lastSearch) return;

                if (!query || query.length < 3) {
                  this.list_empresas = [];
                  this.lastSearch = query;
                  this.isCustomValue = false;
                  return;
                }

                // Debounce
                if (this.searchTimeout) {
                  clearTimeout(this.searchTimeout);
                }

                this.searchTimeout = setTimeout(async () => {
                  this.loading = true;
                  this.lastSearch = query;

                  try {
                    const response = await this.$http.get(`/comercial/empresas/search`, {
                      params: {
                        search: query,
                        limit: 50 // Aumentar límite si es necesario
                      }
                    });

                    this.list_empresas = response.data.data || response.data;

                    if (this.list_empresas.length === 0) {
                      console.log(`No se encontraron resultados para: "${query}"`);
                      console.log('El usuario puede presionar Enter para usar este valor');
                    }

                  } catch (error) {
                    console.error('Error buscando empresas:', error);
                    this.list_empresas = [];
                    this.$message.error('Error al buscar empresas');
                  } finally {
                    this.loading = false;
                  }
                }, 400);
            },
            handleEmpresaChange(value) {
              if (value === null || value === undefined || value === '') {
                this.isCustomValue = false;
                return;
              }

              // Verificar si existe en la lista de empresas
              const existsInList = this.list_empresas.some(emp => emp.id === value);
              this.isCustomValue = !existsInList;

              if (this.isCustomValue) {
                console.log('Valor personalizado seleccionado:', value);
                console.log('Este valor se enviará al backend como nueva empresa');
              } else {
                console.log('Empresa existente seleccionada, ID:', value);
              }
            },
            handleFocus() {
              if (!this.form.empresa_id && this.list_empresas.length === 0) {
                this.currentSearchQuery = '';
                this.getEmpresas('');
              }
            },
            initForm() {
                this.errors = {}
                this.isCustomValue = false;
                this.currentSearchQuery = '';
                this.lastSearch = '';

                this.form = {
                  id: null,
                  empresa_id: null,
                  origen: null,
                  etapa: null,
                  prioridad: null,
                  contacto: null,
                  email: null,
                  telefono: null,
                  monto_estimado: null,
                  fecha_estimada: null,
                  estado_revision: null,
                  descripcion: null,
                  ruc: null,
                }
                this.originalForm = JSON.stringify(this.form)
            },
            async create() {
              this.titleDialog = (this.recordId) ? 'Editar Lead' : 'Nuevo Lead'

              if (this.recordId) {
                try {
                  const response = await this.$http.get(`/${this.resource}/record/${this.recordId}`)
                  const data = response.data.data
                  this.form = data

                  console.info('Lead cargado:', this.form)

                  // Si el backend devuelve la empresa, agregarla a la lista
                  if (data.empresa) {
                    const exists = this.list_empresas.some(emp => emp.id === data.empresa.id)
                    if (!exists) {
                      this.list_empresas.push(data.empresa)
                    }
                    console.log('Empresa precargada:', data.empresa.razon_social)
                  } else {
                    this.form.empresa_id = data.ruc;
                    this.handleEmpresaChange(data.ruc);
                  }

                } catch (error) {
                  console.error('ERROR AL CARGAR:', error.response);
                  this.$message.error('No se pudo cargar el registro para editar')
                  this.forceCloseDialog()
                }
              } else {
                this.initForm();
              }
            },

            async submit() {
                this.loading_submit = true

                console.log('📤 Enviando formulario:');
                console.log('  - empresa_id:', this.form.empresa_id);
                console.log('  - ¿Es valor personalizado?', this.isCustomValue);
                console.log('  - Datos completos:', this.form);

                this.$http.post(`/${this.resource}`, this.form)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            this.$eventHub.$emit('reloadData')
                            this.close()
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(error => {
                        if (error.response.status === 422) {
                          this.errors = error.response.data
                          console.error('Errores de validación:', this.errors);
                        } else {
                          console.log(error.response)
                          this.$message.error('Error al guardar el registro')
                        }
                    })
                    .then(() => {
                        this.loading_submit = false
                    })

            },

            close() {
                this.$emit('update:showDialog', false)
                this.$emit('update:recordId', null)
                this.$emit('reloadLeads');
                this.initForm()
            }
        }
    }
</script>
<style>
.el-select-dropdown__empty {
  padding: 15px 10px !important;
}

kbd {
  background-color: #f4f4f5;
  border: 1px solid #d1d5db;
  border-radius: 3px;
  padding: 2px 6px;
  font-family: monospace;
  font-size: 12px;
}

.text-warning {
  color: #e6a23c;
}

.text-success {
  color: #67c23a;
}
</style>
