<template>
  <div class="small-cash">
    <div class="page-header pr-0">
      <h2>
        <a href="/finances/small-cash">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            style="margin-top: -5px;"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="icon icon-tabler icons-tabler-outline icon-tabler-wallet"
          >
            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
            <path d="M17 8v-3a1 1 0 0 0 -1 -1h-10a2 2 0 0 0 0 4h12a1 1 0 0 1 1 1v3m0 4v3a1 1 0 0 1 -1 1h-12a2 2 0 0 1 -2 -2v-12" />
            <path d="M20 12v4h-4a2 2 0 0 1 0 -4h4" />
          </svg>
        </a>
      </h2>
      <ol class="breadcrumbs">
        <li class="active"><span>Caja Chica</span></li>
      </ol>
      <div class="right-wrapper pull-right">
        <button
          type="button"
          class="btn btn-custom btn-sm mt-2 mr-2"
          @click.prevent="clickExportListExcel()"
        >
          <i class="fas fa-file-excel"></i> Exportar Listado
        </button>

        <button
          type="button"
          class="btn btn-custom btn-sm mt-2 mr-2"
          @click.prevent="clickCreate()"
        >
          <i class="fas fa-plus"></i> Nueva Caja Chica
        </button>
      </div>
    </div>

    <div class="card tab-content-default row-new mb-0">
      <div class="card-body">

        <data-table :resource="resource" :type-user="typeUser" :users="users">
          <tr slot="heading">
            <th>Código</th>
            <th>Area</th>
            <th class="text-left">F. Apertura</th>
            <th class="text-left">F. Cierre</th>
            <th class="text-right">Saldo Inicial</th>
            <th class="text-right">Ingresos</th>
            <th class="text-right">Egresos</th>
            <th class="text-right">Saldo Final</th>
            <th>Estado</th>
            <th class="text-right">Acciones</th>
          </tr>

          <tr slot-scope="{ index, row }">
            <td>{{ row.code }}</td>
            <td>{{ row.user.type }}</td>
            <td class="text-left">{{ formatDate(row.date_opening) }}</td>
            <td class="text-left">{{ formatDate(row.date_closed) }}</td>
            <td class="text-right">{{ formatMoney(row.beginning_balance) }}</td>
            <td class="text-right text-success">{{ formatMoney(row.income) }}</td>
            <td class="text-right text-danger">{{ formatMoney(row.expense) }}</td>
            <td class="text-right font-weight-bold">{{ formatMoney(row.final_balance) }}</td>
            <td>
                            <span :class="getStateClass(row.state)">
                                {{ getStateLabel(row.state) }}
                            </span>
            </td>
            <td class="text-right">
              <button
                type="button"
                class="btn waves-effect waves-light btn-xs btn-info"
                @click.prevent="clickView(row.id)"
              >
                Ver Detalle
              </button>

              <template v-if="row.state === 'open'">
                <button v-if="typeUser === 'finance' || typeUser === 'admin' "
                        type="button"
                        class="btn waves-effect waves-light btn-xs btn-default"
                        @click="clickEdit(row.id)">
                  Editar
                </button>
                <button
                  v-if="row.user_id === userId"
                  type="button"
                  class="btn waves-effect waves-light btn-xs btn-success"
                  @click.prevent="clickAddTransaction(row.id, 'income')"
                >
                  Ingreso
                </button>
                <button
                  v-if="row.user_id === userId"
                  type="button"
                  class="btn waves-effect waves-light btn-xs btn-danger"
                  @click.prevent="clickAddTransaction(row.id, 'expense')"
                >
                  Egreso
                </button>
                <button
                  type="button"
                  class="btn waves-effect waves-light btn-xs btn-warning"
                  @click.prevent="clickCloseSmallCash(row.id)"
                >
                  Cerrar
                </button>
              </template>

              <template v-if="typeUser === 'admin'">
                <div class="btn-group flex-wrap">
                  <button
                    type="button"
                    class="btn waves-effect waves-light btn-xs btn-primary dropdown-toggle"
                    data-toggle="dropdown"
                    aria-expanded="false"
                  >
                    Reportes <span class="caret"></span>
                  </button>
                  <div class="dropdown-menu">
                    <a
                      class="dropdown-item text-1"
                      href="#"
                      @click.prevent="clickExportExcel(row.id)"
                    >
                      Excel
                    </a>
                    <a
                      class="dropdown-item text-1"
                      href="#"
                      @click.prevent="clickExportPDF(row.id)"
                    >
                      PDF
                    </a>
                  </div>
                </div>
              </template>
            </td>
          </tr>
        </data-table>
      </div>
    </div>

    <!-- Formulario de Apertura/Edición -->
    <tenant-finance-small-cash-form
      :showDialog.sync="showDialog"
      :typeUser="typeUser"
      :recordId="recordId"
      :userId="userId"
      @reload="reload"
    ></tenant-finance-small-cash-form>

    <!-- Modal de Detalle -->
    <tenant-finance-small-cash-detail
      :showDialog.sync="showDialogDetail"
      :recordId="recordId"
      :userId="userId"
      :typeUser="typeUser"
      @reload="reload"
    ></tenant-finance-small-cash-detail>

    <!-- Formulario de Transacción -->
    <tenant-finance-small-cash-transaction-form
      :showDialog.sync="showDialogTransaction"
      :smallCashId="recordId"
      :transactionType="transactionType"
      @reload="reload"
    ></tenant-finance-small-cash-transaction-form>
  </div>
</template>

<script>
import DataTable from "../../components/DatatableSmallCash.vue";
import { deletable } from "../../../../../../../resources/js/mixins/deletable";
import SmallCashForm from "./form.vue";
import SmallCashDetail from "./detail.vue";
import SmallCashTransactionForm from "./transaction-form.vue";

export default {
  mixins: [deletable],
  components: {
    DataTable,
    SmallCashForm,
    SmallCashDetail,
    SmallCashTransactionForm
  },
  props: ["typeUser", "userId"],
  data() {
    return {
      showDialog: false,
      showDialogDetail: false,
      showDialogTransaction: false,
      resource: "finances/small-cash",
      recordId: null,
      transactionType: 'income',
      users: [],
      filters: {
        state: '',
        user_id: null
      }
    };
  },
  async created() {
    await this.loadUsers();
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
    formatDate(date) {
      if (!date) return '-';
      const parsedDate = moment(date, "DD-MM-YYYY");
      return parsedDate.isValid() ? parsedDate.format("DD-MM-YYYY") : '-';
    },
    formatMoney(value) {
      return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 2
      }).format(value || 0);
    },
    getStateClass(state) {
      return state === 'open' ? 'badge badge-success' : 'badge badge-secondary';
    },
    getStateLabel(state) {
      return state === 'open' ? 'Abierta' : 'Cerrada';
    },
    clickCreate(recordId = null) {
      this.recordId = recordId;
      this.showDialog = true;
    },
    clickEdit(recordId) {
      this.recordId = recordId;  // ← Clave: con valor = editar
      this.showDialog = true;
    },
    clickView(recordId) {
      this.recordId = recordId;
      this.showDialogDetail = true;
    },
    clickAddTransaction(recordId, type) {
      this.recordId = recordId;
      this.transactionType = type;
      this.showDialogTransaction = true;
    },
    clickCloseSmallCash(recordId) {
      this.recordId = recordId;
      const h = this.$createElement;
      this.$msgbox({
        title: "Cerrar Caja Chica",
        type: "warning",
        message: h("p", null, [
          h(
            "p",
            { style: "text-align: justify; font-size:15px" },
            "¿Está seguro de cerrar esta caja chica? Esta acción no se puede revertir."
          )
        ]),
        showCancelButton: true,
        confirmButtonText: "Cerrar",
        cancelButtonText: "Cancelar",
        beforeClose: (action, instance, done) => {
          if (action === "confirm") {
            this.closeSmallCash(instance, done);
          } else {
            done();
          }
        }
      })
        .then(action => {})
        .catch(action => {});
    },
    closeSmallCash(instance, done) {
      instance.confirmButtonLoading = true;
      instance.confirmButtonText = "Cerrando caja...";

      this.$http
        .post(`/finances/small-cash/${this.recordId}/close`)
        .then(response => {
          if (response.data.success) {
            this.$message.success(response.data.message);
            this.reload();
          } else {
            this.$message.error(response.data.message);
          }
        })
        .catch(error => {
          const message = error.response?.data?.message || 'Error al cerrar la caja';
          this.$message.error(message);
        })
        .then(() => {
          instance.confirmButtonLoading = false;
          done();
        });
    },
    clickExportExcel(id) {
      window.open(`/finances/small-cash/${id}/export/excel`, '_blank');
    },
    clickExportPDF(id) {
      window.open(`/finances/small-cash/${id}/export/pdf`, '_blank');
    },
    clickExportListExcel() {
      const params = new URLSearchParams();
      if (this.filters.state) params.append('state', this.filters.state);
      if (this.filters.user_id) params.append('user_id', this.filters.user_id);
      window.open(`/finances/small-cash/export/list/excel?${params.toString()}`, '_blank');
    },
    reload() {
      this.$eventHub.$emit('reloadData');
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
