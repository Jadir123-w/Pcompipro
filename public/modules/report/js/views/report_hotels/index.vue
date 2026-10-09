<template>
    <div>
        <div class="page-header pr-0">
            <h2><a href="/list-reports">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-analytics">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <path d="M14 3v4a1 1 0 0 0 1 1h4"></path>
                    <path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z"></path>
                    <path d="M9 17l0 -5"></path>
                    <path d="M12 17l0 -1"></path>
                    <path d="M15 17l0 -3"></path>
                </svg>
            </a></h2>
            <ol class="breadcrumbs">
                <li class="active"><span>Reporte habitaciones hoteles</span></li>
            </ol>
        </div>
        <div class="card mb-0 pt-2 pt-md-0">
        <!-- <div class="card-header bg-info">
            <h3 class="my-0">Reporte habitaciones hoteles</h3>
        </div> -->
        <div class="card mb-0">
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <th>#</th>
                        <th>Nombres y Apellidos</th>
                        <!--                            <th>T. Documento</th>-->
                        <th> Habitación</th>
                        <th>Status de pago</th>
                        <th>Status checkout</th>
                        <th>Productos</th>
                        <th>Fecha de entrada</th>
                        <th>Hora de entrada</th>
                        <th>Fecha de salida</th>
                        <th>Hora de salida</th>
                        <!-- <th  >Edad</th>
                         <th  >E. civil</th>
                         <th  >Nacionalidad</th>
                         <th  >Procedencia</th>
                         <th>Comprobante</th>-->

                    <tr>
                    <tr slot-scope="{ index, row }">
                        <td>{{ index }}</td>
                        <td>{{ row.customer.description }}</td>
                        <!--                            <td>{{row.identity_document_type_description}}</td>-->
                        <td>{{ row.room ? row.room.name : '' }}</td>
                        <td>{{ row.payment_status === "PAID" ? "Pagado" : "Debe" }}</td>
                        <td>{{ row.status }}</td>
                        <td>


                            <template
                                v-if="row.items.length > 0 ">


                                <button
                                    class="btn waves-effect waves-light btn-xs btn-primary"
                                    type="button"
                                    @click.prevent="clickProductsItems(row.items,row.room?row.room.name:'')"

                                >
                                    <i class="fa fa-database"></i>
                                </button>

                                <br>
                                {{ checkProductDebts(row.items) }}
                            </template>
                        </td>
                        <td>{{ row.input_date }}</td>
                        <td>{{ row.input_time }}</td>
                        <td>{{ row.output_date }}</td>
                        <td>{{ row.output_time }}</td>

                        <!--

                        <td>{{row.age}}</td>
                        <td>{{row.civil_status}}</td>
                        <td >{{ row.nacionality }}</td>
                        <td >{{ row.origin }}</td>
                        <td >{{ row.document }}</td>
                        -->

                    </tr>

                </data-table>


            </div>
            <item-modal
                :from_documents="false"
                :item="recordItem"
                :room_name="room_name"
                :showDialog.sync="showItemModal"
            ></item-modal>

        </div>

    </div>
    </div>
</template>

<script>

import ItemModal from './modal_item_info.vue'
import DataTable from '../../components/DataTableDocumentHotel.vue'

export default {
    components: {
        DataTable,
        ItemModal,
    },
    data() {
        return {
            resource: 'reports/report_hotels',
            form: {},
            recordItem: {},
            showItemModal: false,
            room_name: '',
        }
    },
    async created() {
    },
    methods: {


        clickProductsItems(row, room) {
            this.recordItem = row
            this.showItemModal = true
            this.room_name = room
        },
        checkProductDebts(row) {
            let str = '';
            let deb = 0;
            row.forEach(function (a, b) {
                if (a.payment_status !== 'PAID') {
                    deb += a.item.total;
                }
            });
            if (deb != 0) {
                str = `Debe ${deb} en producto(s)`;
            }

            return str;
        }
    },

}
</script>
