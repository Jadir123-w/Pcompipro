<template>
  <div>
    <data-table
      :resource="resource"
      :apply-filter="applyFilter"
      :product-type="productType"
      :extra-params="extraParams"
      ref="dataTable"
    >
      <!-- Filtros adicionales de la vista (junto al buscador) -->
      <template v-slot:filters>
        <slot name="filters"></slot>
      </template>

      <!-- Pasar el slot de heading -->
      <template v-slot:heading>
        <slot name="heading"></slot>
      </template>

      <!-- Slot personalizado para las filas con expansión -->
      <template v-slot="{ row, index }">
        <!-- Fila original -->
        <tr :key="'row-' + row.id">
          <slot name="row" :row="row" :index="index" />
        </tr>

        <!-- Fila expandida -->
        <tr
          v-if="expandedRows.includes(row.id)"
          :key="'expanded-' + row.id"
          class="expanded-row"
        >
          <td :colspan="colspanCount" class="p-0">
            <slot
              name="expandable-content"
              :row="row"
              :index="index"
              :close="() => closeExpand(row.id)"
            />
          </td>
        </tr>
      </template>
    </data-table>
  </div>
</template>

<script>
import DataTable from './Datatable.vue';

export default {
  components: {
    DataTable
  },
  props: {
    resource: String,
    applyFilter: {
      type: Boolean,
      default: true
    },
    productType: {
      type: String,
      default: ''
    },
    colspanCount: {
      type: Number,
      required: true
    },
    extraParams: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      expandedRows: []
    }
  },
  methods: {
    toggleExpand(rowId) {
      const index = this.expandedRows.indexOf(rowId);
      if (index > -1) {
        this.expandedRows.splice(index, 1);
      } else {
        // Cerrar otros expands si es necesario
        this.expandedRows = [rowId];
      }
    },
    closeExpand(rowId) {
      const index = this.expandedRows.indexOf(rowId);
      if (index > -1) {
        this.expandedRows.splice(index, 1);
      }
    },
    closeAllExpands() {
      this.expandedRows = [];
    },
    getRecords() {
      return this.$refs.dataTable.getRecords();
    },
    getSearch() {
      return this.$refs.dataTable.getSearch();
    }
  }
}
</script>

<style scoped>
.expanded-row {
  background-color: #f8fafc;
  border-left: 4px solid #007bff;
  animation: expand 0.3s ease-out;
}

@keyframes expand {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
