<template>
    <div class="cash">
        <div class="page-header pr-0">
            <h2>
                <a href="/cash">
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
                        class="icon icon-tabler icons-tabler-outline icon-tabler-calculator"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path
                            d="M4 3m0 2a2 2 0 0 1 2 -2h12a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-12a2 2 0 0 1 -2 -2z"
                        />
                        <path
                            d="M8 7m0 1a1 1 0 0 1 1 -1h6a1 1 0 0 1 1 1v1a1 1 0 0 1 -1 1h-6a1 1 0 0 1 -1 -1z"
                        />
                        <path d="M8 14l0 .01" />
                        <path d="M12 14l0 .01" />
                        <path d="M16 14l0 .01" />
                        <path d="M8 17l0 .01" />
                        <path d="M12 17l0 .01" />
                        <path d="M16 17l0 .01" />
                    </svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Cajas</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <template v-if="open_cash">
                    <!--
                    <button
                        type="button"
                        class="btn btn-custom btn-sm  mt-2 mr-2"
                        @click.prevent="clickDownloadGeneral()"
                    >
                        <i class="fas fa-shopping-cart"></i> Reporte general
                    </button>
                    -->
                    <button
                        type="button"
                        class="btn btn-custom btn-sm  mt-2 mr-2"
                        @click.prevent="clickCreate()"
                    >
                        <i class="fas fa-shopping-cart"></i> Aperturar caja chica
                    </button>
                </template>
                <!-- <template v-else>                 -->
                <!-- <button type="button" class="btn btn-success btn-sm  mt-2 mr-2" @click.prevent="clickOpenPos()"><i class="fas fa-shopping-cart" ></i> Aperturar punto de venta</button> -->
                <!-- </template> -->
            </div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <!-- <div class="card-header bg-info">
                <h3 class="my-0">Listado de cajas</h3>
            </div> -->
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <!-- <th>#</th> -->
                        <th>Referencia</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th class="text-left">Apertura</th>
                        <th class="text-left">Cierre</th>
                        <th class="text-right">Saldo inicial</th>
                        <th class="text-right">Saldo actual</th>
                        <th class="text-right">Saldo final</th>
                        <!-- <th>Ingreso</th> -->
                        <!-- <th>Egreso</th> -->
                        <th>Estado</th>
                        <th class="text-right">Acciones</th>
                    </tr>


                    <tr></tr>
                    <tr slot-scope="{ index, row }">
                        <!-- <td>{{ index }}</td> -->
                        <td>{{ row.reference_number }}</td>
                        <td>{{ row.user }}</td>
                        <td>{{ row.type }}</td>
                        <td class="text-left">{{ formatDate(row.opening) }}</td>
                        <td class="text-left">{{ formatDate(row.closed) }}</td>
                        <td class="text-right">{{ row.beginning_balance }}</td>
                        <td class="text-right">{{ row.saldo_actual }}</td>
                        <td class="text-right">{{ row.final_balance }}</td>
                        <!-- <td>{{ row.income }}</td>
                        <td>{{ row.expense }}</td> -->
                        <td>{{ row.state_description }}</td>
                        <td class="text-right">
                            <template v-if="availableReportForSeller">
                            <!-- <button type="button" class="btn waves-effect waves-light btn-xs btn-primary" @click.prevent="clickDownload(row.id)">Reporte</button> -->

                            <!--
                            <div class="btn-group flex-wrap">
                                <button
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-primary dropdown-toggle"
                                    data-toggle="dropdown"
                                    aria-expanded="false"
                                >
                                    Reporte <span class="caret"></span>
                                </button>
                                <div
                                    class="dropdown-menu"
                                    role="menu"
                                    x-placement="bottom-start"
                                    style="position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(0px, 42px, 0px);"
                                >
                                    <a
                                        class="dropdown-item text-1"
                                        href="#"
                                        @click.prevent="
                                            clickDownloadReport(row.id, 'a4')
                                        "
                                        >PDF A4</a
                                    >
                                    <a
                                        class="dropdown-item text-1"
                                        href="#"
                                        @click.prevent="
                                            clickDownloadReport(
                                                row.id,
                                                'ticket'
                                            )
                                        "
                                        >PDF Ticket</a
                                    >
                                    <a
                                        class="dropdown-item text-1"
                                        href="#"
                                        @click.prevent="
                                            clickDownloadReport(
                                                row.id,
                                                'ticket',
                                                '58'
                                            )
                                        "
                                        >PDF Ticket 58</a
                                    >
                                    <a
                                        class="dropdown-item text-1"
                                        href="#"
                                        @click.prevent="
                                            clickDownloadReport(
                                                row.id,
                                                'ticket',
                                                '80',
                                                1
                                            )
                                        "
                                        >PDF Ticket Resumen</a
                                    >
                                    <a
                                        class="dropdown-item text-1"
                                        href="#"
                                        @click.prevent="
                                            clickDownloadReport(
                                                row.id,
                                                'simple_a4'
                                            )
                                        "
                                        >Simple A4</a
                                    >
                                    <a
                                        class="dropdown-item text-1"
                                        href="#"
                                        @click.prevent="
                                            clickDownloadReport(row.id, 'excel')
                                        "
                                        >Excel</a
                                    >
                                    <a class="dropdown-item text-1" href="#" @click.prevent="clickDownloadProducts(row.id, 'excel')">Excel</a>

                                    <a
                                        class="dropdown-item text-1"
                                        href="#"
                                        @click.prevent="clickReportSummaryDailyOperations(row.id)"
                                        >Resumen de Operaciones Diarias</a
                                    >

                                    <el-tooltip
                                        class="item"
                                        content="Reporte general de caja asociado a los pagos al contado con destino caja"
                                        effect="dark"
                                        placement="right-end"
                                    >
                                        <a
                                            class="dropdown-item text-1"
                                            href="#"
                                            @click.prevent="
                                                clickReportCashWithPayments(
                                                    row.id
                                                )
                                            "
                                            >Reporte general caja V2</a
                                        >
                                    </el-tooltip>
                                </div>
                            </div>
                            -->

                            <!-- <button type="button" class="btn waves-effect waves-light btn-xs btn-primary" @click.prevent="clickDownloadReport(row.id, 'a4')">Reporte</button> -->

                            <div class="btn-group flex-wrap">
                                <button
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-primary dropdown-toggle"
                                    data-toggle="dropdown"
                                    aria-expanded="false"
                                >
                                    Reportes <span class="caret"></span>
                                </button>
                                <div
                                    class="dropdown-menu"
                                    role="menu"
                                    x-placement="bottom-start"
                                    style="position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(0px, 42px, 0px);"
                                >
                                    <!-- <a class="dropdown-item text-1" href="#" @click.prevent="clickDownloadProducts(row.id, 'pdf')">PDF</a> -->
                                    <!-- <a class="dropdown-item text-1" href="#" @click.prevent="clickDownloadReportCash(row.id, 'excel')">Excel</a> -->

                                    <el-tooltip
                                        class="item"
                                        content="Ingresos en efectivo con destino caja"
                                        effect="dark"
                                        placement="right-end"
                                    >
                                        <a
                                            class="dropdown-item text-1"
                                            href="#"
                                            @click.prevent="
                                                clickCashPaymentReportExcel(
                                                    row.id
                                                )
                                            "
                                            >Excel</a
                                        >
                                    </el-tooltip>

                                    <a
                                        class="dropdown-item text-1"
                                        href="#"
                                        @click.prevent="
                                            clickDownloadReportIncomeEgress(
                                                row.id
                                            )
                                        "
                                        >Ingresos y egresos</a
                                    >

                                  <el-tooltip
                                    class="item"
                                    content="Resumen de ingresos a Caja Chica"
                                    effect="dark"
                                    placement="right-end"
                                  >
                                    <a
                                      class="dropdown-item text-1"
                                      href="#"
                                      @click.prevent="clickDownloadIncomeSummary(row.id)"
                                    >Resumen de Ingresos</a
                                    >
                                  </el-tooltip>

                                    <!--
                                    <el-tooltip
                                        class="item"
                                        content="Ingresos en efectivo con destino caja - Disponible para facturas, boletas y notas de venta"
                                        effect="dark"
                                        placement="right-end"
                                    >
                                        <a
                                            class="dropdown-item text-1"
                                            href="#"
                                            @click.prevent="
                                                clickReportPaymentsAssociatedCash(
                                                    row.id
                                                )
                                            "
                                            >Pagos asociados a caja</a
                                        >
                                    </el-tooltip>
                                    -->

                                  <el-tooltip
                                    class="item"
                                    content="Reporte general de caja asociado a los pagos al contado con destino caja"
                                    effect="dark"
                                    placement="right-end"
                                  >
                                    <a
                                      class="dropdown-item text-1"
                                      href="#"
                                      @click.prevent="
                                                clickReportCashWithPayments(
                                                    row.id
                                                )
                                            "
                                    >Reporte general caja V2</a
                                    >
                                  </el-tooltip>
                                </div>
                            </div>

                              <!---
                              <div class="btn-group flex-wrap">
                                <button
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-primary dropdown-toggle"
                                    data-toggle="dropdown"
                                    aria-expanded="false"
                                >
                                    Reporte Productos
                                    <span class="caret"></span>
                                </button>
                                <div
                                    class="dropdown-menu"
                                    role="menu"
                                    x-placement="bottom-start"
                                    style="position: absolute; will-change: transform; top: 0px; left: 0px; transform: translate3d(0px, 42px, 0px);"
                                >
                                    <a
                                        class="dropdown-item text-1"
                                        href="#"
                                        @click.prevent="
                                            clickDownloadProducts(row.id, 'pdf')
                                        "
                                        >Punto de venta - PDF</a
                                    >
                                    <a
                                        class="dropdown-item text-1"
                                        href="#"
                                        @click.prevent="
                                            clickDownloadProducts(
                                                row.id,
                                                'excel'
                                            )
                                        "
                                        >Punto de venta - Excel</a
                                    >
                                    <a
                                        class="dropdown-item text-1"
                                        href="#"
                                        @click.prevent="
                                            clickDownloadProducts(
                                                row.id,
                                                'pdf',
                                                true
                                            )
                                        "
                                        >Venta rápida - PDF</a
                                    >
                                </div>
                              </div>
                            -->


                            </template>

                            <template v-if="row.state">
                                <button
                                  v-if="row.user_id === userId"
                                  type="button"
                                  class="btn waves-effect waves-light btn-xs btn-success"
                                  @click.prevent="clickCloseEgressCash(row.id)"
                                >
                                  Registrar Egreso
                                </button>
                                <button
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-warning"
                                    @click.prevent="clickCloseCash(row.id)"
                                >
                                    Cerrar caja
                                </button>
                                <button
                                    v-if="typeUser === 'admin'"
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-info"
                                    @click.prevent="clickCreate(row.id)"
                                >
                                    Editar
                                </button>
                                <button
                                    v-if="typeUser === 'admin'"
                                    type="button"
                                    class="btn waves-effect waves-light btn-xs btn-danger"
                                    @click.prevent="clickDelete(row.id)"
                                >
                                    Eliminar
                                </button>
                            </template>

                            <!-- <button
                                type="button"
                                class="btn waves-effect waves-light btn-xs btn-info"
                                @click.prevent="clickOptions(row.id)"
                            >
                                C. Electrónico
                            </button> -->
                        </td>
                    </tr>
                </data-table>
            </div>
        </div>
        <cash-form
            :showDialog.sync="showDialog"
            :typeUser="typeUser"
            :recordId="recordId"
        ></cash-form>

        <cash-options
            :showDialog.sync="showDialogOptions"
            :recordId="recordId"
        ></cash-options>

        <cash-form-egress
          :showDialog.sync="showDialogEgress"
          :typeUser="typeUser"
          :recordId="recordId"
          @reload="onReloadData"
        ></cash-form-egress>
    </div>
</template>
<style>
@media only screen and (max-width: 485px) {
    .filter-container {
        margin-top: 0px;
        & .btn-filter-content,
        .btn-container-mobile {
            display: flex;
            align-items: center;
            justify-content: start;
        }
    }
}
</style>
<script>
import DataTable from "../../../components/DataTable.vue";
import { deletable } from "../../../mixins/deletable";
import CashForm from "./form.vue";
import CashOptions from "./partials/options.vue";
import CashFormEgress from "./formEgress.vue";

export default {
    mixins: [deletable],
    components: { DataTable, CashForm, CashOptions, CashFormEgress },
    props: ["typeUser", "configuration", "userId"],
    data() {
        return {
            showDialog: false,
            showDialogOptions: false,
            showDialogEgress: false,
            open_cash: true,
            resource: "cash",
            recordId: null,
            cash: null
        };
    },
    async created() {
        /*await this.$http.get(`/${this.resource}/opening_cash`)
                .then(response => {
                    this.cash = response.data.cash
                    this.open_cash = (this.cash) ? false : true
                })*/
        /*this.$eventHub.$on('openCash', () => {
                this.open_cash = false
            })*/

        // Verificar si se redirigió por falta de caja
        this.checkRedirectReason();
    },
    computed:{
        availableReportForSeller() {
            return this.typeUser === 'admin' ? true : (this.typeUser === 'finance');
        }
    },
    methods: {
        onReloadData() {
          this.$eventHub.$emit('reloadData');
        },
        checkRedirectReason() {
            const urlParams = new URLSearchParams(window.location.search);
            const redirectReason = urlParams.get('redirect_reason');

            if (redirectReason === 'no_cash_sale_note') {
                this.$message({
                    message: 'Debe aperturar una caja antes de crear una nota de venta.',
                    type: 'warning',
                    duration: 5000
                });
                this.clearUrlParameters();
            } else if (redirectReason === 'no_cash_document') {
                this.$message({
                    message: 'Debe aperturar una caja antes de crear un nuevo comprobante.',
                    type: 'warning',
                    duration: 5000
                });
                this.clearUrlParameters();
            } else if (redirectReason === 'no_cash_pos') {
                this.$message({
                    message: 'Debe aperturar una caja antes de acceder al punto de venta.',
                    type: 'warning',
                    duration: 5000
                });
                this.clearUrlParameters();
            } else if (redirectReason === 'no_cash_fast_sale') {
                this.$message({
                    message: 'Debe aperturar una caja antes de acceder a la venta rápida.',
                    type: 'warning',
                    duration: 5000
                });
                this.clearUrlParameters();
            } else if (redirectReason === 'no_cash_garage') {
                this.$message({
                    message: 'Debe aperturar una caja antes de acceder a la venta rápida.',
                    type: 'warning',
                    duration: 5000
                });
                this.clearUrlParameters();
            }
        },
        clearUrlParameters() {
            const url = new URL(window.location);
            url.searchParams.delete('redirect_reason');
            window.history.replaceState({}, document.title, url.pathname);
        },
        formatDate(date) {
            if (!date) return null;
            const parsedDate = moment(date, "YYYY-MM-DD HH:mm:ss");
            return parsedDate.isValid()
                ? parsedDate.format("DD-MM-YYYY h:mmA")
                : null;
        },
        clickOptions(recordId) {
            this.showDialogOptions = true;
            this.recordId = recordId;
        },
        clickDownloadReport(id, template, mm = 80, summary = 0) {
            if (template == "ticket") {
                window.open(
                    `/${
                        this.resource
                    }/report-${template}/${id}/${mm}/${summary}`,
                    "_blank"
                );
            } else if (template == "simple_a4") {
                window.open(
                    `/${this.resource}/simple/report-a4/${id}/`,
                    "_blank"
                );
            } else {
                window.open(
                    `/${this.resource}/report-${template}/${id}`,
                    "_blank"
                );
            }
        },
        clickDownload(id) {
            window.open(`/${this.resource}/report/${id}`, "_blank");
        },
        clickDownloadIncomeSummary(id) {
            window.open(
                `/${this.resource}/report/income-summary/${id}`,
                "_blank"
            );
        },
        clickCreate(recordId = null) {
            this.recordId = recordId;
            this.showDialog = true;
        },
        clickCloseEgressCash(recordId) {
          this.recordId = recordId;
          this.showDialogEgress = true;
        },
        clickCloseCash(recordId) {
            this.recordId = recordId;
            const h = this.$createElement;
            this.$msgbox({
                title: "Cerrar caja chica",
                type: "warning",
                message: h("p", null, [
                    h(
                        "p",
                        { style: "text-align: justify; font-size:15px" },
                        "¿Está seguro de cerrar la caja?"
                    )
                ]),

                showCancelButton: true,
                confirmButtonText: "Cerrar",
                cancelButtonText: "Cancelar",
                beforeClose: (action, instance, done) => {
                    if (action === "confirm") {
                        this.createRegister(instance, done);
                    } else {
                        done();
                    }
                }
            })
                .then(action => {})
                .catch(action => {});
        },
        createRegister(instance, done) {
            instance.confirmButtonLoading = true;
            instance.confirmButtonText = "Cerrando caja...";

            this.$http
                .get(`/${this.resource}/close/${this.recordId}`)
                .then(response => {
                    if (response.data.success) {
                        this.$eventHub.$emit("reloadData");
                        this.open_cash = true;
                        this.$message.success(response.data.message);
                    } else {
                        console.log(response);
                        this.$message.success(response.data.message);
                    }
                })
                .catch(error => {
                    console.log(error);
                })
                .then(() => {
                    instance.confirmButtonLoading = false;
                    instance.confirmButtonText = "Iniciar prueba";
                    done();
                });
        },
        clickOpenPos() {
            window.open("/pos");
        },
        clickDelete(id) {
            this.destroy(`/${this.resource}/${id}`).then(() =>
                this.$eventHub.$emit("reloadData")
            );
        },
        clickDownloadGeneral() {
            window.open(`/${this.resource}/report`, "_blank");
        },
        clickDownloadProducts(id, type, is_garage = false) {
            if (type == "excel") {
                window.open(
                    `/${this.resource}/report/products-excel/${id}`,
                    "_blank"
                );
                return;
            }

            window.open(
                `/${this.resource}/report/products/${id}/${is_garage}`,
                "_blank"
            );
            // window.open(`/${this.resource}/report/products/${id}`, '_blank');
        },
        clickDownloadReportCash(id, type) {
            if (type == "excel") {
                window.open(
                    `/${this.resource}/report/cash-excel/${id}`,
                    "_blank"
                );
                return;
            }

            window.open(`/${this.resource}/report/products/${id}`, "_blank");
        },
        clickDownloadReportIncomeEgress(id) {
            window.open(
                `/${this.resource}/report-cash-income-egress/${id}`,
                "_blank"
            );
        },
        clickReportSummaryDailyOperations(id) {
            window.open(
                `/cash-reports/summary-daily-operations/${id}`,
                "_blank"
            );
        },
        clickReportPaymentsAssociatedCash(id) {
            window.open(
                `/cash-reports/payments-associated-cash/${id}`,
                "_blank"
            );
        },
        clickReportCashWithPayments(id) {
            window.open(`/cash-reports/general-with-payments/${id}`, "_blank");
        },
        clickCashPaymentReportExcel(id) {
            window.open(
                `/cash-reports/cash-payment-report-excel/${id}`,
                "_blank"
            );
        }
    }
};
</script>
