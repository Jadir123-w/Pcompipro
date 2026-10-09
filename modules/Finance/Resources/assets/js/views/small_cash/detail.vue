<template>
  <el-dialog
    title="Detalle de Caja Chica"
    :visible="showDialog"
    :close-on-click-modal="false"
    :close-on-press-escape="false"
    width="90%"
    @close="close"
    @open="loadData">

    <div v-loading="loading">
      <!-- Resumen de Caja -->
      <div class="row mb-4">
        <div class="col-md-12">
          <h4>{{ smallCash.code }} - {{ smallCash.user && smallCash.user.name }}</h4>
          <p v-if="smallCash.description" class="text-muted">{{ smallCash.description }}</p>
        </div>
      </div>

      <div class="row mb-4">
        <div class="col-md-3">
          <div class="card bg-light">
            <div class="card-body text-center">
              <h6 class="text-muted mb-2">Saldo Inicial</h6>
              <h4 class="mb-0">{{ formatMoney(smallCash.beginning_balance) }}</h4>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-success text-green-dark">
            <div class="card-body text-center">
              <h6 class="mb-2">Total Ingresos</h6>
              <h4 class="mb-0">{{ formatMoney(smallCash.income) }}</h4>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-danger text-red-darkest">
            <div class="card-body text-center">
              <h6 class="mb-2">Total Egresos</h6>
              <h4 class="mb-0">{{ formatMoney(smallCash.expense) }}</h4>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-primary text-black">
            <div class="card-body text-center">
              <h6 class="mb-2">Saldo Actual</h6>
              <h4 class="mb-0">{{ formatMoney(smallCash.final_balance) }}</h4>
            </div>
          </div>
        </div>
      </div>

      <!-- Información Adicional -->
      <div class="row mb-3">
        <div class="col-md-6">
          <p><strong>Fecha Apertura:</strong> {{ formatDate(smallCash.date_opening) }}</p>
          <p><strong>Fecha Cierre:</strong> {{ formatDate(smallCash.date_closed) || 'Sin cerrar' }}</p>
        </div>
        <div class="col-md-6">
          <p>
            <strong>Estado:</strong>
            <span :class="getStateClass(smallCash.state)">
                            {{ getStateLabel(smallCash.state) }}
                        </span>
          </p>
          <p v-if="smallCash.reference_number">
            <strong>Referencia:</strong> {{ smallCash.reference_number }}
          </p>
        </div>
      </div>

      <!-- Botones de acción -->
      <div class="mb-3" v-if="smallCash.state === 'open' && smallCash.user_id === userId">
        <button
          class="btn btn-success btn-sm"
          @click="clickAddTransaction('income')">
          <i class="fa fa-plus"></i> Registrar Ingreso
        </button>
        <button
          class="btn btn-danger btn-sm"
          @click="clickAddTransaction('expense')">
          <i class="fa fa-minus"></i> Registrar Egreso
        </button>
      </div>

      <!-- Pestañas -->
      <el-tabs v-model="activeTab" @tab-click="handleTabClick">
        <el-tab-pane label="Todos" name="all">
                    <span slot="label">
                        Todos ({{ transactions.length }})
                    </span>
          <transaction-table
            :transactions="transactions"
            :editable="smallCash.state === 'open' && smallCash.user_id === userId"
            @edit="editTransaction"
            @delete="deleteTransaction">
          </transaction-table>
        </el-tab-pane>

        <el-tab-pane label="Ingresos" name="income">
                    <span slot="label">
                        Ingresos ({{ incomeTransactions.length }})
                    </span>
          <transaction-table
            :transactions="incomeTransactions"
            :editable="smallCash.state === 'open' && smallCash.user_id === userId"
            @edit="editTransaction"
            @delete="deleteTransaction">
          </transaction-table>
        </el-tab-pane>

        <el-tab-pane label="Egresos" name="expense">
                    <span slot="label">
                        Egresos ({{ expenseTransactions.length }})
                    </span>
          <transaction-table
            :transactions="expenseTransactions"
            :editable="smallCash.state === 'open' && smallCash.user_id === userId"
            @edit="editTransaction"
            @delete="deleteTransaction">
          </transaction-table>
        </el-tab-pane>
      </el-tabs>
    </div>

    <span slot="footer" class="dialog-footer">
            <el-button @click="close">Cerrar</el-button>
        </span>

    <!-- Modal de transacción -->
    <tenant-finance-small-cash-transaction-form
      :showDialog.sync="showTransactionDialog"
      :smallCashId="recordId"
      :transactionId="selectedTransactionId"
      :transactionType="transactionType"
      @reload="loadData">
    </tenant-finance-small-cash-transaction-form>
  </el-dialog>
</template>

<script>
import TransactionTable from './transaction-table.vue';
import SmallCashTransactionForm from './transaction-form.vue';

export default {
  components: { TransactionTable, SmallCashTransactionForm },
  props: ['showDialog', 'recordId', 'userId', 'typeUser'],
  data() {
    return {
      loading: false,
      smallCash: {},
      transactions: [],
      activeTab: 'all',
      showTransactionDialog: false,
      selectedTransactionId: null,
      transactionType: 'income'
    };
  },
  computed: {
    incomeTransactions() {
      return this.transactions.filter(t => t.type === 'income');
    },
    expenseTransactions() {
      return this.transactions.filter(t => t.type === 'expense');
    }
  },
  methods: {
    async loadData() {
      if (!this.recordId) return;

      this.loading = true;
      try {
        const response = await this.$http.get(`/finances/small-cash/${this.recordId}`);
        if (response.data.success) {
          this.smallCash = response.data.data;
          this.transactions = response.data.data.transactions || [];
        }
      } catch (error) {
        this.$message.error('Error al cargar datos');
        console.error(error);
      } finally {
        this.loading = false;
      }
    },
    handleTabClick(tab) {
      // Lógica adicional si es necesaria al cambiar de tab
    },
    clickAddTransaction(type) {
      this.transactionType = type;
      this.selectedTransactionId = null;
      this.showTransactionDialog = true;
    },
    editTransaction(transaction) {
      this.selectedTransactionId = transaction.id;
      this.transactionType = transaction.type;
      this.showTransactionDialog = true;
    },
    deleteTransaction(transaction) {
      this.$confirm(
        '¿Está seguro de eliminar este movimiento?',
        'Confirmar eliminación',
        {
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar',
          type: 'warning'
        }
      ).then(async () => {
        try {
          const response = await this.$http.delete(`/finances/small-cash-transactions/${transaction.id}`);
          if (response.data.success) {
            this.$message.success(response.data.message);
            this.loadData();
            this.$emit('reload');
          }
        } catch (error) {
          const message = error.response?.data?.message || 'Error al eliminar';
          this.$message.error(message);
        }
      }).catch(() => {});
    },
    formatMoney(value) {
      return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2
      }).format(value || 0);
    },
    formatDate(date) {
      if (!date) return null;
      const parsedDate = moment(date, "YYYY-MM-DD");
      return parsedDate.isValid() ? parsedDate.format("DD-MM-YYYY") : null;
    },
    getStateClass(state) {
      return state === 'open' ? 'badge badge-success' : 'badge badge-secondary';
    },
    getStateLabel(state) {
      return state === 'open' ? 'Abierta' : 'Cerrada';
    },
    close() {
      this.$emit('update:showDialog', false);
      this.activeTab = 'all';
    }
  }
};
</script>

<style scoped>
.badge {
  padding: 5px 10px;
  border-radius: 3px;
  font-size: 11px;
  font-weight: 600;
}
.badge-success {
  background-color: #28a745;
  color: white;
}
.badge-secondary {
  background-color: #6c757d;
  color: white;
}
</style>
