<template>
  <el-dialog :title="titleDialog" :visible="showDialog" :close-on-click-modal="false" :close-on-press-escape="false"
    @close="handleCloseDialog" @open="onOpen">
    <form autocomplete="off" @submit.prevent="submit">
      <div class="form-body">
        <div class="row">
          <div class="col-md-4">
            <div class="form-group" :class="{ 'has-danger': errors.periodo }">
              <label class="control-label">Periodo</label>
              <el-date-picker type="month" v-model="form.periodo" format="MM-yyyy" value-format="yyyy-MM"
                placeholder="Seleccione un mes del Año">
              </el-date-picker>
              <small class="form-control-feedback" v-if="errors.periodo" v-text="errors.periodo[0]"></small>
            </div>
          </div>

          <div class="col-md-4">
            <div class="form-group" :class="{ 'has-danger': errors.fecha_inicio }">
              <label class="control-label">Fecha Inicio</label>
              <el-date-picker v-model="form.fecha_inicio" type="date" format="dd-MM-yyyy" value-format="yyyy-MM-dd"
                placeholder="Seleccione la fecha de inicio">
              </el-date-picker>
              <small class="form-control-feedback" v-if="errors.fecha_inicio" v-text="errors.fecha_inicio[0]"></small>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group" :class="{ 'has-danger': errors.fecha_fin }">
              <label class="control-label">Fecha Fin</label>
              <el-date-picker v-model="form.fecha_fin" type="date" format="dd-MM-yyyy" value-format="yyyy-MM-dd"
                placeholder="Seleccione la fecha de fin">
              </el-date-picker>
              <small class="form-control-feedback" v-if="errors.fecha_fin" v-text="errors.fecha_fin[0]"></small>
            </div>
          </div>


        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group" :class="{ 'has-danger': errors.fecha_pago }">
              <label class="control-label">Fecha de Pago</label>
              <el-date-picker v-model="form.fecha_pago" type="date" format="dd-MM-yyyy" value-format="yyyy-MM-dd"
                placeholder="Seleccione la fecha de pago">
              </el-date-picker>
              <small class="form-control-feedback" v-if="errors.fecha_pago" v-text="errors.fecha_pago[0]"></small>
            </div>

          </div>
          <div class="col-md-8">

          </div>
        </div>
        <div class="row">
          <div class="col-md-12">

            <div class="form-group" :class="{ 'has-danger': errors.observaciones }">
              <label class="control-label">Observaciones</label>
              <el-input type="textarea" :rows="2" placeholder="Ingrese las observaciones" v-model="form.observaciones">
              </el-input>
              <small class="form-control-feedback" v-if="errors.observaciones" v-text="errors.observaciones[0]"></small>
            </div>

          </div>

        </div>

        <div class="row">
          <div class="col-md-12">
            <br />

            <el-alert title="Nota" type="warning"
              description="Al crear la planilla, se cargarán automáticamente todos los empleados activos en el sistema. Podrá editar los conceptos variables antes de procesar la planilla."
              :closable="false" show-icon>
            </el-alert>
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
      resource: 'payroll-main',
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
          this.titleDialog = 'Editar Planilla';
          this.loadPlanillaData();
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
            this.titleDialog = 'Editar Planilla';
            this.loadPlanillaData();
          } else {
            // Modo creación
            this.titleDialog = 'Nueva Planilla';
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
        this.titleDialog = 'Editar Planilla';
      } else {
        this.titleDialog = 'Nueva Planilla';
        this.initForm();
      }

      this.isInitialized = true;
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

    handleFocus() {
      if (!this.form.user_id && this.list_usuarios.length === 0) {
        this.getUsuarios('');
      }
    },
    initForm() {
      this.errors = {}
      this.form = {
        id: null,
        periodo: null,
        fecha_inicio: null,
        fecha_fin: null,
        fecha_pago: null,
        observaciones: null,
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

    async loadPlanillaData() {
      if (!this.recordId) return;

      try {
        const response = await this.$http.get(`/${this.resource}/record/${this.recordId}`);

        // Adapt to Laravel Resource response (wrapped in data)
        const data = response.data.data ? response.data.data : response.data;

        if (data) {
          this.form = {
            id: data.id,
            periodo: data.periodo,
            fecha_inicio: data.fecha_inicio,
            fecha_fin: data.fecha_fin,
            fecha_pago: data.fecha_pago,
            observaciones: data.observaciones
          };
          this.originalForm = JSON.stringify(this.form);
        }
      } catch (error) {
        this.$message.error('Error al cargar los datos de la planilla');
        console.error(error);
      }
    },

    preloadUsuario() {
      // Este método se mantiene por compatibilidad pero no hace nada en planillas
      // Las planillas no usan usuarios directos como los empleados
    }



  }
}
</script>
<style>
.input-checkbox {
  margin-top: 33px;
}
</style>
