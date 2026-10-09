<template>
    <div>
        <div class="page-header pr-0">
            <h2>
                <a href="/list-reports">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>Ventas por marca</span>
                </li>
            </ol>
        </div>
        <div class="card mb-0 pt-2 pt-md-0 tab-content-default row-new">
            <div class="card mb-0">
                <div class="card-body">
                    <data-table-sales-by-brand
                        :resource="resource"
                        @update:records="records = $event"
                    ></data-table-sales-by-brand>
                    <div class="table-responsive mt-3">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Marca</th>
                                    <th>Unidades Vendidas
                                        <el-tooltip
                                            class="item"
                                            content="cantidad de productos vendidos"
                                            effect="dark"
                                            placement="top"
                                        >
                                            <i class="fa fa-info-circle"></i>
                                        </el-tooltip>
                                    </th>
                                    <th>Valor de venta 
                                        <el-tooltip
                                            class="item"
                                            content="Sin IGV"
                                            effect="dark"
                                            placement="top"
                                        >
                                            <i class="fa fa-info-circle"></i>
                                        </el-tooltip>
                                    </th>
                                    <th>Utilidad generada</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in records" :key="row.id">
                                    <td>{{ index + 1 }}</td>
                                    <td>{{ row.brand_name }}</td>
                                    <td>{{ formatNumber(row.quantity_sold) }}</td>
                                    <td>{{ formatNumber(row.total_sale_value) }}</td>
                                    <td>{{ formatNumber(row.total_profit) }}</td>
                                </tr>
                            </tbody>
                            <tfoot v-if="records.length">
                                <tr>
                                    <td colspan="2" class="text-right">TOTALES:</td>
                                    <td>{{ formatNumber(totalQuantity) }}</td>
                                    <td>{{ formatNumber(totalSaleValue) }}</td>
                                    <td>{{ formatNumber(totalProfit) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import DataTableSalesByBrand from "../../components/DataTableSalesByBrand.vue";

export default {
    components: { DataTableSalesByBrand },
    data() {
        return {
            resource: "reports/sales-by-brand",
            records: []
        };
    },
    computed: {
        totalQuantity() {
            return this.records.reduce((acc, item) => acc + Number(item.quantity_sold), 0)
        },
        totalSaleValue() {
            return this.records.reduce((acc, item) => acc + Number(item.total_sale_value), 0)
        },
        totalProfit() {
            return this.records.reduce((acc, item) => acc + Number(item.total_profit), 0)
        }
    },
    methods: {
        formatNumber(value) {
            if (value === null || value === undefined) return '';
            return Number(value).toFixed(2);
        }
    }
};
</script>