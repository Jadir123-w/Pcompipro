<template>
    <div class="quotations">
        <div class="page-header pr-0">
            <h2>
                <a href="/quotations"
                    ><svg
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
                        class="icon icon-tabler icons-tabler-outline icon-tabler-edit"
                    >
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path
                            d="M7 7h-1a2 2 0 0 0 -2 2v9a2 2 0 0 0 2 2h9a2 2 0 0 0 2 -2v-1"
                        />
                        <path
                            d="M20.385 6.585a2.1 2.1 0 0 0 -2.97 -2.97l-8.415 8.385v3h3l8.385 -8.415z"
                        />
                        <path d="M16 5l3 3" /></svg
                ></a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Cotizaciones</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <a
                    :href="`/${resource}/create`"
                    class="btn btn-custom btn-sm  mt-2 mr-2"
                    ><i class="fa fa-plus-circle"></i> Nuevo</a
                >
            </div>
        </div>
        <div class="card tab-content-default row-new mb-0">
            <div class="data-table-visible-columns">
                <el-dropdown :hide-on-click="false">
                    <el-button type="secondary">
                        Mostrar/Ocultar columnas<i
                            class="el-icon-arrow-down el-icon--right"
                        ></i>
                    </el-button>
                    <el-dropdown-menu slot="dropdown">
                        <el-dropdown-item
                            v-for="(column, index) in columns"
                            :key="index"
                        >
                            <el-checkbox
                                v-model="column.visible"
                                @change="saveColumnVisibilityQuotations"
                                >{{ column.title }}</el-checkbox
                            >
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </el-dropdown>
            </div>
            <div class="card-body">
                <data-table :resource="resource" :state-types="state_types">
                    <tr slot="heading">
                        <!-- <th>#</th> -->
                        <th class="text-left">Fecha Emisión</th>
                        <th
                            class="text-center"
                            v-if="columns.delivery_date.visible"
                        >
                            T. Entrega
                        </th>
                        <th>Registrado por</th>
                        <th>Vendedor</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>Cotización</th>
                        <th>Comprobantes</th>
                        <th>Notas de venta</th>
                        <th v-if="columns.order_note.visible">Pedido</th>
                        <th>Oportunidad Venta</th>
                        <th v-if="columns.referential_information.visible">
                            Inf.Referencial
                        </th>
                        <th v-if="columns.contract.visible">Contrato</th>
                        <!-- <th>Estado</th> -->
                        <th v-if="columns.exchange_rate_sale.visible">T.C.</th>
                        <th class="text-center">Moneda</th>
                        <th class="text-center"></th>
                        <th
                            class="text-right"
                            v-if="columns.total_exportation.visible"
                        >
                            T.Exportación
                        </th>
                        <th
                            class="text-right"
                            v-if="columns.total_free.visible"
                        >
                            T.Gratuito
                        </th>
                        <th
                            class="text-right"
                            v-if="columns.total_unaffected.visible"
                        >
                            T.Inafecta
                        </th>
                        <th
                            class="text-right"
                            v-if="columns.total_exonerated.visible"
                        >
                            T.Exonerado
                        </th>
                        <th class="text-right">T.Gravado</th>
                        <th class="text-right">T.Igv</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">PDF</th>
                        <th class="text-right"></th>
                        <!-- <th class="text-right">Acciones</th> -->
                    </tr>
                    <tr
                        slot-scope="{ index, row }"
                        :class="{ anulate_color: row.state_type_id == '11' }"
                    >
                        <!-- <td>{{ index }}</td> -->
                        <td class="text-left">
                            {{ formatDate(row.date_of_issue) }}
                        </td>
                        <td
                            class="text-center"
                            v-if="columns.delivery_date.visible"
                        >
                            {{ row.delivery_date }}
                        </td>
                        <td>{{ row.user_name }}</td>
                        <td>{{ row.seller_name }}</td>
                        <td>
                            {{ row.customer_name }}<br /><small
                                v-text="row.customer_number"
                            ></small>
                        </td>
                        <td>
                            <template v-if="row.state_type_id == '11'">
                                {{ row.state_type_description }}
                            </template>
                            <template v-else>
                                <el-select
                                    v-model="row.state_type_id"
                                    @change="changeStateType(row)"
                                    style="width:120px !important"
                                >
                                    <el-option
                                        v-for="option in state_types"
                                        :key="option.id"
                                        :value="option.id"
                                        :label="option.description"
                                    ></el-option>
                                </el-select>
                            </template>
                        </td>
                        <td>{{ row.identifier }}</td>
                        <td>
                            <template v-for="(document, i) in row.documents">
                                <template v-if="document.is_voided_or_rejected">
                                    <label :key="i" class="d-block text-danger">
                                        {{ document.number_full }}
                                        <!-- {{ document.number_full }} ({{document.state_type_description}}) -->
                                    </label>
                                </template>
                                <template v-else>
                                    <label
                                        :key="i"
                                        v-text="document.number_full"
                                        class="d-block"
                                    ></label>
                                </template>
                            </template>
                        </td>
                        <td>
                            <template v-for="(sale_note, i) in row.sale_notes">
                                <!-- <label :key="i" v-text="sale_note.identifier" class="d-block"></label> -->
                                <label
                                    :key="i"
                                    v-text="sale_note.number_full"
                                    class="d-block"
                                ></label>
                            </template>
                        </td>
                        <td v-if="columns.order_note.visible">
                            <!-- Pedidos -->
                            <template
                                v-if="
                                    row.order_note !== undefined &&
                                        row.order_note.full_number !== undefined
                                "
                            >
                                <label class="d-block"
                                    >{{ row.order_note.full_number }}
                                </label>
                            </template>
                        </td>
                        <td>
                            <!-- {{ row.sale_opportunity_number_full }} -->

                            <el-popover
                                placement="right"
                                v-if="row.sale_opportunity"
                                width="400"
                                trigger="click"
                            >
                                <div class="col-md-12 mt-4">
                                    <table>
                                        <tr>
                                            <td><strong>O. Venta: </strong></td>
                                            <td>
                                                <strong>{{
                                                    row.sale_opportunity_number_full
                                                }}</strong>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Detalle: </strong></td>
                                            <td>
                                                <strong>{{
                                                    row.sale_opportunity.detail
                                                }}</strong>
                                            </td>
                                        </tr>
                                        <tr class="mt-4 mb-4">
                                            <td>
                                                <strong>F. Emisión:</strong>
                                            </td>
                                            <td>
                                                <strong>{{
                                                    row.date_of_issue
                                                }}</strong>
                                            </td>
                                        </tr>
                                    </table>

                                    <div class="table-responsive mt-4">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Descripción</th>
                                                    <th>Cantidad</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(row, index) in row
                                                        .sale_opportunity.items"
                                                    :key="index"
                                                >
                                                    <td>{{ index + 1 }}</td>
                                                    <td>
                                                        {{
                                                            row.item.description
                                                        }}
                                                    </td>
                                                    <td>{{ row.quantity }}</td>
                                                    <td>{{ row.total }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <el-button slot="reference"
                                    ><i class="fa fa-eye"></i
                                ></el-button>
                            </el-popover>
                        </td>
                        <!-- <td>{{ row.state_type_description }}</td> -->
                        <td v-if="columns.referential_information.visible">
                            {{ row.referential_information }}
                        </td>
                        <td v-if="columns.contract.visible">
                            {{ row.contract_number_full }}
                        </td>
                        <td v-if="columns.exchange_rate_sale.visible">
                            {{ row.exchange_rate_sale }}
                        </td>
                        <td class="text-center">{{ row.currency_type_id }}</td>

                        <td class="text-right">
                            <button
                                type="button"
                                class="btn waves-effect waves-light btn-xs btn-info"
                                @click.prevent="clickPayment(row.id)"
                            >
                                Pagos
                            </button>
                        </td>

                        <td
                            class="text-right"
                            v-if="columns.total_exportation.visible"
                        >
                            {{row.currency_type_id === 'PEN' ? 'S/' : '$'}}
                            {{ row.total_exportation }}
                        </td>
                        <td
                            class="text-right"
                            v-if="columns.total_free.visible"
                        >
                            {{row.currency_type_id === 'PEN' ? 'S/' : '$'}}
                            {{ row.total_free }}
                        </td>
                        <td
                            class="text-right"
                            v-if="columns.total_unaffected.visible"
                        >
                            {{row.currency_type_id === 'PEN' ? 'S/' : '$'}}
                            {{ row.total_unaffected }}
                        </td>
                        <td
                            class="text-right"
                            v-if="columns.total_exonerated.visible"
                        >
                            {{row.currency_type_id === 'PEN' ? 'S/' : '$'}}
                            {{ row.total_exonerated }}
                        </td>
                        <td class="text-right">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total_taxed }}</td>
                        <td class="text-right">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total_igv }}</td>
                        <td class="text-right">{{row.currency_type_id === 'PEN' ? 'S/' : '$'}} {{ row.total }}</td>
                        <td class="text-right">
                            <button
                                type="button"
                                class="btn waves-effect waves-light btn-xs btn-info"
                                @click.prevent="clickOptionsPdf(row.id)"
                            >
                                PDF
                            </button>
                        </td>

                        <td class="text-right">
                          <el-dropdown trigger="click" placement="bottom-end">
                            <button
                              class="btn btn-default btn-sm btn-dropdown-toggle"
                              type="button"
                              id="dropdownMenuButton"
                              aria-haspopup="true"
                              aria-expanded="false"
                            >
                              <i class="fas fa-ellipsis-v"></i>
                              <i class="fas fa-ellipsis-h" style="display: none;"></i>
                            </button>
                        
                            <el-dropdown-menu slot="dropdown">
                              <el-dropdown-item
                                v-if="row.btn_options"
                                @click.native.prevent="clickGenerateDocument(row.id)"
                              >
                                Generar comprobante
                              </el-dropdown-item>
                          
                              <el-dropdown-item
                                v-if="row.btn_options"
                                @click.native.prevent="clickOptions(row.id)"
                              >
                                Generar nota de venta
                              </el-dropdown-item>
                          
                              <el-dropdown-item
                                v-if="row.documents.length == 0 && row.state_type_id != '11'"
                                @click.native.prevent="go(`/${resource}/edit/${row.id}`)"
                              >
                                Editar
                              </el-dropdown-item>
                        
                              <el-dropdown-item
                                v-if="row.documents.length == 0 && row.state_type_id != '11'"
                                @click.native.prevent="clickAnulate(row.id)"
                              >
                                Anular
                              </el-dropdown-item>
                        
                              <el-dropdown-item
                                @click.native.prevent="duplicate(row.id)"
                              >
                                Duplicar
                              </el-dropdown-item>
                        
                              <a :href="`/dispatches/create_new/quotation/${row.id}`">
                                <el-dropdown-item
                                >
                                    Guía
                                </el-dropdown-item>
                              </a>
                              <template v-if="row.btn_generate_cnt && row.state_type_id != '11'">
                                <el-dropdown-item
                                  @click.native.prevent="go(`/contracts/generate-quotation/${row.id}`)"
                                >
                                  Generar contrato
                                </el-dropdown-item>
                              </template>
                              <template v-else>
                                <el-dropdown-item
                                  @click.native.prevent="clickPrintContract(row.external_id_contract)"
                                >
                                  Ver contrato
                                </el-dropdown-item>
                              </template>
                        
                              <!-- pedidos -->
                              <el-dropdown-item
                                v-if="canMakeOrderNote(row)"
                                @click.native.prevent="makeOrder(row.id)"
                              >
                                Generar Pedido
                              </el-dropdown-item>
                        
                              <el-dropdown-item
                                @click.native.prevent="clickSendQuotation(row.id)"
                              >
                                Enviar cotización
                              </el-dropdown-item>
                            </el-dropdown-menu>
                          </el-dropdown>
                        </td>
                    </tr>
                </data-table>
            </div>

            <quotation-options
                :showDialog.sync="showDialogOptions"
                :recordId="recordId"
                :showGenerate="true"
                :showClose="true"
            ></quotation-options>

            <quotation-options-pdf
                :showDialog.sync="showDialogOptionsPdf"
                :recordId="recordId"
                :showClose="true"
            ></quotation-options-pdf>

            <quotation-payments
                :showDialog.sync="showDialogPayments"
                :recordId="recordId"
            ></quotation-payments>

            <send-email-document
                :config="config"
                :showDialog.sync="showDialogSendEmailDocument"
                :recordId="recordId"
                :resource="resource"
            ></send-email-document>
        </div>
    </div>
</template>
<style scoped>
.anulate_color {
    color: red;
}
</style>
<script>
import QuotationOptions from "./partials/options.vue";
import QuotationOptionsPdf from "./partials/options_pdf.vue";
import DataTable from "../../../components/DataTableQuotation.vue";
import { deletable } from "../../../mixins/deletable";
import QuotationPayments from "./partials/payments.vue";
import { mapActions, mapState } from "vuex";
import SendEmailDocument from "@components/secondary/SendEmailDocument.vue";

export default {
    props: ["typeUser", "soapCompany", "generateOrderNoteFromQuotation"],
    mixins: [deletable],
    components: {
        DataTable,
        QuotationOptions,
        QuotationOptionsPdf,
        QuotationPayments,
        SendEmailDocument
    },
    computed: {
        ...mapState(["config"])
    },
    data() {
        return {
            resource: "quotations",
            showDialogSendEmailDocument: false,
            recordId: null,
            showDialogPayments: false,
            showDialogOptions: false,
            showDialogOptionsPdf: false,
            state_types: [],
            columns: {
                total_exportation: {
                    title: "T.Exportación",
                    visible: false
                },
                total_unaffected: {
                    title: "T.Inafecto",
                    visible: false
                },
                total_exonerated: {
                    title: "T.Exonerado",
                    visible: false
                },
                total_free: {
                    title: "T.Gratuito",
                    visible: false
                },
                contract: {
                    title: "Contrato",
                    visible: false
                },
                delivery_date: {
                    title: "T.Entrega",
                    visible: false
                },
                referential_information: {
                    title: "Inf.Referencial",
                    visible: false
                },
                order_note: {
                    title: "Pedidos",
                    visible: false
                },
                exchange_rate_sale: {
                    title: "Tipo de cambio",
                    visible: false
                }
            }
        };
    },
    async created() {
        this.loadColumnVisibilityQuotations();
        await this.filter();
    },
    mounted() {
        this.loadConfiguration();
    },
    methods: {
        formatDate(date) {
            if (!date) return null;
            return moment(date).format("DD-MM-YYYY");
        },
        saveColumnVisibilityQuotations() {
            localStorage.setItem(
                "columnVisibilityQuotations",
                JSON.stringify(this.columns)
            );
        },
        loadColumnVisibilityQuotations() {
            const savedColumns = localStorage.getItem(
                "columnVisibilityQuotations"
            );
            if (savedColumns) {
                this.columns = JSON.parse(savedColumns);
            }
        },
        clickSendQuotation(id) {
            this.recordId = id;
            this.showDialogSendEmailDocument = true;
        },
        ...mapActions(["loadConfiguration"]),
        canMakeOrderNote(row) {
            let permission = true;

            // Si ya tiene Pedidos, no se genera uno nuevo
            if (row.order_note.full_number) {
                permission = false;
            } else {
                if (this.typeUser !== "admin") {
                    permission = this.generateOrderNoteFromQuotation;
                }
            }

            return permission;
        },
        clickPrintContract(external_id) {
            window.open(`/contracts/print/${external_id}/a4`, "_blank");
        },
        clickPayment(recordId) {
            this.recordId = recordId;
            this.showDialogPayments = true;
        },
        async changeStateType(row) {
            await this.updateStateType(
                `/${this.resource}/state-type/${row.state_type_id}/${row.id}`
            ).then(() => this.$eventHub.$emit("reloadData"));
        },
        async filter() {
            await this.$http.get(`/${this.resource}/filter`).then(response => {
                this.state_types = response.data.state_types;
            });
        },
        clickEdit(id) {
            this.recordId = id;
            this.showDialogFormEdit = true;
        },
        clickOptions(recordId = null) {
            this.recordId = recordId;
            this.showDialogOptions = true;
        },
        clickOptionsPdf(recordId = null) {
            this.recordId = recordId;
            this.showDialogOptionsPdf = true;
        },
        clickAnulate(id) {
            this.anular(`/${this.resource}/anular/${id}`).then(() =>
                this.$eventHub.$emit("reloadData")
            );
        },
        makeOrder(quotation) {
            let tos = parseInt(quotation);
            localStorage.setItem("Quotation", tos);
            localStorage.setItem("FromQuotation", true);
            window.location.href = "/order-notes/create";
        },
        duplicate(id) {
            this.$http
                .post(`${this.resource}/duplicate`, { id })
                .then(response => {
                    if (response.data.success) {
                        this.$message.success(
                            "Se guardaron los cambios correctamente."
                        );
                        this.$eventHub.$emit("reloadData");
                    } else {
                        this.$message.error("No se guardaron los cambios");
                    }
                })
                .catch(error => {});
            this.$eventHub.$emit("reloadData");
        },
        clickGenerateDocument(recordId) {
            window.location.href = `/documents/create/quotations/${recordId}`;
        },
        go(url) {
          window.location.href = url;
        }
    }
};
</script>
