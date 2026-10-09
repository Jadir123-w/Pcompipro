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
          <div class="col-md-6">
            <div class="form-group" :class="{'has-danger': errors.date}">
              <label class="control-label">Fecha *</label>
              <el-date-picker
                v-model="form.date"
                type="date"
                placeholder="Seleccionar fecha"
                format="dd-MM-yyyy"
                value-format="yyyy-MM-dd"
                style="width: 100%">
              </el-date-picker>
              <small class="form-control-feedback" v-if="errors.date" v-text="errors.date[0]"></small>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group" :class="{'has-danger': errors.amount}">
              <label class="control-label">Monto *</label>
              <el-input
                v-model="form.amount"
                type="number"
                step="0.01"
                placeholder="0.00">
                <template slot="prepend">S/</template>
              </el-input>
              <small class="form-control-feedback" v-if="errors.amount" v-text="errors.amount[0]"></small>
            </div>
          </div>

          <div class="col-md-12">
            <div class="form-group" :class="{'has-danger': errors.description}">
              <label class="control-label">Descripción *</label>
              <el-input
                type="textarea"
                v-model="form.description"
                :rows="2"
                placeholder="Descripción del movimiento">
              </el-input>
              <small class="form-control-feedback" v-if="errors.description" v-text="errors.description[0]"></small>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group" :class="{'has-danger': errors.payment_method_type_id}">
              <label class="control-label">Método de Pago</label>
              <el-select
                v-model="form.payment_method_type_id"
                placeholder="Seleccionar"
                clearable
                filterable
                style="width: 100%">
                <el-option
                  v-for="method in paymentMethods"
                  :key="method.id"
                  :value="method.id"
                  :label="method.description">
                </el-option>
              </el-select>
              <small class="form-control-feedback" v-if="errors.payment_method_type_id" v-text="errors.payment_method_type_id[0]"></small>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group" :class="{'has-danger': errors.document_number}">
              <label class="control-label">Número de Documento</label>
              <el-input
                v-model="form.document_number"
                placeholder="Opcional - Ej: B001-0001">
              </el-input>
              <small class="form-control-feedback" v-if="errors.document_number" v-text="errors.document_number[0]"></small>
            </div>
          </div>

          <!-- Egresos: proveedor tomado del maestro; puede quedar vacío -->
          <div class="col-md-6" v-if="isExpense">
            <div class="form-group" :class="{'has-danger': errors.supplier_id}">
              <label class="control-label">Proveedor</label>
              <el-select
                v-model="form.supplier_id"
                placeholder="Buscar proveedor (opcional)"
                clearable
                filterable
                style="width: 100%"
                @change="changeSupplier">
                <el-option
                  v-for="supplier in suppliers"
                  :key="supplier.id"
                  :value="supplier.id"
                  :label="supplier.description">
                </el-option>
              </el-select>
              <small class="text-muted" v-if="!form.supplier_id && form.supplier_customer">
                Registrado antes como: {{ form.supplier_customer }}
              </small>
              <small class="text-muted" v-else-if="!form.supplier_id">
                Déjelo vacío en movilidad, taxis, pasajes o gastos de feria.
              </small>
              <small class="form-control-feedback" v-if="errors.supplier_id" v-text="errors.supplier_id[0]"></small>
            </div>
          </div>

          <!-- Ingresos: se mantiene el cliente como texto libre -->
          <div class="col-md-6" v-else>
            <div class="form-group" :class="{'has-danger': errors.supplier_customer}">
              <label class="control-label">Cliente</label>
              <el-input
                v-model="form.supplier_customer"
                placeholder="Nombre del cliente">
              </el-input>
              <small class="form-control-feedback" v-if="errors.supplier_customer" v-text="errors.supplier_customer[0]"></small>
            </div>
          </div>

          <div class="col-md-6">
            <div class="form-group" :class="{'has-danger': errors.category}">
              <label class="control-label">Categoría / Tipo de gasto</label>
              <el-input
                v-model="form.category"
                placeholder="Ej: Útiles, Transporte, Alimentación">
              </el-input>
              <small class="form-control-feedback" v-if="errors.category" v-text="errors.category[0]"></small>
            </div>
          </div>

          <!-- Campos propios del gasto -->
          <template v-if="isExpense">
            <div class="col-md-6">
              <div class="form-group" :class="{'has-danger': errors.ruta}">
                <label class="control-label">Ruta</label>
                <el-input
                  v-model="form.ruta"
                  placeholder="Ej: Oficina - Jesús María">
                </el-input>
                <small class="form-control-feedback" v-if="errors.ruta" v-text="errors.ruta[0]"></small>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-group" :class="{'has-danger': errors.proyecto}">
                <label class="control-label">Proyecto</label>
                <el-select
                  v-model="form.proyecto"
                  placeholder="Seleccionar o escribir (opcional)"
                  clearable
                  filterable
                  allow-create
                  default-first-option
                  style="width: 100%">
                  <el-option
                    v-for="proyecto in proyectos"
                    :key="proyecto.id"
                    :value="proyecto.nombre"
                    :label="proyecto.nombre">
                  </el-option>
                </el-select>
                <small class="text-muted">
                  Elija un proyecto o escriba un texto libre (ej. Feria Compipro).
                </small>
                <small class="form-control-feedback" v-if="errors.proyecto" v-text="errors.proyecto[0]"></small>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-group" :class="{'has-danger': errors.responsable}">
                <label class="control-label">Responsable</label>
                <el-input
                  v-model="form.responsable"
                  placeholder="Persona que ejecutó el gasto. Ej: Miguel">
                </el-input>
                <small class="form-control-feedback" v-if="errors.responsable" v-text="errors.responsable[0]"></small>
              </div>
            </div>
          </template>
        </div>
      </div>

      <div class="form-actions text-right mt-4">
        <el-button class="second-buton" @click.prevent="handleCloseDialog()">
          Cancelar
        </el-button>
        <el-button
          :type="transactionType === 'income' ? 'success' : 'danger'"
          native-type="submit"
          :loading="loading_submit">
          <i :class="transactionType === 'income' ? 'fa fa-arrow-up' : 'fa fa-arrow-down'"></i>
          {{ loading_submit ? 'Guardando...' : 'Guardar' }}
        </el-button>
      </div>
    </form>
  </el-dialog>
</template>

<script>
export default {
  props: ['showDialog', 'smallCashId', 'transactionId', 'transactionType'],
  data() {
    return {
      loading_submit: false,
      errors: {},
      form: {},
      paymentMethods: [],
      suppliers: [],
      proyectos: [],
      originalForm: null
    };
  },
  computed: {
    titleDialog() {
      const action = this.transactionId ? 'Editar' : 'Registrar';
      const type = this.transactionType === 'income' ? 'Ingreso' : 'Egreso';
      return `${action} ${type}`;
    },
    isExpense() {
      return this.transactionType === 'expense';
    }
  },
  async created() {
    await this.loadPaymentMethods();
    await this.loadSuppliers();
    await this.loadProyectos();
    this.initForm();
  },
  methods: {
    async loadPaymentMethods() {
      try {
        const response = await this.$http.get('/finances/small-cash/payment-methods');
        this.paymentMethods = response.data.data || [];
      } catch (error) {
        console.error('Error cargando métodos de pago:', error);
      }
    },
    async loadSuppliers() {
      try {
        const response = await this.$http.get('/finances/small-cash/suppliers');
        this.suppliers = response.data.data || [];
      } catch (error) {
        console.error('Error cargando proveedores:', error);
      }
    },
    async loadProyectos() {
      try {
        const response = await this.$http.get('/finances/small-cash/proyectos');
        this.proyectos = response.data.data || [];
      } catch (error) {
        console.error('Error cargando proyectos:', error);
      }
    },
    changeSupplier(supplierId) {
      // Se guarda también el nombre para que el listado y el reporte no dependan
      // de una consulta adicional.
      if (!supplierId) {
        this.form.supplier_customer = null;
        return;
      }

      var seleccionado = null;
      for (var i = 0; i < this.suppliers.length; i++) {
        if (this.suppliers[i].id === supplierId) {
          seleccionado = this.suppliers[i];
          break;
        }
      }

      this.form.supplier_customer = seleccionado ? seleccionado.name : null;
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
        small_cash_id: this.smallCashId,
        date: today,
        type: this.transactionType,
        payment_method_type_id: null,
        amount: 0,
        description: null,
        document_number: null,
        supplier_customer: null,
        supplier_id: null,
        category: null,
        ruta: null,
        proyecto: null,
        responsable: null
      };
      this.originalForm = JSON.stringify(this.form);
    },
    async create() {
      if (this.transactionId) {
        try {
          const response = await this.$http.get(`/finances/small-cash-transactions/${this.transactionId}`);
          if (response.data.success) {
            this.form = response.data.data;
          }
        } catch (error) {
          console.error('Error cargando transacción:', error);
        }
      } else {
        this.form.small_cash_id = this.smallCashId;
        this.form.type = this.transactionType;
      }
    },
    async submit() {
      this.loading_submit = true;
      this.errors = {};

      const url = this.transactionId
        ? `/finances/small-cash-transactions/${this.transactionId}`
        : '/finances/small-cash-transactions';

      const method = this.transactionId ? 'put' : 'post';

      this.$http[method](url, this.form)
        .then(response => {
          if (response.data.success) {
            this.$message.success(response.data.message);
            this.$emit('reload');
            this.close();
          } else {
            this.$message.error(response.data.message);
          }
        })
        .catch(error => {
          if (error.response && error.response.status === 422) {
            this.errors = error.response.data.errors || error.response.data;
          } else if (error.response && error.response.status === 400) {
            this.$message.error(error.response.data.message);
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