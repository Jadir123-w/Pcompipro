<template>
    <el-dialog
      :visible="showReviewDialog"
      :close-on-click-modal="false"
      :close-on-press-escape="false"
      @open="onOpen">
        <div slot="title">
          <span class="el-dialog__title">{{ this.titleDialog}}</span>
          <span :class="'badge ' + (classNameEstadosRevision[this.form.estado_revision] || '')">
            <i class="el-icon-warning"></i> {{ this.form.estado_revision }}
          </span>
        </div>
        <hr style="margin-top: 1px;margin-bottom: 15px;" />
        <form autocomplete="off" @submit.prevent="submit">
            <div class="form-body">
              <div class="row">
                <div class="col-md-12">
                  <el-descriptions title="Datos del Lead" :column="3" border>
                    <el-descriptions-item label="RUC">{{ this.form.lead.ruc }}</el-descriptions-item>
                    <el-descriptions-item label="Email">{{ this.form.lead.email }}</el-descriptions-item>
                    <el-descriptions-item label="Celular/Telefono">{{ this.form.lead.telefono }}</el-descriptions-item>
                  </el-descriptions>
                </div>
              </div>

              <!-- Empresa por RUC -->
              <div class="row mt-4">
                <div class="col-md-12">
                  <h5 class="mb-3">Empresa por RUC</h5>

                  <!-- No se encontró empresa -->
                  <el-alert
                    v-if="!form.empresa_detectada"
                    title="No se encontró empresa por este RUC."
                    type="warning"
                    :closable="false"
                    class="mb-3">
                    <div slot="default" class="mt-2">
                      <el-button
                        type="primary"
                        size="small"
                        icon="el-icon-plus"
                        @click="showCreateEmpresaDialog = true">
                        Crear empresa
                      </el-button>
                    </div>
                  </el-alert>

                  <!-- Empresa encontrada -->
                  <el-card v-else shadow="hover" class="mb-3">
                    <div slot="header" class="clearfix">
                      <span><strong>{{ form.empresa_detectada.razon_social }}</strong></span>
                      <el-tag size="small" type="success" class="ml-2">Empresa OK</el-tag>
                    </div>
                    <el-descriptions :column="2" border size="small">
                      <el-descriptions-item label="RUC">{{ form.empresa_detectada.ruc }}</el-descriptions-item>
                      <el-descriptions-item label="ID">{{ form.empresa_detectada.id }}</el-descriptions-item>
                      <el-descriptions-item label="Nombre Comercial">{{ form.empresa_detectada.nombre_comercial || '-' }}</el-descriptions-item>
                      <el-descriptions-item label="Dirección">{{ form.empresa_detectada.direccion || '-' }}</el-descriptions-item>
                    </el-descriptions>
                  </el-card>
                </div>
              </div>

              <!-- Contacto por Email/Celular -->
              <div class="row mt-3">
                <div class="col-md-12">
                  <h5 class="mb-3">Contacto por Email/Celular</h5>

                  <!-- No se encontró contacto -->
                  <el-alert
                    v-if="!contactoActual"
                    title="No se encontró contacto por email/celular."
                    type="warning"
                    :closable="false"
                    class="mb-3">
                    <div slot="default" class="mt-2">
                      <el-button
                        type="primary"
                        size="small"
                        icon="el-icon-plus"
                        :disabled="!empresaIdActual"
                        @click="showCreateContactoDialog = true">
                        Crear contacto
                      </el-button>
                      <small v-if="!empresaIdActual" class="text-muted d-block mt-2">
                        * Debe crear o seleccionar una empresa primero
                      </small>
                    </div>
                  </el-alert>

                  <!-- Contacto encontrado o creado -->
                  <el-card v-else shadow="hover" class="mb-3">
                    <div slot="header" class="clearfix">
                      <span><strong>{{ contactoActual.nombres }} {{ contactoActual.apellidos }}</strong></span>
                      <el-tag size="small" type="success" class="ml-2">
                        {{ contactoCreado ? 'Creado' : 'Coincidencia completa' }}
                      </el-tag>
                    </div>
                    <el-descriptions :column="2" border size="small">
                      <el-descriptions-item label="ID">{{ contactoActual.id }}</el-descriptions-item>
                      <el-descriptions-item label="Cargo">{{ contactoActual.cargo || '-' }}</el-descriptions-item>
                      <el-descriptions-item label="Email">{{ contactoActual.email }}</el-descriptions-item>
                      <el-descriptions-item label="Teléfono">{{ contactoActual.telefono }}</el-descriptions-item>
                      <el-descriptions-item label="Empresa ID">{{ contactoActual.empresa_id }}</el-descriptions-item>
                    </el-descriptions>
                  </el-card>
                </div>
              </div>

            </div>
            <div class="form-actions text-right pt-2">
                <el-button class="second-buton" @click.prevent="close()">Cancelar</el-button>
                <el-button
                  type="primary"
                  native-type="submit"
                  :loading="loading_submit"
                  :disabled="!canConvert">
                  Convertir a Requerimiento
                </el-button>
            </div>
        </form>

      <!-- Diálogo para Crear Empresa -->
      <el-dialog
        title="Crear Empresa"
        :visible.sync="showCreateEmpresaDialog"
        width="500px"
        append-to-body
        :close-on-click-modal="false">
        <form @submit.prevent="submitCreateEmpresa">
          <div class="row">
            <div class="col-md-12">
              <div class="form-group" :class="{'has-danger': errorsEmpresa.ruc}">
                <label class="control-label">RUC *</label>
                <el-input 
                  v-model="formEmpresa.ruc" 
                  :disabled="!esRucGenerico"
                  maxlength="11"
                  placeholder="Ingrese RUC de 11 dígitos">
                </el-input>
                <small v-if="esRucGenerico" class="text-info d-block mt-1">
                  <i class="el-icon-info"></i> El RUC del lead es temporal, ingrese el RUC real de la empresa.
                </small>
                <small class="form-control-feedback" v-if="errorsEmpresa.ruc" v-text="errorsEmpresa.ruc[0]"></small>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group" :class="{'has-danger': errorsEmpresa.razon_social}">
                <label class="control-label">Razón Social *</label>
                <el-input v-model="formEmpresa.razon_social"></el-input>
                <small class="form-control-feedback" v-if="errorsEmpresa.razon_social" v-text="errorsEmpresa.razon_social[0]"></small>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group" :class="{'has-danger': errorsEmpresa.nombre_comercial}">
                <label class="control-label">Nombre Comercial</label>
                <el-input v-model="formEmpresa.nombre_comercial"></el-input>
                <small class="form-control-feedback" v-if="errorsEmpresa.nombre_comercial" v-text="errorsEmpresa.nombre_comercial[0]"></small>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group" :class="{'has-danger': errorsEmpresa.direccion}">
                <label class="control-label">Dirección</label>
                <el-input v-model="formEmpresa.direccion"></el-input>
                <small class="form-control-feedback" v-if="errorsEmpresa.direccion" v-text="errorsEmpresa.direccion[0]"></small>
              </div>
            </div>
          </div>
          <small class="text-muted d-block mb-3">Se creará la empresa y se actualizará la revisión del lead.</small>
          <div class="text-right">
            <el-button @click="showCreateEmpresaDialog = false">Cancelar</el-button>
            <el-button type="primary" native-type="submit" :loading="loadingEmpresa">Guardar</el-button>
          </div>
        </form>
      </el-dialog>

      <!-- Diálogo para Crear Contacto -->
      <el-dialog
        title="Crear Contacto"
        :visible.sync="showCreateContactoDialog"
        width="500px"
        append-to-body
        :close-on-click-modal="false">
        <form @submit.prevent="submitCreateContacto">
          <div class="row">
            <div class="col-md-12">
              <div class="form-group" :class="{'has-danger': errorsContacto.nombres}">
                <label class="control-label">Nombres *</label>
                <el-input v-model="formContacto.nombres"></el-input>
                <small class="form-control-feedback" v-if="errorsContacto.nombres" v-text="errorsContacto.nombres[0]"></small>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group" :class="{'has-danger': errorsContacto.apellidos}">
                <label class="control-label">Apellidos *</label>
                <el-input v-model="formContacto.apellidos"></el-input>
                <small class="form-control-feedback" v-if="errorsContacto.apellidos" v-text="errorsContacto.apellidos[0]"></small>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group" :class="{'has-danger': errorsContacto.cargo}">
                <label class="control-label">Cargo</label>
                <el-input v-model="formContacto.cargo"></el-input>
                <small class="form-control-feedback" v-if="errorsContacto.cargo" v-text="errorsContacto.cargo[0]"></small>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group" :class="{'has-danger': errorsContacto.telefono}">
                <label class="control-label">Teléfono *</label>
                <el-input v-model="formContacto.telefono"></el-input>
                <small class="form-control-feedback" v-if="errorsContacto.telefono" v-text="errorsContacto.telefono[0]"></small>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group" :class="{'has-danger': errorsContacto.email}">
                <label class="control-label">Email *</label>
                <el-input v-model="formContacto.email"></el-input>
                <small class="form-control-feedback" v-if="errorsContacto.email" v-text="errorsContacto.email[0]"></small>
              </div>
            </div>
            <div class="col-md-12">
              <el-alert
                type="info"
                :closable="false"
                show-icon>
                  <span slot="title">
                    Empresa: <strong>{{ empresaSeleccionada }}</strong> (ID: {{ empresaIdActual }})
                  </span>
              </el-alert>
            </div>
          </div>
          <small class="text-muted d-block mb-3 mt-2">Se creará el contacto y se actualizará la revisión del lead.</small>
          <div class="text-right">
            <el-button @click="showCreateContactoDialog = false">Cancelar</el-button>
            <el-button type="primary" native-type="submit" :loading="loadingContacto">Guardar</el-button>
          </div>
        </form>
      </el-dialog>




    </el-dialog>
</template>

<script>
export default {
  props: ['showReviewDialog', 'recordId'],
  data() {
    return {
      loading_submit: false,
      titleDialog: null,
      resource: 'comercial/leads',
      errors: {},
      form: {
        lead: {},
        empresa_detectada: null,
        contacto_detectado: null,
        contactos_coincidentes: [],
        estado_revision: '',
        estado_semaforo: '',
        sugerencias: []
      },

      showCreateEmpresaDialog: false,
      showCreateContactoDialog: false,

      formEmpresa: {
        id_lead: null,
        ruc: '',
        razon_social: '',
        nombre_comercial: '',
        direccion: ''
      },
      errorsEmpresa: {},
      loadingEmpresa: false,
      empresaCreada: null,

      // Formulario Contacto
      formContacto: {
        id_lead: null,
        empresa_id: null,
        nombres: '',
        apellidos: '',
        cargo: '',
        telefono: '',
        email: ''
      },
      errorsContacto: {},
      loadingContacto: false,
      contactoCreado: null,

      classNameEstadosRevision: {
        'Pendiente' : 'badge-warning',
        'En Revision' : 'badge-info',
        'Convertido': '',
        'Coincidencia Total' : 'badge-success',
        'Conflicto' : 'badge-danger'
      },

      // Lista de RUCs genéricos que no son válidos
      rucsGenericos: ['00000000000', '00000000001', '11111111111', '99999999999'],
    }
  },
  computed: {
    empresaIdActual() {
      if (this.empresaCreada) return this.empresaCreada.id;
      if (this.form.empresa_detectada) return this.form.empresa_detectada.id;
      return null;
    },
    empresaSeleccionada() {
      if (this.empresaCreada) return this.empresaCreada.razon_social;
      if (this.form.empresa_detectada) return this.form.empresa_detectada.razon_social;
      return 'Sin empresa';
    },
    contactoActual() {
      if (this.contactoCreado) return this.contactoCreado;
      if (this.form.contacto_detectado) return this.form.contacto_detectado;
      return null;
    },
    canConvert() {
      // Solo puede convertir si tiene empresa Y contacto
      return this.empresaIdActual && this.contactoActual;
    },
    // Computed para verificar si el RUC del lead es genérico
    esRucGenerico() {
      const ruc = this.form.lead.ruc || '';
      return !ruc || this.rucsGenericos.includes(ruc);
    }
  },
  methods: {
      onOpen() {
        this.create();
      },

      initForm() {
        this.errors = {};
        this.form = {
          lead: {},
          empresa_detectada: null,
          contacto_detectado: null,
          contactos_coincidentes: [],
          estado_revision: '',
          estado_semaforo: '',
          sugerencias: []
        };
        this.empresaCreada = null;
        this.contactoCreado = null;
      },
      async create() {
        this.titleDialog = 'Revisión de Lead';

        if (this.recordId) {
          try {
            const response = await this.$http.get(`/${this.resource}/review/${this.recordId}`);
            this.form = response.data.data || response.data;

            console.info('Lead con revisiones cargado:', this.form);

            // Inicializar formulario de empresa con RUC del lead (vacío si es genérico)
            this.formEmpresa.ruc = this.esRucGenerico ? '' : this.form.lead.ruc;

          } catch (error) {
            console.error('ERROR AL CARGAR:', error.response);
            this.$message.error('No se pudo cargar el registro para revisar');
            this.close();
          }
        } else {
          this.initForm();
        }
      },

      // Crear Empresa
      async submitCreateEmpresa() {
        // Validar que el RUC tenga 11 dígitos
        if (!this.formEmpresa.ruc || this.formEmpresa.ruc.length !== 11) {
          this.$message.error('El RUC debe tener 11 dígitos');
          return;
        }

        // Validar que no sea un RUC genérico
        if (this.rucsGenericos.includes(this.formEmpresa.ruc)) {
          this.$message.error('Por favor ingrese un RUC válido');
          return;
        }

        this.loadingEmpresa = true;
        this.errorsEmpresa = {};

        try {
          this.formEmpresa.id_lead = this.form.lead.id;
          console.log(this.formEmpresa);
          const response = await this.$http.post(`/${this.resource}/store/empresa/${this.recordId}`, this.formEmpresa);

          if (response.data.success) {
            this.$message.success('Empresa creada exitosamente');
            this.empresaCreada = response.data.data;
            this.showCreateEmpresaDialog = false;

            // Actualizar el contacto form con la nueva empresa
            this.formContacto.empresa_id = this.empresaCreada.id;

            // Recargar la revisión del lead
            await this.create();
          }
        } catch (error) {
          if (error.response && error.response.status === 422) {
            this.errorsEmpresa = error.response.data;
          } else {
            this.$message.error('Error al crear la empresa');
            console.error('Error:', error);
          }
        } finally {
          this.loadingEmpresa = false;
        }
      },

      // Crear Contacto
      async submitCreateContacto() {
        this.loadingContacto = true;
        this.errorsContacto = {};

        // Asignar empresa_id
        this.formContacto.empresa_id = this.empresaIdActual;

        if (!this.formContacto.empresa_id) {
          this.$message.error('Debe crear o seleccionar una empresa primero');
          this.loadingContacto = false;
          return;
        }

        try {
          this.formContacto.id_lead = this.form.lead.id;
          console.log(this.formContacto);

          const response = await this.$http.post(`/${this.resource}/store/contacto/${this.recordId}`, this.formContacto);

          if (response.data.success) {
            this.$message.success('Contacto creado exitosamente');
            this.contactoCreado = response.data.data;
            this.showCreateContactoDialog = false;

            // Recargar la revisión del lead
            await this.create();
          }
        } catch (error) {
          if (error.response && error.response.status === 422) {
            this.errorsContacto = error.response.data;
          } else {
            this.$message.error('Error al crear el contacto');
            console.error('Error:', error);
          }
        } finally {
          this.loadingContacto = false;
        }
      },

      async submit() {
        if (!this.canConvert) {
          this.$message.warning('Debe tener empresa y contacto para convertir a requerimiento');
          return;
        }

        this.loading_submit = true;

        const payload = {
          lead_id: this.form.lead.id,
          empresa_id: this.empresaIdActual,
          contacto_id: this.contactoActual.id
        };

        try {
          const response = await this.$http.post(`/${this.resource}/convert-to-requirement/${this.recordId}`, payload);

          if (response.data.success) {
            this.$message.success(response.data.message || 'Lead convertido a requerimiento exitosamente');
            this.$eventHub.$emit('reloadData');
            this.close();
          } else {
            this.$message.error(response.data.message);
          }
        } catch (error) {
          console.error('Error al convertir:', error.response);
          this.$message.error('Error al convertir el lead a requerimiento');
        } finally {
          this.loading_submit = false;
        }

      },

      close() {
        this.$emit('update:showReviewDialog', false);
        this.$emit('update:recordId', null);
        this.initForm();
      }
  },

  watch: {
    showCreateEmpresaDialog(val) {
      if (val) {
        // Reiniciar formulario de empresa
        // Si el RUC es genérico, dejarlo vacío para que el usuario lo ingrese
        this.formEmpresa = {
          ruc: this.esRucGenerico ? '' : this.form.lead.ruc,
          razon_social: this.form.lead.razon_social || '',
          nombre_comercial: '',
          direccion: ''
        };
        this.errorsEmpresa = {};
      }
    },
    showCreateContactoDialog(val) {
      if (val) {
        // Reiniciar formulario de contacto con datos del lead
        this.formContacto = {
          empresa_id: this.empresaIdActual,
          nombres: this.form.lead.contacto || '',
          apellidos: '',
          cargo: '',
          telefono: this.form.lead.telefono || '',
          email: this.form.lead.email || ''
        };
        this.errorsContacto = {};
      }
    }
  }
}
</script>

<style scoped>
.has-danger .el-input__inner,
.has-danger .el-textarea__inner {
  border-color: #f56c6c;
}

.form-control-feedback {
  color: #f56c6c;
  font-size: 12px;
  margin-top: 5px;
}

.el-card {
  border-radius: 4px;
}

.text-muted {
  color: #909399;
  font-size: 13px;
}

.text-info {
  color: #409eff;
  font-size: 12px;
}

.ml-2 {
  margin-left: 8px;
}

h5 {
  font-size: 15px;
  font-weight: 600;
  color: #303133;
}

.el-descriptions__title {
  font-size: 15px;
  font-weight: 600;
  font-family: 'Montserrat', sans-serif;
  letter-spacing: normal;
  line-height: 18px;
}
</style>
