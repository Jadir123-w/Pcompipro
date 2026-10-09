<template>
  <div class="table-responsive">
    <table class="table table-sm table-bordered table-hover">
      <thead>
      <tr>
        <th width="100">Fecha</th>
        <th width="80">Tipo</th>
        <th>Descripción</th>
        <th width="120">Método Pago</th>
        <th width="100">Documento</th>
        <th width="150">Proveedor/Cliente</th>
        <th width="120">Categoría / Tipo de gasto</th>
        <th width="140">Ruta</th>
        <th width="150">Proyecto</th>
        <th width="100" class="text-right">Monto</th>
        <th width="120">Responsable</th>
        <th width="150">Usuario</th>
        <th v-if="editable" width="120" class="text-center">Acciones</th>
      </tr>
      </thead>
      <tbody v-if="transactions.length === 0">
      <tr>
        <td :colspan="editable ? 13 : 12" class="text-center text-muted">
          No hay movimientos registrados
        </td>
      </tr>
      </tbody>
      <tbody v-else>
      <tr v-for="transaction in transactions" :key="transaction.id">
        <td>{{ formatDate(transaction.date) }}</td>
        <td>
                        <span :class="getTypeClass(transaction.type)">
                            {{ getTypeLabel(transaction.type) }}
                        </span>
        </td>
        <td>{{ transaction.description }}</td>
        <td>
          {{ transaction.payment_method_type ? transaction.payment_method_type.description : '-' }}
        </td>
        <td>{{ transaction.document_number || '-' }}</td>
        <td>{{ transaction.supplier_customer || '-' }}</td>
        <td>{{ transaction.category || '-' }}</td>
        <td>{{ transaction.ruta || '-' }}</td>
        <td>{{ transaction.proyecto || '-' }}</td>
        <td class="text-right" :class="getAmountClass(transaction.type)">
          {{ formatMoney(transaction.amount) }}
        </td>
        <td>{{ transaction.responsable || '-' }}</td>
        <td>{{ transaction.user ? transaction.user.name : '-' }}</td>
        <td v-if="editable" class="text-center">
          <button
            type="button"
            class="btn waves-effect waves-light btn-xs btn-info"
            @click="$emit('edit', transaction)"
            title="Editar">
            <i class="fa fa-edit"></i>
          </button>
          <button
            type="button"
            class="btn waves-effect waves-light btn-xs btn-danger"
            @click="$emit('delete', transaction)"
            title="Eliminar">
            <i class="fa fa-trash"></i>
          </button>
        </td>
      </tr>
      </tbody>
      <tfoot v-if="transactions.length > 0">
      <tr class="font-weight-bold bg-light">
        <td colspan="9" class="text-right">TOTAL:</td>
        <td class="text-right">{{ formatMoney(total) }}</td>
        <td :colspan="editable ? 3 : 2"></td>
      </tr>
      </tfoot>
    </table>
  </div>
</template>

<script>
export default {
  name: 'TransactionTable',
  props: {
    transactions: {
      type: Array,
      required: true,
      default: () => []
    },
    editable: {
      type: Boolean,
      default: false
    }
  },
  computed: {
    total() {
      return this.transactions.reduce((sum, t) => sum + parseFloat(t.amount || 0), 0);
    }
  },
  methods: {
    formatMoney(value) {
      return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2
      }).format(value || 0);
    },
    formatDate(date) {
      if (!date) return '-';
      const parsedDate = moment(date, "YYYY-MM-DD");
      return parsedDate.isValid() ? parsedDate.format("DD-MM-YYYY") : '-';
    },
    getTypeClass(type) {
      return type === 'income' ? 'badge badge-success' : 'badge badge-danger';
    },
    getTypeLabel(type) {
      return type === 'income' ? 'Ingreso' : 'Egreso';
    },
    getAmountClass(type) {
      return type === 'income' ? 'text-success font-weight-bold' : 'text-danger font-weight-bold';
    }
  }
};
</script>

<style scoped>
.badge {
  padding: 5px 8px;
  border-radius: 3px;
  font-size: 10px;
  font-weight: 600;
}
.badge-success {
  background-color: #28a745;
  color: white;
}
.badge-danger {
  background-color: #dc3545;
  color: white;
}
.table td {
  vertical-align: middle;
}
</style>
