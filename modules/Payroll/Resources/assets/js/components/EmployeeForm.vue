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

                    <div :class="{'has-danger': errors.user_id}"
                         class="form-group">
                      <label class="control-label">
                        Usuario
                      </label>
                      <el-select
                        v-model="form.user_id"
                        clearable
                        filterable
                        placeholder="Ingrese el nombre del Usuario para buscar"
                        remote
                        :remote-method="getUsuarios"
                        :loading="loading"
                        :automatic-dropdown="false"
                        @focus="handleFocus"
                      >
                        <el-option
                          v-for="option in list_usuarios"
                          :key="option.id"
                          :label="option.name"
                          :value="option.id">
                        </el-option>

                        <div slot="empty" class="text-center p-2">
                          <template v-if="loading">
                            Cargando...
                          </template>
                          <template v-else>
                            {{ list_usuarios.length === 0 ? 'Ingrese al menos 3 caracteres para buscar' : 'No se encontraron resultados' }}
                          </template>
                        </div>
                      </el-select>
                      <small v-if="errors.user_id"
                             class="form-control-feedback"
                             v-text="errors.user_id[0]"></small>
                    </div>




                  </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group" :class="{'has-danger': errors.codigo_empleado}">
                            <label class="control-label">Codigo Empleado</label>
                            <el-input v-model="form.codigo_empleado"></el-input>
                            <small class="form-control-feedback" v-if="errors.codigo_empleado" v-text="errors.codigo_empleado[0]"></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group" :class="{'has-danger': errors.sueldo_basico}">
                        <label class="control-label">Sueldo Basico</label>
                        <el-input v-model="form.sueldo_basico"></el-input>
                        <small class="form-control-feedback" v-if="errors.sueldo_basico" v-text="errors.sueldo_basico[0]"></small>
                      </div>
                    </div>
                </div>
                <div class="row">
                  <div class="col-md-6">

                    <div :class="{'has-danger': errors.tipo_regimen}"
                         class="form-group">
                      <label class="control-label">
                        Régimen Pensionario
                      </label>
                      <el-select v-model="form.tipo_regimen"
                                 clearable
                                 filterable>
                        <el-option v-for="option in list_tipo_regimen"
                                   :key="option.id"
                                   :label="option.name"
                                   :value="option.id"></el-option>
                      </el-select>
                      <small v-if="errors.tipo_regimen"
                             class="form-control-feedback"
                             v-text="errors.tipo_regimen[0]"></small>
                    </div>


                  </div>
                  <div class="col-md-6">
                    
                    <div :class="{'has-danger': errors.afp_id}"
                         class="form-group">
                      <label class="control-label">
                        AFP
                      </label>
                      <el-select v-model="form.afp_id"
                                 clearable
                                 filterable>
                        <el-option v-for="option in list_afps"
                                   :key="option.id"
                                   :label="option.nombre"
                                   :value="option.id"></el-option>
                      </el-select>
                      <small v-if="errors.afp_id"
                             class="form-control-feedback"
                             v-text="errors.afp_id[0]"></small>
                    </div>


                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    
                    <div :class="{'has-danger': errors.tipo_comision_afp}"
                         class="form-group">
                      <label class="control-label">
                        Tipo Comisión
                      </label>
                      <el-select v-model="form.tipo_comision_afp"
                                 clearable
                                 filterable>
                        <el-option v-for="option in list_tipo_comision_afp"
                                   :key="option.id"
                                   :label="option.name"
                                   :value="option.id"></el-option>
                      </el-select>
                      <small v-if="errors.tipo_comision_afp"
                             class="form-control-feedback"
                             v-text="errors.tipo_comision_afp[0]"></small>
                    </div>
                    

                  </div>
                  <div class="col-md-6">

                    <div class="form-group" :class="{'has-danger': errors.cuspp}">
                      <label class="control-label">CUSPP</label>
                      <el-input v-model="form.cuspp" maxlength="12" show-word-limit></el-input>
                      <small class="form-control-feedback" v-if="errors.cuspp" v-text="errors.cuspp[0]"></small>
                    </div>

                  </div>
                </div>

                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group input-checkbox" :class="{'has-danger': errors.asignacion_familiar}">
                      <el-checkbox v-model="form.asignacion_familiar" :true-label="1" :false-label="0"> Asignación Familiar</el-checkbox>
                      <small class="form-control-feedback" v-if="errors.asignacion_familiar" v-text="errors.asignacion_familiar[0]"></small>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group" :class="{'has-danger': errors.movilidad}">
                      <label class="control-label">Movilidad</label>
                      <el-input-number v-model="form.movilidad" :step="0.01" :min="0"></el-input-number>
                      <small class="form-control-feedback" v-if="errors.movilidad" v-text="errors.movilidad[0]"></small>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div :class="{'has-danger': errors.activo}"
                         class="form-group">
                      <label class="control-label">
                        Estado Empleado
                      </label>
                      <el-select v-model="form.activo"
                                 clearable
                                 filterable>
                        <el-option v-for="option in list_empleado_activo"
                                   :key="option.id"
                                   :label="option.name"
                                   :value="option.id"></el-option>
                      </el-select>
                      <small v-if="errors.activo"
                             class="form-control-feedback"
                             v-text="errors.activo[0]"></small>
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
        props: ['showDialog', 'recordId', 'context', 'userId', 'empleadoEntity'],
        data() {
            return {
                loading_submit: false,
                titleDialog: null,
                resource: 'payroll/employees',
                errors: {},
                form: {},
                headers: headers_token,
                list_usuarios: [],
                list_tipo_regimen: [],
                list_afps: [],
                list_tipo_comision_afp: [],
                list_empleado_activo: [],
                loading: false,
                searchTimeout: null,
                lastSearch: '',
                isInitialized: false
            }
        },
        async created() {
          // Solo cargar las tablas iniciales
          await this.$http.get(`/${this.resource}/tables`)
            .then(response => {
              this.list_usuarios = response.data.list_usuarios
              this.list_tipo_regimen = response.data.list_tipo_regimen
              this.list_afps = response.data.list_afps
              this.list_tipo_comision_afp = response.data.list_tipo_comision_afp
              this.list_empleado_activo = response.data.list_empleado_activo
            })
        },
        watch: {
          recordId: {
            immediate: false,
            handler(newVal) {
              // Solo cargar si el diálogo está visible y hay un recordId válido
              if (this.showDialog && newVal && this.isInitialized) {
                this.titleDialog = 'Editar Empleado';
                this.loadContactData();
              }
            }
          },
          showDialog: {
            immediate: false,
            handler(newVal, oldVal) {
              if (newVal && !oldVal) {
                // Diálogo se abre
                this.errors = {};
                
                if (this.recordId) {
                  // Modo edición
                  this.titleDialog = 'Editar Empleado';
                  this.loadContactData();
                } else {
                  // Modo creación
                  this.titleDialog = 'Nuevo Empleado';
                  this.initForm();

                  // Pre-cargar usuario si existe
                  if (this.empleadoEntity && this.empleadoEntity.id) {
                    this.$nextTick(() => {
                      this.preloadUsuario();
                    });
                  }
                }

                this.isInitialized = true;
              } else if (!newVal && oldVal) {
                // El diálogo se acaba de cerrar
                this.isInitialized = false;
              }
            }
          },
          // Agregar watcher para empleadoEntity
          empleadoEntity: {
            deep: true,
            handler(newVal, oldVal) {
              // Si el diálogo está abierto, no hay recordId (nuevo contacto) y hay una empresa
              if (this.showDialog && !this.recordId && newVal && newVal.id) {
                this.$nextTick(() => {
                  this.preloadUsuario();
                });
              }
            }
          }
        },
        methods: {
            onOpen() {
              // Los watchers ya manejan la inicialización
              // Este método solo sirve como fallback si los watchers no se activaron
              if (this.isInitialized) {
                return;
              }

              this.errors = {};

              if (this.recordId) {
                this.titleDialog = 'Editar Empleado';
                this.loadContactData();
              } else {
                this.titleDialog = 'Nuevo Empleado';
                this.initForm();

                if (this.empleadoEntity && this.empleadoEntity.id) {
                  this.$nextTick(() => {
                    this.preloadUsuario();
                  });
                }
              }

              this.isInitialized = true;
            },
            preloadUsuario() {
              if (!this.empleadoEntity || !this.empleadoEntity.id) {
                return;
              }

              // Extraer datos de la empresa (manejar Getters & Setters de Vue)
              const userId = parseInt(this.empleadoEntity.id);
              const empleadoName = this.empleadoEntity.name || 'Empleado';

              const empleadoToAdd = {
                id: userId,
                name: empleadoName
              };

              // Buscar si ya existe en la lista
              const existsIndex = this.list_usuarios.findIndex(e => e.id === userId);

              if (existsIndex === -1) {
                this.list_usuarios.unshift(empleadoToAdd);
              } else {
                this.$set(this.list_usuarios, existsIndex, empleadoToAdd);
              }

              // Asignar el ID al formulario
              this.form.user_id = userId;

              // Forzar actualización del componente
              this.$forceUpdate();

              this.$nextTick(() => {
                this.originalForm = JSON.stringify(this.form);
              });
            },

            loadContactData() {
              this.loading = true;
              this.$http.get(`/${this.resource}/record/${this.recordId}`)
                .then(response => {
                  const data = response.data.data;

                  // Validar que data existe
                  if (!data) {
                    this.$message.error('No se pudieron cargar los datos del contacto');
                    this.forceCloseDialog();
                    return;
                  }

                  // Asignar todos los campos del formulario
                  this.form = {
                    id: data.id,
                    user_id: data.user_id,
                    codigo_empleado: data.codigo_empleado,
                    sueldo_basico: data.sueldo_basico,
                    tipo_regimen: data.tipo_regimen,
                    afp_id: data.afp_id,
                    activo: data.activo,
                    asignacion_familiar: data.asignacion_familiar,
                    cuspp: data.cuspp,
                    movilidad: data.movilidad,
                    tipo_comision_afp: data.tipo_comision_afp
                  };

                  // Manejar la empresa en el select
                  if (data.user_id) {
                    let empleadoNombre = 'Empleado';

                    // Intentar obtener el nombre de la empresa de diferentes formas
                    if (data.usuario) {
                      // Si viene como objeto
                      empleadoNombre = data.usuario.name || 'Empleado';
                    }

                    // Verificar si la empresa ya está en la lista
                    const usuarioExiste = this.list_usuarios.find(e => e.id === data.empresa_id);

                    if (!usuarioExiste) {
                      this.list_usuarios.unshift({
                        id: data.empresa_id,
                        name: empleadoNombre
                      });
                    }
                  }

                  // Guardar estado original del formulario
                  this.$nextTick(() => {
                    this.originalForm = JSON.stringify(this.form);
                  });
                })
                .catch(error => {
                  this.$message.error('No se pudo cargar el registro para editar');
                  this.forceCloseDialog();
                })
                .finally(() => {
                  this.loading = false;
                });
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
              this.isInitialized = false
              this.$emit('update:showDialog', false)
              this.$emit('update:recordId', null)
            },
            hasUnsavedChanges() {
                return JSON.stringify(this.form) !== this.originalForm
            },
            async getUsuarios(query) {
              if (query === this.lastSearch) return;

              if (!query || query.length < 3) {
                // No limpiar la lista si hay una empresa pre-cargada
                if (!this.form.empresa_id) {
                  this.list_usuarios = [];
                }
                this.lastSearch = query;
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
                  const response = await this.$http.get(`/payroll/employees/search-users`, {
                    params: {
                      search: query,
                      limit: 50
                    }
                  });

                  this.list_usuarios = response.data.data || response.data;

                  // Si hay una empresa pre-seleccionada que no está en los resultados, agrégala
                  if (this.form.user_id && this.empleadoEntity) {
                    const exists = this.list_usuarios.find(e => e.id === this.form.user_id);
                    if (!exists) {
                      this.list_usuarios.unshift({
                        id: this.form.user_id,
                        name: this.empleadoEntity.name
                      });
                    }
                  }
                } catch (error) {
                  this.list_usuarios = [];
                } finally {
                  this.loading = false;
                }
              }, 400);
            },
            handleFocus() {
              if (!this.form.user_id && this.list_usuarios.length === 0) {
                this.getUsuarios('');
              }
            },
            initForm() {
                this.errors = {}
                this.form = {
                    id: null,
                    user_id: null,
                    movilidad: 0.00,
                    codigo_empleado: null,
                    tipo_regimen: null,
                    afp_id: null,
                    tipo_comision_afp: null,
                    cuspp:null,
                    sueldo_basico: null,
                    asignacion_familiar: false,
                    activo: 1,                   
                }
                this.originalForm = JSON.stringify(this.form)
            },
            async submit() {
                this.loading_submit = true
                this.$http.post(`/${this.resource}/store`, this.form)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message)
                            if (this.context === 'empresas') {
                              this.$emit('contactSaved', this.userId || this.form.empresa_id);
                            } else {
                              this.$eventHub.$emit('reloadData');
                            }
                            this.close()
                        } else {
                            this.$message.error(response.data.message)
                        }
                    })
                    .catch(error => {
                        if (error.response.status === 422) {
                            this.errors = error.response.data
                        }
                    })
                    .then(() => {
                        this.loading_submit = false
                    })

            },

            close() {
              this.$emit('update:showDialog', false)
              this.$emit('update:recordId', null)
              this.initForm()
              this.isInitialized = false
            },


        }
    }
</script>
<style>
.input-checkbox {
  margin-top: 33px;
}
</style>
