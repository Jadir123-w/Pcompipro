<template>
    <div>
        <div class="btn-filter-content">
            <el-button
                type="secondary"
                class="btn-show-filter"
                :class="{ shift: isVisible }"
                @click="toggleInformation"
            >
                {{ isVisible ? "Ocultar filtros" : "Mostrar filtros" }}
            </el-button>
        </div>
        <div class="row">
            <div class="col-md-12 col-lg-12 col-xl-12 " v-if="isVisible">
                <div class="row mt-2 m-0 p-0">
                    <div class="col-md-3 form-modern">
                        <label class="control-label">
                            Periodo
                            <el-tooltip
                                class="item"
                                content="Filtra por fecha de pago"
                                effect="dark"
                                placement="top-start">
                                <i class="fa fa-info-circle"></i>
                            </el-tooltip>
                        </label>
                        <el-select v-model="form.period"
                                   @change="changePeriod">
                            <el-option key="month"
                                       label="Por mes"
                                       value="month"></el-option>
                            <el-option key="between_months"
                                       label="Entre meses"
                                       value="between_months"></el-option>
                            <el-option key="date"
                                       label="Por fecha"
                                       value="date"></el-option>
                            <el-option key="between_dates"
                                       label="Entre fechas"
                                       value="between_dates"></el-option>
                        </el-select>
                    </div>
                    <template v-if="form.period === 'month' || form.period === 'between_months'">
                        <div class="col-md-3 form-modern">
                            <label class="control-label">Mes de</label>
                            <el-date-picker
                                v-model="form.month_start"
                                :clearable="false"
                                format="MM/yyyy"
                                type="month"
                                value-format="yyyy-MM"
                                @change="changeDisabledMonths"></el-date-picker>
                        </div>
                    </template>
                    <template v-if="form.period === 'between_months'">
                        <div class="col-md-3">
                            <label class="control-label">Mes al</label>
                            <el-date-picker
                                v-model="form.month_end"
                                :clearable="false"
                                :picker-options="pickerOptionsMonths"
                                format="MM/yyyy"
                                type="month"
                                value-format="yyyy-MM"></el-date-picker>
                        </div>
                    </template>
                    <template v-if="form.period === 'date' || form.period === 'between_dates'">
                        <div class="col-md-3">
                            <label class="control-label">Fecha del</label>
                            <el-date-picker
                                v-model="form.date_start"
                                :clearable="false"
                                format="dd/MM/yyyy"
                                type="date"
                                value-format="yyyy-MM-dd"
                                @change="changeDisabledDates"></el-date-picker>
                        </div>
                    </template>
                    <template v-if="form.period === 'between_dates'">
                        <div class="col-md-3">
                            <label class="control-label">Fecha al</label>
                            <el-date-picker
                                v-model="form.date_end"
                                :clearable="false"
                                :picker-options="pickerOptionsDates"
                                format="dd/MM/yyyy"
                                type="date"
                                value-format="yyyy-MM-dd"></el-date-picker>
                        </div>
                    </template>
                    <div class="form-modern col-md-2">
                        <label class="control-label">
                            Moneda
                        </label>
                        <el-select v-model="form.currency_type_id"
                                   @change="changeCurrency">
                            <el-option key="PEN"
                                       label="Soles"
                                       value="PEN"></el-option>
                            <el-option key="USD"
                                       label="Dolares"
                                       value="USD"></el-option>
                        </el-select>
                    </div>

                    <div class="col-md-2">
                        <label class="control-label" style="width: 100%;">
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        </label>

                        <el-button
                            class="submit"
                            type="info"
                            @click.prevent="ShowTransferModal()">
                            <i class="fa fa-cash-register"></i>
                            Transferir entre cuentas
                        </el-button>
                    </div>
                    <div class="col-lg-7 col-md-7 col-md-7 col-sm-12"
                         style="margin-top:29px">
                        <el-button
                            :loading="loading_submit"
                            class="submit"
                            icon="el-icon-search"
                            type="primary"
                            @click.prevent="getRecordsByFilter">Buscar
                        </el-button>
                        <template v-if="records.length>0">
                            <el-button
                                class="submit"
                                icon="el-icon-tickets"
                                type="danger"
                                @click.prevent="clickDownload('pdf')">Exportar PDF
                            </el-button>
                            <el-button
                                class="submit"
                                type="success"
                                @click.prevent="clickDownload('excel')">
                                <i class="fa fa-file-excel"></i> Exportal Excel
                            </el-button>
                        </template>
                    </div>

                </div>
                <div class="row mt-1 mb-4">
                </div>
            </div>
            <div class="col-md-12">
                <div class="scroll-shadow shadow-left" v-show="showLeftShadow"></div>
                <div class="scroll-shadow shadow-right" v-show="showRightShadow"></div>

                <div class="table-responsive" ref="scrollContainer">
                    <table class="table">
                        <thead class="">
                        <tr>
                            <th style="width: 0.1%;"><!-- # --></th>
                            <th class="text-left pl-0">Descripción</th>
                            <th v-if="resource !== 'finances/payment-method-types'"
                                class="text-right">S. Inicial
                            </th>
                            <th class="text-right">CPE</th>
                            <th class="text-right">N. Venta</th>
                            <th class="text-right">Cotización</th>
                            <th class="text-right">Contrato</th>
                            <th class="text-right">S. Técnico</th>
                            <th class="text-right">Ingresos</th>
                            <th class="text-right">Compras</th>
                            <th class="text-right">Gastos</th>
                            <th class="text-right">P. Bancarios</th>
                            <th class="text-right">Pago P. Bancarios</th>
                            <th class="text-right">Saldo</th>

                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="(row, index) in records">
                            <td class=""><!-- {{ index + 1 }} --></td>
                            <td class="text-left pl-0">{{ row.description }}</td>
                            <td v-if="resource !== 'finances/payment-method-types'"
                                class="text-right">
                                {{ row.initial_balance | DecimalText }}
                            </td>
                            <td class="text-right">{{ row.document_payment | DecimalText }}</td>
                            <td class="text-right">{{ row.sale_note_payment | DecimalText }}</td>
                            <td class="text-right">{{ row.quotation_payment | DecimalText }}</td>
                            <td class="text-right">{{ row.contract_payment | DecimalText }}</td>
                            <td class="text-right">{{ row.technical_service_payment | DecimalText }}</td>
                            <td class="text-right">{{ row.income_payment | DecimalText }}</td>
                            <td class="text-right">{{ row.purchase_payment | DecimalText }}</td>
                            <td class="text-right">{{ row.expense_payment | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ row.bank_loan | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ row.bank_loan_payment | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ row.balance | DecimalText }}</td>
                        </tr>
                        </tbody>
                        <tfoot>
                        <tr>
                            <td class="text-left pl-3"
                                colspan="2">Totales
                            </td>
                            <td v-if="resource !== 'finances/payment-method-types'"
                                class="text-right">{{curencySymbol}} {{
                                    totals.t_initial_balance | DecimalText
                                                    }}
                            </td>
                            <td class="text-right">{{curencySymbol}} {{ totals.t_documents | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ totals.t_sale_notes | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ totals.t_quotations | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ totals.t_contracts | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ totals.t_technical_services | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ totals.t_income | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ totals.t_purchases | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ totals.t_expenses | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ totals.t_bank_loan | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ totals.t_bank_loan_payment | DecimalText }}</td>
                            <td class="text-right">{{curencySymbol}} {{ totals.t_balance | DecimalText }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <tenant-finance-modal-transfer-between-accounts
            :showDialogTransfer.sync="showDialogTransfer"
            :reloadAccounts="getRecordsByFilter"
        >

        </tenant-finance-modal-transfer-between-accounts>
    </div>
</template>
<style>
.font-custom {
    font-size: 15px !important;
}
</style>
<script>
import moment from 'moment'
import queryString from 'query-string'
import {mapActions, mapState} from "vuex/dist/vuex.mjs";


export default {
    props: {
        resource: String,
        applyCustomer: {type: Boolean, required: false, default: false}
    },
    data() {
        return {
            isVisible: false,
            loading_submit: false,
            loading_search: false,
            showDialogTransfer: false,
            records: [],
            pagination: {},
            search: {},
            totals: {},
            payment_types: [],
            destination_types: [],
            form: {},
            pickerOptionsDates: {
                disabledDate: (time) => {
                    time = moment(time).format('YYYY-MM-DD')
                    return this.form.date_start > time
                }
            },
            pickerOptionsMonths: {
                disabledDate: (time) => {
                    time = moment(time).format('YYYY-MM')
                    return this.form.month_start > time
                }
            },
            sellers: [],
            showLeftShadow: false,
            showRightShadow: false,
        }
    },

    computed: {
        ...mapState([
            'config',
        ]),
        curencySymbol() {
            return this.form.currency_type_id == 'PEN' ? 'S/': '$'
        }
    },
    created() {
        this.loadConfiguration()
        this.initForm()
        this.$eventHub.$on('reloadData', () => {
            this.getRecords()
        })
    },
    async mounted() {
        await this.getRecords()

        this.$nextTick(() => {
            const el = this.$refs.scrollContainer;
            if (el) {
                el.addEventListener('scroll', this.checkScrollShadows);
                this.checkScrollShadows();
            }
        });
    },
    methods: {
        checkScrollShadows() {
            const el = this.$refs.scrollContainer;
            if (!el) return;
            
            const scrollLeft = el.scrollLeft;
            const scrollRight = el.scrollWidth - el.clientWidth - scrollLeft;
            
            this.showLeftShadow = scrollLeft > 1;
            this.showRightShadow = scrollRight > 1;
        },
        toggleInformation(){
            this.isVisible = !this.isVisible;
        },
        ...mapActions([
            'loadConfiguration',
        ]),
        clickDownload(type) {
            let query = queryString.stringify({
                ...this.form
            });
            window.open(`/${this.resource}/${type}/?${query}`, '_blank');
        },
        customIndex(index) {
            return index + 1
        },
        initForm() {
            this.form = {
                period: 'month',
                date_start: moment().format('YYYY-MM-DD'),
                date_end: moment().format('YYYY-MM-DD'),
                month_start: moment().format('YYYY-MM'),
                month_end: moment().format('YYYY-MM'),
                currency_type_id: 'PEN'
            }
        },
        ShowTransferModal(){
            this.showDialogTransfer = true;
        },
        async getRecordsByFilter() {
            this.loading_submit = await true
            await this.getRecords()
            this.loading_submit = await false
        },
        getRecords() {
            this.records = [];
            this.loading_submit = true
            this.totals.t_initial_balance = 0;
            this.totals.t_documents = 0;
            this.totals.t_sale_notes = 0;
            this.totals.t_quotations = 0;
            this.totals.t_contracts = 0;
            this.totals.t_technical_services = 0;
            this.totals.t_income = 0;
            this.totals.t_purchases = 0;
            this.totals.t_expenses = 0;
            this.totals.t_balance = 0;

            return this.$http.get(`/${this.resource}/records?${this.getQueryParameters()}`)
                .then((response) => {
                    let data = response.data;
                    this.records = data.records
                    this.totals = data.totals
                })
                .catch(() => {
                    this.loading_submit = false

                })
                .finally(() => {
                    this.loading_submit = false
                });
        },
        getQueryParameters() {
            return queryString.stringify({
                ...this.form
            })
        },
        changeDisabledDates() {
            if (this.form.date_end < this.form.date_start) {
                this.form.date_end = this.form.date_start
            }
            // this.loadAll();
        },
        changeDisabledMonths() {
            if (this.form.month_end < this.form.month_start) {
                this.form.month_end = this.form.month_start
            }
            // this.loadAll();
        },
        changePeriod() {
            if (this.form.period === 'month') {
                this.form.month_start = moment().format('YYYY-MM');
                this.form.month_end = moment().format('YYYY-MM');
            }
            if (this.form.period === 'between_months') {
                this.form.month_start = moment().startOf('year').format('YYYY-MM'); //'2019-01';
                this.form.month_end = moment().endOf('year').format('YYYY-MM');
            }
            if (this.form.period === 'date') {
                this.form.date_start = moment().format('YYYY-MM-DD');
                this.form.date_end = moment().format('YYYY-MM-DD');
            }
            if (this.form.period === 'between_dates') {
                this.form.date_start = moment().startOf('month').format('YYYY-MM-DD');
                this.form.date_end = moment().endOf('month').format('YYYY-MM-DD');
            }
            // this.loadAll();
        },
        changeCurrency() {
            this.$emit('changeCurrency', this.form.currency_type_id)
            this.getRecords()
        }


    }
}
</script>
