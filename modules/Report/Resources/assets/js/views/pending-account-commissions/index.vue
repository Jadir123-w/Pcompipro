<template>
    <div>
        <div class="page-header pr-0">
            <h2>
                <a href="/list-reports">
                    <svg xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-file-analytics"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M14 3v4a1 1 0 0 0 1 1h4" /><path d="M17 21h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2z" /><path d="M9 17l0 -5" /><path d="M12 17l0 -1" /><path d="M15 17l0 -3" /></svg>
                </a>
            </h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>
                        Cuentas pendientes 
                        <el-tooltip class="item" effect="dark" content="Solo se calcularan los pagos realizados después del vencimiento y antes que superen los días maximos de deuda vencida" placement="top-end">
                            <i class="fa fa-info-circle"></i>
                        </el-tooltip>
                    </span>
                </li>
            </ol>
        </div>
        <div class="card mb-0 pt-2 pt-md-0 tab-content-default row-new">
            <div class="card mb-0">
                <div class="card-body">
                    <data-table
                        :resource="resource">
                        <tr slot="heading">
                            <th>#</th>
                            <th>Vendedor</th>
                            <th class="text-center">Monto de cobro fuera de fecha</th>
                            <th class="text-center">Monto de cobro comisionado</th>
                            <th class="text-center">Monto total de cobro efectuado</th>
                            <th class="text-center">Comisión</th>
                        </tr>
                        <tr slot-scope="{ index, row }">
                            <td>{{ index }}</td>
                            <td>{{ row.user_name }}</td>
                            <td class="text-center">{{ row.total_out_of_range }}</td>
                            <td class="text-center">{{ row.total_within_range }}</td>
                            <td class="text-center">{{ row.total_completed }}</td>
                            <td class="text-center">{{ row.commission }}</td>
                        </tr>
                    </data-table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import DataTable from '../../components/DataTablePendingAccounts.vue'

export default {
    components: { DataTable },
    data() {
        return {
            resource: 'reports/pending-account-commissions',
        }
    },
    async created() { 
    },
    methods: { 
             
            
    }
}
</script>