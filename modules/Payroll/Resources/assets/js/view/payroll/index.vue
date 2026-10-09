<template>
    <div>
        <div class="page-header pr-0">
            <h2><a href="/payroll-main/list">
                    <svg xmlns="http://www.w3.org/2000/svg" style="margin-top: -5px;" width="24" height="24"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-building-factory-2">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M3 21h18" />
                        <path d="M5 21v-12l5 4v-4l5 4h4" />
                        <path
                            d="M19 21v-8l-1.436 -9.574a.5 .5 0 0 0 -.495 -.426h-1.145a.5 .5 0 0 0 -.494 .418l-1.43 8.582" />
                        <path d="M9 17h1" />
                        <path d="M14 17h1" />
                    </svg>
                </a></h2>
            <ol class="breadcrumbs">
                <li class="active">
                    <span>
                        Listado de Planillas
                    </span>
                </li>
            </ol>
            <div class="right-wrapper pull-right">
                <button class="btn btn-custom btn-sm  mt-2 mr-2" type="button" @click.prevent="clickCreate()">
                    <i class="fa fa-plus-circle"></i> Nueva Planilla
                </button>
            </div>

        </div>
        <div class="card tab-content-default row-new mb-0">
            <div class="card-body">
                <data-table :resource="resource">
                    <tr slot="heading">
                        <th>Código</th>
                        <th>Periodo</th>
                        <th>Fecha Inicio</th>
                        <th>Fecha Fin</th>
                        <th>Empleados</th>
                        <th>Total Neto</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                    <tr slot-scope="{ index, row }">
                        <td>{{ row.codigo }}</td>
                        <td>{{ row.periodo }}</td>
                        <td>{{ row.fecha_inicio }}</td>
                        <td>
                            {{ row.fecha_fin }}
                        </td>
                        <td>{{ row.total_empleados }}</td>
                        <td>{{ row.total_neto }}</td>
                        <td>{{ row.estado }}</td>
                        <td class="text-right">
                            <!-- Botón Editar - Solo en BORRADOR y PROCESADA -->
                            <button v-if="row.estado === 'BORRADOR' || row.estado === 'PROCESADA'" type="button"
                                class="btn waves-effect waves-light btn-xs btn-info"
                                @click.prevent="clickCreate(row.id)">
                                <i class="fa fa-edit"></i> Editar
                            </button>

                            <button type="button" class="btn waves-effect waves-light btn-xs btn-default"
                                @click.prevent="clickDetail(row.id)">
                                <i class="fa fa-list"></i> Detalle
                            </button>

                            <!-- Botón Procesar - Solo en BORRADOR -->
                            <button v-if="row.estado === 'BORRADOR'" type="button"
                                class="btn waves-effect waves-light btn-xs btn-primary"
                                @click.prevent="clickProcesar(row.id)">
                                <i class="fa fa-cogs"></i> Procesar
                            </button>

                            <!-- Botón Aprobar - Solo en PROCESADA -->
                            <button v-if="row.estado === 'PROCESADA'" type="button"
                                class="btn waves-effect waves-light btn-xs btn-success"
                                @click.prevent="clickAprobar(row.id)">
                                <i class="fa fa-check"></i> Aprobar
                            </button>

                            <!-- Botón Imprimir - PROCESADA, APROBADA, PAGADA -->
                            <button
                                v-if="row.estado === 'PROCESADA' || row.estado === 'APROBADA' || row.estado === 'PAGADA'"
                                type="button" class="btn waves-effect waves-light btn-xs btn-secondary"
                                @click.prevent="clickImprimir(row.id)">
                                <i class="fa fa-print"></i> Imprimir
                            </button>

                            <!-- Botón Enviar Boletas - APROBADA, PAGADA -->
                            <button v-if="row.estado === 'APROBADA' || row.estado === 'PAGADA'" type="button"
                                class="btn waves-effect waves-light btn-xs btn-warning"
                                @click.prevent="clickEnviarBoletas(row.id)">
                                <i class="fa fa-envelope"></i> Enviar Boletas
                            </button>

                            <!-- Botón Eliminar - Solo en BORRADOR -->
                            <button v-if="row.estado === 'BORRADOR'" type="button"
                                class="btn waves-effect waves-light btn-xs btn-danger"
                                @click.prevent="clickDelete(row.id)">
                                <i class="fa fa-trash"></i> Eliminar
                            </button>
                        </td>
                    </tr>
                </data-table>
            </div>

            <Planilla-form :recordId.sync="recordId" :showDialog.sync="showDialog"></Planilla-form>


        </div>
    </div>

</template>
<style>
@media only screen and (max-width: 485px) {
    .filter-container {
        margin-top: 0;

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

import PlanillaForm from "../../components/PlanillaForm.vue";
import DataTable from '../../components/Datatable.vue'
import { deletable } from '../../../../../../../resources/js/mixins/deletable'
import queryString from 'query-string'

export default {
    mixins: [deletable],
    components: {
        DataTable, PlanillaForm
    },
    data() {
        return {
            showDialog: false,
            resource: 'payroll-main',
            recordId: null,
        }
    },
    created() {
    },
    methods: {
        clickCreate(recordId = null) {
            this.recordId = recordId;
            this.showDialog = true;
        },

        clickDelete(id) {
            this.destroy(`/${this.resource}/record/${id}`).then(() =>
                this.$eventHub.$emit('reloadData')
            )
        },

        clickProcesar(id) {
            this.$confirm('¿Está seguro de procesar esta planilla? Se calcularán todos los conceptos de nómina.', 'Confirmar Procesamiento', {
                confirmButtonText: 'Sí, Procesar',
                cancelButtonText: 'Cancelar',
                type: 'warning'
            }).then(() => {
                this.$http.post(`/${this.resource}/procesar/${id}`)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message);
                            this.$eventHub.$emit('reloadData');
                        } else {
                            this.$message.error(response.data.message);
                        }
                    })
                    .catch(error => {
                        this.$message.error('Error al procesar la planilla');
                        console.error(error);
                    });
            }).catch(() => {
                // Usuario canceló
            });
        },

        clickAprobar(id) {
            this.$confirm('¿Está seguro de aprobar esta planilla? Una vez aprobada, no podrá ser editada.', 'Confirmar Aprobación', {
                confirmButtonText: 'Sí, Aprobar',
                cancelButtonText: 'Cancelar',
                type: 'warning'
            }).then(() => {
                this.$http.post(`/${this.resource}/aprobar/${id}`)
                    .then(response => {
                        if (response.data.success) {
                            this.$message.success(response.data.message);
                            this.$eventHub.$emit('reloadData');
                        } else {
                            this.$message.error(response.data.message);
                        }
                    })
                    .catch(error => {
                        this.$message.error('Error al aprobar la planilla');
                        console.error(error);
                    });
            }).catch(() => {
                // Usuario canceló
            });
        },

        clickImprimir(id) {
            // Abrir en nueva ventana para imprimir PDF
            window.open(`/${this.resource}/imprimir-planilla/${id}`, '_blank');
        },

        clickDetail(id) {
            window.location.href = `/${this.resource}/detail/${id}`;
        },

        clickEnviarBoletas(id) {
            this.$confirm('¿Está seguro de enviar las boletas de pago por email a todos los empleados?', 'Confirmar Envío', {
                confirmButtonText: 'Sí, Enviar',
                cancelButtonText: 'Cancelar',
                type: 'info'
            }).then(() => {
                // Mostrar loading
                const loading = this.$loading({
                    lock: true,
                    text: 'Enviando boletas...',
                    spinner: 'el-icon-loading',
                    background: 'rgba(0, 0, 0, 0.7)'
                });

                this.$http.post(`/${this.resource}/enviar-boletas/${id}`)
                    .then(response => {
                        loading.close();
                        if (response.data.success) {
                            const msg = `${response.data.message}`;
                            if (response.data.errores && response.data.errores.length > 0) {
                                this.$message.warning(msg);
                            } else {
                                this.$message.success(msg);
                            }
                            this.$eventHub.$emit('reloadData');
                        } else {
                            this.$message.error(response.data.message);
                        }
                    })
                    .catch(error => {
                        loading.close();
                        this.$message.error('Error al enviar las boletas');
                        console.error(error);
                    });
            }).catch(() => {
                // Usuario canceló
            });
        }
    }
}
</script>
