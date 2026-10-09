<template>
    <div>
        <div class="page-header pr-0">
            <h2>
                <a href="/hotels/reception">
                    <svg  xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24"  height="24"  viewBox="0 0 24 24"  fill="none"  stroke="currentColor"  stroke-width="2"  stroke-linecap="round"  stroke-linejoin="round"  class="icon icon-tabler icons-tabler-outline icon-tabler-building-skyscraper"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 21l18 0" /><path d="M5 21v-14l8 -4v18" /><path d="M19 21v-10l-6 -4" /><path d="M9 9l0 .01" /><path d="M9 12l0 .01" /><path d="M9 15l0 .01" /><path d="M9 18l0 .01" /></svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active"><span>{{ title }}</span></li>
            </ol>
            <div class="pull-right right-wrapper">
                <el-button type="primary"
                           class="mt-2 mr-2"
                           @click="onOpenModalProducts">
                    <i class="fa fa-plus"></i>
                    <span class="ml-2">Agregar Producto</span>
                </el-button>
            </div>
        </div>
        <div class="card mb-0 tab-content-default row-new">
            <!-- <div class="card-header bg-info">
                <h3 class="my-0">{{ title }}</h3>
            </div> -->
            <div class="card-body">
                <div class="row justify-content-between">                    
                    <div class="col-12">                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-center">Cant.</th>
                                    <th class="text-center">Precio</th>
                                    <th class="text-right">Importe</th>
                                    <th class="text-center">Comprobante</th>
                                    <th class="text-center">Estado del pago</th>
                                    <th class="text-center">M. Pago</th>
                                    <th class="text-center">Destino</th>
    
                                    <th class="text-right"></th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="(p, index) in form.products"
                                    :key="index">
                                    <td>{{ p.item.description }}</td>
                                    <td class="text-center">{{ p.quantity | toDecimals }}</td>
                                    <td class="text-center">
                                        {{ p.input_unit_price_value | toDecimals }}
                                    </td>
                                    <td class="text-right">{{ p.total | toDecimals }}</td>
                                    <td class="text-right">{{ p.document}}</td>
                                    <td class="text-center">
                                        <div class="d-inline-block"
                                             style="max-width: 150px">
                                            <el-select
                                                v-model="p.payment_status"
                                                placeholder="Proceso de pago"
                                                :disabled="p.is_registered"
                                                @change="changePaymentStatus(index)"
                                            >
                                                <el-option label="Cancelado"
                                                           value="PAID"></el-option>
                                                <el-option
                                                    label="Cargar a habitación"
                                                    value="DEBT"
                                                ></el-option>
                                            </el-select>
                                            <div
                                                v-if="errors.payment_status"
                                                class="form-control-feedback"
                                            >
                                                {{ errors.payment_status[0] }}
                                            </div>
                                        </div>
                                    </td>
    
                                    <td style="max-width: 150px">
                                        
                                        <template v-if="isPaid(p)">
                                            <div class="form-group mb-2 mr-2" :class="{ 'has-danger': errors[`products.${index}.rent_payment.payment_method_type_id`] }">
                                                <el-select
                                                    v-model="p.rent_payment.payment_method_type_id"
                                                    filterable
                                                    :disabled="p.is_registered"
                                                >
                                                    <el-option
                                                        v-for="option in payment_method_types"
                                                        :key="option.id"
                                                        :value="option.id"
                                                        :label="option.description"
                                                    ></el-option>
                                                </el-select>
    
                                                <small
                                                    class="form-control-feedback"
                                                    v-if="errors[`products.${index}.rent_payment.payment_method_type_id`]"
                                                    v-text="errors[`products.${index}.rent_payment.payment_method_type_id`][0]"
                                                ></small>
                                            </div>
                                        </template>
    
                                    </td>
                                    <td style="max-width: 150px">
                                        
                                        <template v-if="isPaid(p)">
                                            <div class="form-group mb-2 mr-2" :class="{ 'has-danger': errors[`products.${index}.rent_payment.payment_destination_id`] }">
                                                <el-select
                                                    v-model="p.rent_payment.payment_destination_id"
                                                    filterable
                                                    :disabled="p.is_registered"
                                                >
                                                    <el-option
                                                        v-for="option in payment_destinations"
                                                        :key="option.id"
                                                        :value="option.id"
                                                        :label="option.description"
                                                    ></el-option>
                                                </el-select>
    
                                                <small
                                                    class="form-control-feedback"
                                                    v-if="errors[`products.${index}.rent_payment.payment_destination_id`]"
                                                    v-text="errors[`products.${index}.rent_payment.payment_destination_id`][0]"
                                                ></small>
                                            </div>
                                        </template>
                                    </td>
    
                                    <td>
                                        <el-button type="danger"
                                            :disabled="p.is_registered"
                                            @click="onDeleteProduct(index)">
                                            <i class="fa fa-trash"></i>
                                        </el-button>
                                    </td>
                                </tr>
                                </tbody>
                                <tfoot v-if="form.products.length > 0">
                                    <tr>
                                        <td class="text-right">
                                            <strong>SUBTOTAL</strong> 
                                        </td>
                                        <td class="text-right">
                                           <strong>{{ this.form.subtotal | toDecimals }}</strong> 
                                        </td>
    
                                        <!-- <td class="text-right">
                                            <strong>IGV</strong>
                                        </td>
                                        <td class="text-right">
                                            <strong>{{ this.form.igv | toDecimals }}</strong>
                                        </td> -->
    
                                        <td class="text-right">
                                            <strong>TOTAL</strong>
                                        </td>
                                        <td class="text-right">
                                            <strong>{{ this.form.total | toDecimals }}</strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="d-flex align-items-center flex-column-mb" style="justify-content: space-between;">
                            <div class="form-control-feedback">
                                <p>El valor total es del último pedido que esta realizando</p>
                            </div>
                            <div v-if="errors.products"
                                 class="form-control-feedback">
                                {{ errors.products[0] }}
                            </div>
                        
                            <div class="pull-right col-md-2 form-group">
                                <div :class="{ 'has-danger': errors.series_id }"
                                    class="form-group">
                                    <label class="control-label">Serie</label>
                                    <el-select v-model="document.series_id">
                                        <el-option
                                            v-for="option in series"
                                            :key="option.id"
                                            :label="option.number"
                                            :value="option.id"
                                        ></el-option>
                                    </el-select>
                                    <small
                                        v-if="errors.series_id"
                                        class="form-control-feedback"
                                        v-text="errors.series_id[0]"
                                    ></small>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions text-right pt-2 mt-4">
                            <el-button
                                    class="second-buton btn btn-default mr-1"
                                    style="min-width: 180px;"
                                    @click="onGotoBack"
                                >
                                    Cancelar
                            </el-button>
                            <template v-if="canMakePayment">
                                <el-button
                                        :disabled="loading"
                                        :loading="loading"
                                        type="primary"
                                        class="btn m-1"
                                        style="min-width: 180px;"
                                        @click="onSubmit"
                                    >
                                        <i class="fa fa-save"></i>
                                        <span class="ml-2">Guardar</span>
                                </el-button>
                                <div v-if="this.products.length>0 && form.products.length  < 1"
                                    class="pull-right">
                                    <el-button
                                        :disabled="loading"
                                        :loading="loading"
                                        type="primary"
                                        class="btn m-1"
                                        style="min-width: 180px;"
                                        @click="onTotalDeleteProduct"
                                    >
                                        <i class="fa fa-save"></i>
                                        <span class="ml-2">Guardar</span>
                                    </el-button>
                                </div>
                            </template>                            
                        </div>
                        <!-- <template v-else>
                            <div
                             class="pull-right">
                            <el-button
                                class="btn-block"
                                type="primary"
                                @click="onGotoBack"
                            >
                                <i class="fa fa-arrow-left"></i>
                                <span class="ml-2">Regresar</span>
                            </el-button>
                        </div>
                        </template> -->
                        
                        

                    </div>
                </div>
            </div>
        </div>
        <tenant-documents-items-list
            :configuration="configuration"
            :editNameProduct="configuration.edit_name_product"
            :exchange-rate-sale="0"
            :isEditItemNote="false"
            :recordItem="recordItem"
            :showDialog.sync="showDialogAddItem"
            :typeUser="typeUser"
            :percentageIgv="percentage_igv"
            currency-type-id-active="PEN"
            operation-type-id="0101"
            @add="onAddItem"

        ></tenant-documents-items-list>

        <sale-note-options :configuration="config"
                           :recordId="form.sale_note_id"
                           :showClose="true"
                           :showDialog.sync="showDialogSaleNoteOptions">
        </sale-note-options>

    </div>
</template>

<script>
// import DocumentFormItem from "../../../../../../../resources/js/views/tenant/documents/partials/item.vue";
import {functions} from "../../../../../../../resources/js/mixins/functions";
import moment from "moment";
import {mapState} from "vuex/dist/vuex.mjs";
import SaleNoteOptions from "@views/sale_notes/partials/options.vue";

export default {
    components: {
        // DocumentFormItem,
        SaleNoteOptions,
    },
    mixins: [
        functions
    ],
    props: {
        rent: {
            type: Object,
            required: true,
        },
        products: {
            type: Array,
            required: false,
            default: [],
        },
        configuration: {
            type: Object,
            required: true,
        },
        establishment: {
            type: Object,
            required: true,
        },
        allSeries: {
            type: Array,
            required: true,
        },
    },
    data() {
        return {
            title: "",
            showDialogAddItem: false,
            recordItem: null,
            form: {
                products: [],
                subtotal: 0,
                total: 0,
                igv: 0,
                date_of_issue: moment().format("YYYY-MM-DD"),
                establishment_id: null,
                sale_note_id: null,
            },
            errors: {},
            typeUser: "admin",
            loading: false,
            payment_method_types: [],
            payment_destinations: [],
            resource_documents: "sale-notes",
            document: {
                payments: [],
                items: [],
            },
            series: [],
            form_cash_document: {},
            showDialogSaleNoteOptions: false,
        };
    },
    computed: {
        ...mapState([
            'config',
        ]),
        canMakePayment: function () {
            if (this.form.sale_note_id!=null || !this.isAtLeastOneNotRegister()) {
                return false;
            }
            return true;
        },
    },
    async mounted() {

        this.form.establishment_id = this.establishment.id;
        this.getPercentageIgv();
        if (this.products) {
            const products = this.products.map((p) => {
                p.item.payment_status = p.payment_status
                p.item.is_registered = p.id ? true : false
                p.item.id = p.id
                p.item.document = p.document
                return p.item;
            });
            this.form.products = products;
            this.onCalculateTotals();
        }
        this.series = _.filter(this.allSeries, {
            document_type_id: '80',
        });
        await this.initDocument();
    },
    async created() 
    {
        this.title = `Habitación ${this.rent.room.name} - Agregar productos`;
        await this.getTables()
    },
    methods: {
        changePaymentStatus(index)
        {
            this.form.products[index].rent_payment.payment_destination_id = null
            this.form.products[index].rent_payment.payment_method_type_id = null
        },
        isPaid(row)
        {
            return row.payment_status === 'PAID'
        },
        isAtLeastOnePaid(items)
        {
            return items.some(item => item.payment_status === "PAID");
        },
        isAtLeastOneNotRegister()
        {
            return this.form.products.some(item => item.is_registered === false);
        },
        async getTables()
        {
            this.loading = true
            await this.$http.get('/hotels/reception/rent-products-tables')
                            .then((response) => {
                                this.payment_method_types = response.data.payment_method_types
                                this.payment_destinations = response.data.payment_destinations
                            })
                            .finally(() => {
                                this.loading = false
                            })
        },
        async onSubmit() {

            this.form.products = this.form.products
                .filter((item) => item.is_registered === false);

            if (this.isAtLeastOnePaid(this.form.products)) {
                const paidProducts = this.form.products.filter(item => item.payment_status === "PAID");
                this.document.items.push(...paidProducts);
                await this.onGoToInvoice();
            }

            this.loading = true;
            this.$http
                .post(
                    `/hotels/reception/${this.rent.id}/rent/products/store`,
                    this.form
                )
                .then((response) => {
                    if(this.form.sale_note_id == null){
                        window.location.href = "/hotels/reception";
                    }
                    this.$message({
                        message: response.data.message,
                        type: "success",
                    });
                })
                .catch((error) => this.axiosError(error))
                .finally(() => (this.loading = false));
        },
        onTotalDeleteProduct() {
            this.$confirm(
                "¿Estás seguro de eliminar todos los productos?",
                "Cuidado",
                {
                    confirmButtonText: "Si",
                    cancelButtonText: "No",
                    type: "warning",
                }
            )
                .then(() => {
                    this.onSubmit()
                })
                .catch(() => {
                });
        },
        onDeleteProduct(index) {
            this.$confirm(
                "¿Estás seguro de eliminar el producto seleccionado?",
                "Cuidado",
                {
                    confirmButtonText: "Sí",
                    cancelButtonText: "No",
                    type: "warning",
                }
            )
            .then(() => {
                this.form.products.splice(index, 1);
                this.onCalculateTotals();
            })
            .catch(() => {});
        },
        onOpenModalProducts() {
            this.showDialogAddItem = true;
        },
        onCalculateTotals() {
            const unRegisteredProducts = this.form.products.filter(p => p.is_registered === false);
            this.form.subtotal = unRegisteredProducts
                .map(p => p.total_value)
                .reduce((a, p) => a + p, 0);

            this.form.igv = unRegisteredProducts
                .map(p => p.total_igv)
                .reduce((a, p) => a + p, 0);

            this.form.total = this.form.subtotal + this.form.igv;
        },
        getDefaultValuesRentPayment()
        {
            let payment_method_type_id = null
            let payment_destination_id = null

            if(this.payment_method_types.length > 0)
            {
                payment_method_type_id = this.payment_method_types[0].id
            }

            if(this.payment_destinations.length > 0)
            {
                const exist_cash = _.find(this.payment_destinations, { id : 'cash'})
                payment_destination_id = (exist_cash) ? exist_cash.id : this.payment_destinations[0].id
            }

            return {
                payment_method_type_id,
                payment_destination_id
            }
        },
        async onAddItem(product) {

            const { payment_method_type_id, payment_destination_id } = await this.getDefaultValuesRentPayment()

            const newProduct = {
                id: null,
                payment_status: "PAID",
                affectation_igv_type_id: product.affectation_igv_type_id,
                attributes: product.attributes,
                charges: product.charges,
                discounts: product.discounts,
                item_id: product.item_id,
                name_product_pdf: product.name_product_pdf,
                percentage_igv: product.percentage_igv,
                percentage_isc: product.percentage_isc,
                percentage_other_taxes: product.percentage_other_taxes,
                price_type_id: product.price_type_id,
                quantity: product.quantity,
                system_isc_type_id: product.system_isc_type_id,
                total: product.total,
                total_base_igv: product.total_base_igv,
                total_base_isc: product.total_base_isc,
                total_base_other_taxes: product.total_base_other_taxes,
                total_charge: product.total_charge,
                total_discount: product.total_discount,
                total_igv: product.total_igv,
                total_isc: product.total_isc,
                total_other_taxes: product.total_other_taxes,
                total_plastic_bag_taxes: product.total_plastic_bag_taxes,
                total_taxes: product.total_taxes,
                total_value: product.total_value,
                unit_price: product.unit_price,
                unit_value: product.unit_value,
                warehouse_id: product.warehouse_id,
                input_unit_price_value: product.input_unit_price_value,
                item: this.getNewItem(product),
                rent_payment: {
                    payment_method_type_id: payment_method_type_id,
                    payment_destination_id: payment_destination_id,
                    reference: null,
                    payment: product.total
                },
                is_registered:false,
                document: "",
                is_various_item: product.various_item
            };

            const repeteads = this.form.products.filter(
                (p) => ( (product.various_item &&p.item_id === newProduct.item_id )? false :  (p.item_id === newProduct.item_id && p.is_registered == false))
            );
            
            if (repeteads.length > 0) {
                this.form.products = this.form.products.map((p) => {
                    if ( !p.is_various_item &&  p.item_id === newProduct.item_id && p.is_registered == false) {
                        return newProduct;
                    }
                    return p;
                });
            } else {
                this.form.products.push(newProduct);
            }
            this.onCalculateTotals();
        },
        getNewItem(product) {

            let new_item = product.item
            new_item.item_type_id = product.item.item_type_id
            new_item.item_code = product.item.item_code
            new_item.item_code_gs1 = product.item.item_code_gs1
            new_item.presentation = product.item.presentation
            new_item.IdLoteSelected = product.item.IdLoteSelected

            return new_item

        },
        initDocument() {
            this.document = {
                customer_id: this.rent.customer_id,
                customer: this.rent.customer,
                document_type_id: '80',
                series_id: this.series.length > 0 ? this.series[0].id : null,
                prefix: 'NV',
                establishment_id: this.establishment.id,
                number: "#",
                date_of_issue: moment().format("YYYY-MM-DD"),
                time_of_issue: moment().format("HH:mm:ss"),
                currency_type_id: "PEN",
                purchase_order: null,
                exchange_rate_sale: 0,
                total_prepayment: 0,
                total_charge: 0,
                total_discount: 0,
                total_exportation: 0,
                total_free: 0,
                total_taxed: 0,
                total_unaffected: 0,
                total_exonerated: 0,
                total_igv: 0,
                total_base_isc: 0,
                total_isc: 0,
                total_base_other_taxes: 0,
                total_other_taxes: 0,
                total_taxes: 0,
                total_value: 0,
                total: 0,
                subtotal: 0,
                operation_type_id: "0101",
                date_of_due: moment().format("YYYY-MM-DD"),
                delivery_date: moment().format("YYYY-MM-DD"),
                items: [],
                charges: [],
                discounts: [],
                attributes: [],
                guides: [],
                additional_information: null,
                actions: {
                    format_pdf: "a4",
                },
                dispatch_id: null,
                dispatch: null,
                is_receivable: false,
                payments: [],
                hotel: {},
                hotel_data_persons: [],
                source_module: 'HOTEL',
                hotel_rent_id: null
            };
        },
        async onGoToInvoice() {
            try {
                await this.onCalculateTotalsDocument();
                await this.setDataPayments();
                
                let validate_payment_destination = this.validatePaymentDestination();
                if (validate_payment_destination.error_by_item > 0) {
                    return this.$message.error("El destino del pago es obligatorio");
                }

                this.loading = true;

                const response = await this.$http.post(`/${this.resource_documents}`, this.document);

                if (response.data.success) {
                    this.form.sale_note_id = response.data.data.id;
                    this.successGoToInvoice();
                    this.$emit("update:showDialog", false);
                    this.saveCashDocument();
                } else {
                    this.$message.error(response.data.message);
                }
            } catch (error) {
                console.error("Error en onGoToInvoice:", error);
                if (error.response) {
                    this.errors = error.response.data;
                } else {
                    this.$message.error(error.response.data.message || "Error inesperado");
                }
            } finally {
                this.loading = false;
            }
        },
        saveCashDocument() {
            this.$http
                .post(`/cash/cash_document`, this.form_cash_document)
                .then((response) => {
                    if (!response.data.success) {
                        this.$message.error(response.data.message);
                    }
                })
                .catch((error) => {
                    this.axiosError(error);
                });
        },
        onCalculateTotalsDocument() {
            let total_exportation = 0;
            let total_taxed = 0;
            let total_exonerated = 0;
            let total_unaffected = 0;
            let total_free = 0;
            let total_igv = 0;
            let total_value = 0;
            let total = 0;
            let total_plastic_bag_taxes = 0;
            let total_discount = 0;
            let total_charge = 0;
            this.document.items.forEach((row) => {
                total_discount += parseFloat(row.total_discount);
                total_charge += parseFloat(row.total_charge);

                if (row.affectation_igv_type_id === "10") {
                    total_taxed += parseFloat(row.total_value);
                }

                if (row.affectation_igv_type_id === '20') {
                    total_exonerated += parseFloat(row.total_value)
                }

                if (["10", "20", "30", "40"].indexOf(row.affectation_igv_type_id) < 0) {
                    total_free += parseFloat(row.total_value);
                }

                if (
                    ["10", "20", "30", "40"].indexOf(row.affectation_igv_type_id) > -1
                ) {
                    total_igv += parseFloat(row.total_igv);
                    total += parseFloat(row.total);
                }

                total_value += parseFloat(row.total_value);
                total_plastic_bag_taxes += parseFloat(row.total_plastic_bag_taxes);

                if (["13", "14", "15"].includes(row.affectation_igv_type_id)) {
                    let unit_value =
                        row.total_value / row.quantity / (1 + this.percentage_igv / 100);
                    let total_value_partial = unit_value * row.quantity;
                    row.total_taxes = row.total_value - total_value_partial;
                    row.total_igv = row.total_value - total_value_partial;
                    row.total_base_igv = total_value_partial;
                    total_value -= row.total_value;
                }
            });

            this.document.total_exportation = _.round(total_exportation, 2);
            this.document.total_taxed = _.round(total_taxed, 2);
            this.document.total_exonerated = _.round(total_exonerated, 2);
            this.document.total_unaffected = _.round(total_unaffected, 2);
            this.document.total_free = _.round(total_free, 2);
            this.document.total_igv = _.round(total_igv, 2);
            this.document.total_value = _.round(total_value, 2);
            this.document.total_taxes = _.round(total_igv, 2);
            this.document.total_plastic_bag_taxes = _.round(
                total_plastic_bag_taxes,
                2
            );
            this.document.total = _.round(
                total + this.document.total_plastic_bag_taxes,
                2
            );
            this.document.subtotal = _.round(
                this.document.total,
                2
            );
        },
        validatePaymentDestination() {
            let error_by_item = 0;
            this.document.payments.forEach((item) => {
                if (item.payment_destination_id == null) error_by_item++;
            });

            return {
                error_by_item: error_by_item,
            };
        },
        successGoToInvoice() {
            this.initFormCashDocument()
            this.form_cash_document.sale_note_id = this.form.sale_note_id
            this.showDialogSaleNoteOptions = true
        },
        initFormCashDocument() {
            this.form_cash_document = {
                document_id: null,
                sale_note_id: null,
            };
        },
        async setDataPayments(){
            this.document.payments = this.document.items.map(item => ({
                id: null,
                document_id: null,
                date_of_payment: this.document.date_of_issue,
                payment_method_type_id: item.rent_payment.payment_method_type_id,
                payment_destination_id: item.rent_payment.payment_destination_id,
                reference: item.rent_payment.reference,
                payment: item.total
            }));
        },
        onGotoBack() {
            window.location.href = "/hotels/reception";
        },
        
    },
};
</script>
