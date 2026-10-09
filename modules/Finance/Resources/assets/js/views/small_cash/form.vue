<template>
  <el-dialog
    :title="titleDialog"
    :visible="showDialog"
    :close-on-click-modal="false"
    :close-on-press-escape="false"
    @close="handleCloseDialog"
    @open="create">

    <form autocomplete="off" @submit.prevent="submit">
      <div class="form-body">
        <div class="row">
          <div class="col-md-6" v-if="typeUser === 'admin'">
            <div class="form-group" :class="{'has-danger': errors.user_id}">
              <label class="control-label">Usuario *</label>
              <el-select
                v-model="form.user_id"
                :disabled="!!recordId"
                placeholder="Seleccionar usuario"
                filterable>
                <el-option
                  v-for="option in users"
                  :key="option.id"
                  :value="option.id"
                  :label="option.name">
                </el-option>
              </el-select>
              <small class="form-control-feedback" v-if="errors.user_id" v-text="errors.user_id[0]"></small>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group" :class="{'has-danger': errors.date_opening}">
              <label class="control-label">Fecha de Apertura *</label>
              <el-date-picker
                v-model="form.date_opening"
                type="date"
                placeholder="Seleccionar fecha"
                format="dd-MM-yyyy"
                value-format="yyyy-MM-dd"
                :disabled="!!recordId"
                style="width: 100%">
              </el-date-picker>
              <small class="form-control-feedback" v-if="errors.date_opening" v-text="errors.date_opening[0]"></small>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group" :class="{'has-danger': errors.beginning_balance}">
              <label class="control-label">Saldo Inicial *</label>
              <el-input
                v-model="form.beginning_balance"
                type="number"
                step="0.01"
                :disabled="!!recordId"
                placeholder="0.00">
              </el-input>
              <small class="form-control-feedback" v-if="errors.beginning_balance" v-text="errors.beginning_balance[0]"></small>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group" :class="{'has-danger': errors.reference_number}">
              <label class="control-label">Número de Referencia</label>
              <el-input
                v-model="form.reference_number"
                :maxlength="50"
                placeholder="Opcional">
              </el-input>
              <small class="form-control-feedback" v-if="errors.reference_number" v-text="errors.reference_number[0]"></small>
              <small class="text-muted">Ej: Número de caja general de origen</small>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group" :class="{'has-danger': errors.description}">
              <label class="control-label">Descripción</label>
              <el-input
                type="textarea"
                v-model="form.description"
                :rows="3"
                placeholder="Descripción opcional">
              </el-input>
              <small class="form-control-feedback" v-if="errors.description" v-text="errors.description[0]"></small>
            </div>
          </div>
        </div>
      </div>

      <div class="form-actions text-right mt-4">
        <el-button class="second-buton" @click.prevent="handleCloseDialog()">
          Cancelar
        </el-button>
        <el-button type="primary" native-type="submit" :loading="loading_submit">
          {{ loading_submit ? 'Guardando...' : 'Guardar' }}
        </el-button>
      </div>
    </form>
  </el-dialog>
</template>

<script>
export default {
  props: ['showDialog', 'recordId', 'typeUser', 'userId'],
  data() {
    return {
      loading_submit: false,
      titleDialog: null,
      errors: {},
      form: {},
      users: [],
      originalForm: null
    };
  },
  async created() {
    await this.loadUsers();
    this.initForm();
  },
  methods: {
    async loadUsers() {
      if (this.typeUser === 'admin') {
        try {
          const response = await this.$http.get('/finances/small-cash/users');
          this.users = response.data.data || [];
        } catch (error) {
          console.error('Error cargando usuarios:', error);
        }
      }
    },
    handleCloseDialog() {
      this.forceCloseDialog();
    },
    forceCloseDialog() {
      this.initForm();
      this.$emit('update:showDialog', false);
    },
    initForm() {
      this.errors = {};
      const today = moment().format('YYYY-MM-DD');

      this.form = {
        id: null,
        user_id: this.userId,
        date_opening: today,
        beginning_balance: 0,
        reference_number: null,
        description: null
      };
      this.originalForm = JSON.stringify(this.form);
    },
    async create() {
      this.titleDialog = this.recordId ? 'Editar Caja Chica' : 'Aperturar Caja Chica';

      if (this.recordId) {
        try {
          const response = await this.$http.get(`/finances/small-cash/${this.recordId}`);
          if (response.data.success) {
            this.form = response.data.data;
          }
        } catch (error) {
          console.error('Error cargando registro:', error);
        }
      } else {
        this.form.user_id = this.userId;
      }
    },
    async checkOpenSmallCash() {
      try {
        const response = await this.$http.get('/finances/small-cash/open', {
          params: { user_id: this.form.user_id }
        });
        return response.data.data !== null;
      } catch (error) {
        return false;
      }
    },
    async submit() {
      this.loading_submit = true;
      this.errors = {};

      // Validar que no tenga caja abierta si es nueva
      if (!this.recordId) {
        const hasOpenCash = await this.checkOpenSmallCash();
        if (hasOpenCash) {
          this.$message({
            message: 'El usuario ya tiene una caja chica abierta. Por favor ciérrela antes de abrir una nueva.',
            type: 'warning',
            duration: 5000
          });
          this.loading_submit = false;
          return;
        }
      }

      const url = this.recordId
        ? `/finances/small-cash/${this.recordId}`
        : '/finances/small-cash';

      const method = this.recordId ? 'put' : 'post';

      this.$http[method](url, this.form)
        .then(response => {
          if (response.data.success) {
            this.$message.success(response.data.message);
            this.close();
            this.$emit('reload');
          } else {
            this.$message.error(response.data.message);
          }
        })
        .catch(error => {
          if (error.response && error.response.status === 422) {
            this.errors = error.response.data.errors || error.response.data;
          } else {
            const message = error.response?.data?.message || 'Error al guardar';
            this.$message.error(message);
          }
        })
        .then(() => {
          this.loading_submit = false;
        });
    },
    close() {
      this.$emit('update:showDialog', false);
      this.initForm();
    }
  }
};
</script>
