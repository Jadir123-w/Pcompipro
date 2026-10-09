<template>
    <div>
        <div class="page-header pr-0">
            <h2><a href="/payroll-main/list"><i class="fas fa-arrow-left"></i> Volver al listado</a></h2>
            <ol class="breadcrumbs">
                <li><a href="/dashboard">Dashboard</a></li>
                <li><a href="/payroll-main/list">Planillas</a></li>
                <li class="active"><span>Detalle de Planilla</span></li>
            </ol>
            <div class="right-wrapper pull-right">
                <!-- Acciones Globales -->
                <button v-if="planilla.estado === 'BORRADOR'" class="btn btn-primary btn-sm mt-2 mr-2"
                    @click="clickProcesar">
                    <i class="fa fa-cogs"></i> Procesar
                </button>
                <button v-if="planilla.estado === 'PROCESADA'" class="btn btn-success btn-sm mt-2 mr-2"
                    @click="clickAprobar">
                    <i class="fa fa-check"></i> Aprobar
                </button>
                <button v-if="['PROCESADA', 'APROBADA', 'PAGADA'].includes(planilla.estado)"
                    class="btn btn-secondary btn-sm mt-2 mr-2" @click="clickImprimirPlanilla">
                    <i class="fa fa-print"></i> Imprimir Planilla
                </button>
                <button v-if="['APROBADA', 'PAGADA'].includes(planilla.estado)" class="btn btn-warning btn-sm mt-2 mr-2"
                    @click="clickEnviarBoletas">
                    <i class="fa fa-envelope"></i> Enviar Boletas
                </button>
            </div>
        </div>

        <div class="card mb-0">
            <div class="card-header bg-info text-white">
                <h3 class="my-0">Planilla: {{ planilla.periodo }} | Estado: {{ planilla.estado }}</h3>
                <div class="row mt-2" style="font-size: 14px;">
                    <div class="col-md-3"><strong>Inicio:</strong> {{ planilla.fecha_inicio }}</div>
                    <div class="col-md-3"><strong>Fin:</strong> {{ planilla.fecha_fin }}</div>
                    <div class="col-md-3"><strong>Fecha Pago:</strong> {{ planilla.fecha_pago || '-' }}</div>
                    <div class="col-md-3 text-right"><strong>Empleados:</strong> {{ planilla.total_empleados }}</div>
                </div>
                <div class="row mt-1" style="font-size: 14px;">
                    <div class="col-md-12"><strong>Observaciones:</strong> {{ planilla.observaciones || '-' }}</div>
                </div>
            </div>
            <div class="card-body">

                <div class="row mb-3" v-if="planilla.estado === 'BORRADOR' || planilla.estado === 'PROCESADA'">
                    <div class="col-md-12 text-right">
                        <button class="btn btn-custom btn-sm" @click="openAddEmployee">
                            <i class="fa fa-plus"></i> Agregar Empleado
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>DNI</th>
                                <th>Empleado</th>
                                <th>Cargo</th>
                                <th>Régimen</th>
                                <th class="text-center">Días Lab.</th>
                                <th class="text-right">Ingresos</th>
                                <th class="text-right">Descuentos</th>
                                <th class="text-right">Aportaciones</th>
                                <th class="text-right">Neto Pagar</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in planilla.detalles" :key="row.id">
                                <td>{{ row.dni }}</td>
                                <td>{{ row.nombres_completos }}</td>
                                <td>{{ row.cargo }}</td>
                                <td>{{ row.tipo_regimen }}</td>
                                <td class="text-center">{{ row.dias_laborados }}</td>
                                <td class="text-right">{{ row.total_ingresos }}</td>
                                <td class="text-right">{{ row.total_descuentos }}</td>
                                <td class="text-right">{{ row.total_aportaciones }}</td>
                                <td class="text-right font-weight-bold">{{ row.neto_pagar }}</td>
                                <td class="text-center">
                                    <template v-if="planilla.estado === 'BORRADOR' || planilla.estado === 'PROCESADA'">
                                        <button type="button" class="btn btn-info btn-xs" @click="editEmployee(row)">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-xs"
                                            @click="removeEmployee(row)">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </template>
                                    <button type="button" class="btn btn-secondary btn-xs" @click="printBoleta(row)">
                                        <i class="fa fa-print"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="planilla.detalles && planilla.detalles.length === 0">
                                <td colspan="10" class="text-center">No hay empleados en esta planilla</td>
                            </tr>
                        </tbody>
                        <tfoot v-if="planilla.detalles && planilla.detalles.length > 0">
                            <tr>
                                <td colspan="5" class="text-right font-weight-bold">TOTALES</td>
                                <td class="text-right font-weight-bold">{{ planilla.total_ingresos }}</td>
                                <td class="text-right font-weight-bold">{{ planilla.total_descuentos }}</td>
                                <td class="text-right font-weight-bold">{{ planilla.total_aportaciones }}</td>
                                <td class="text-right font-weight-bold">{{ planilla.total_neto }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Dialog Agregar Empleado -->
        <el-dialog title="Agregar Empleado a Planilla" :visible.sync="showAddEmployeeDialog" width="50%">
            <div v-loading="loadingAvailable">
                <el-input v-model="searchEmployee" placeholder="Buscar por nombre o DNI" class="mb-3"></el-input>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>DNI</th>
                                <th>Nombre</th>
                                <th>Cargo</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="emp in filteredAvailableEmployees" :key="emp.id">
                                <td>{{ emp.dni }}</td>
                                <td>{{ emp.nombre_completo }}</td>
                                <td>{{ emp.cargo }}</td>
                                <td>
                                    <button class="btn btn-primary btn-xs" @click="addEmployeeToPayroll(emp)">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredAvailableEmployees.length === 0">
                                <td colspan="4" class="text-center">No se encontraron empleados disponibles.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <span slot="footer" class="dialog-footer">
                <el-button @click="showAddEmployeeDialog = false">Cerrar</el-button>
            </span>
        </el-dialog>


        <!-- Dialog Editar Detalle -->
        <el-dialog :title="`Editar: ${selectedDetail.nombres_completos || ''}`" :visible.sync="showEditDialog"
            width="40%">
            <form @submit.prevent="updateDetail">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Días Laborados</label>
                            <el-input-number v-model="editForm.dias_laborados" :min="0" :max="31"></el-input-number>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Horas Extras (S/)</label>
                            <el-input-number v-model="editForm.horas_extras" :min="0" :precision="2"
                                :step="0.1"></el-input-number>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Bonificaciones (S/)</label>
                            <el-input-number v-model="editForm.bonificaciones" :min="0" :precision="2"
                                :step="0.1"></el-input-number>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Otros Ingresos (S/)</label>
                            <el-input-number v-model="editForm.otros_ingresos" :min="0" :precision="2"
                                :step="0.1"></el-input-number>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Otros Descuentos (S/)</label>
                            <el-input-number v-model="editForm.otros_descuentos" :min="0" :precision="2"
                                :step="0.1"></el-input-number>
                        </div>
                    </div>
                </div>

                <div class="form-actions text-right mt-3">
                    <el-button @click="showEditDialog = false">Cancelar</el-button>
                    <el-button type="primary" native-type="submit" :loading="loadingUpdate">Guardar</el-button>
                </div>
            </form>
        </el-dialog>

    </div>
</template>

<script>
export default {
    props: ['id'], // Se asume que el ID viene por ruta si se usa como vista completa, o prop si es componente
    data() {
        return {
            resource: 'payroll-main',
            planillaId: null,
            planilla: {
                detalles: []
            },
            loading: false,

            // Add Employee
            showAddEmployeeDialog: false,
            availableEmployees: [],
            searchEmployee: '',
            loadingAvailable: false,

            // Edit
            showEditDialog: false,
            selectedDetail: {},
            editForm: {
                dias_laborados: 30,
                horas_extras: 0,
                bonificaciones: 0,
                otros_ingresos: 0,
                otros_descuentos: 0
            },
            loadingUpdate: false
        }
    },
    created() {
        // Obtener ID de la URL
        const urlParts = window.location.pathname.split('/');
        // Asumiendo ruta /payroll-main/record/{id} o similar si fuera vista directaVue
        // Pero en Laravel blade wrapper, pasamos props o obtenemos de URL
        // Aquí intentaré obtener el ID de la última parte de la URL si no viene en props
        this.planillaId = this.id;
        if (!this.planillaId) {
            // Fallback simple parsing
            this.planillaId = urlParts[urlParts.length - 1];
        }

        this.getRecord();
    },
    computed: {
        filteredAvailableEmployees() {
            if (!this.searchEmployee) return this.availableEmployees;
            const term = this.searchEmployee.toLowerCase();
            return this.availableEmployees.filter(emp => {
                const name = (emp.nombre_completo || '').toLowerCase();
                const dni = (emp.dni + '') || '';
                return name.includes(term) || dni.includes(term);
            });
        }
    },
    methods: {
        getRecord() {
            this.loading = true;
            this.$http.get(`/${this.resource}/record/${this.planillaId}`)
                .then(response => {
                    this.planilla = response.data.data;
                })
                .finally(() => {
                    this.loading = false;
                });
        },

        // --- Acciones Globales del Header ---
        clickProcesar() {
            this.$confirm('¿Procesar planilla? Se recalcularán todos los montos.', 'Confirmar', {
                confirmButtonText: 'Sí, Procesar', cancelButtonText: 'Cancelar', type: 'warning'
            }).then(() => {
                this.$http.post(`/${this.resource}/procesar/${this.planillaId}`)
                    .then(res => {
                        if (res.data.success) {
                            this.$message.success(res.data.message);
                            this.getRecord();
                        } else {
                            this.$message.error(res.data.message);
                        }
                    });
            });
        },
        clickAprobar() {
            this.$confirm('¿Aprobar planilla? No podrá editarse después.', 'Confirmar', {
                confirmButtonText: 'Sí, Aprobar', cancelButtonText: 'Cancelar', type: 'warning'
            }).then(() => {
                this.$http.post(`/${this.resource}/aprobar/${this.planillaId}`)
                    .then(res => {
                        if (res.data.success) {
                            this.$message.success(res.data.message);
                            this.getRecord();
                        } else {
                            this.$message.error(res.data.message);
                        }
                    });
            });
        },
        clickImprimirPlanilla() {
            window.open(`/${this.resource}/imprimir-planilla/${this.planillaId}`, '_blank');
        },
        clickEnviarBoletas() {
            this.$confirm('¿Enviar boletas por email?', 'Confirmar', {
                confirmButtonText: 'Sí, Enviar', cancelButtonText: 'Cancelar', type: 'info'
            }).then(() => {
                const loading = this.$loading({ text: 'Enviando...' });
                this.$http.post(`/${this.resource}/enviar-boletas/${this.planillaId}`)
                    .then(res => {
                        loading.close();
                        if (res.data.success) {
                            this.$message.success(res.data.message);
                            this.getRecord(); // Para actualizar estado boleta enviada si hubiera
                        } else {
                            this.$message.error(res.data.message);
                        }
                    });
            });
        },

        // --- Acciones de Empleados ---

        openAddEmployee() {
            this.showAddEmployeeDialog = true;
            this.loadingAvailable = true;
            this.searchEmployee = '';
            this.$http.get(`/${this.resource}/available-employees/${this.planillaId}`)
                .then(res => {
                    if (res.data.success) {
                        this.availableEmployees = res.data.data;
                    }
                })
                .finally(() => this.loadingAvailable = false);
        },

        addEmployeeToPayroll(employee) {
            this.$http.post(`/${this.resource}/add-employee/${this.planillaId}`, { empleado_id: employee.id })
                .then(res => {
                    if (res.data.success) {
                        this.$message.success(res.data.message);
                        this.getRecord();
                        // Remover de la lista local para no agregarlo doble sin recargar
                        this.availableEmployees = this.availableEmployees.filter(e => e.id !== employee.id);
                    } else {
                        this.$message.error(res.data.message);
                    }
                });
        },

        removeEmployee(row) {
            this.$confirm(`¿Quitar a ${row.nombres_completos} de la planilla?`, 'Confirmar', {
                confirmButtonText: 'Sí, Quitar', cancelButtonText: 'Cancelar', type: 'warning'
            }).then(() => {
                this.$http.delete(`/${this.resource}/remove-employee/${this.planillaId}/${row.id}`)
                    .then(res => {
                        if (res.data.success) {
                            this.$message.success(res.data.message);
                            this.getRecord();
                        } else {
                            this.$message.error(res.data.message);
                        }
                    });
            });
        },

        editEmployee(row) {
            this.selectedDetail = row;
            this.editForm = {
                dias_laborados: parseInt(row.dias_laborados),
                horas_extras: parseFloat(row.horas_extras),
                bonificaciones: parseFloat(row.bonificaciones),
                otros_ingresos: parseFloat(row.otros_ingresos),
                otros_descuentos: parseFloat(row.otros_descuentos)
            };
            this.showEditDialog = true;
        },

        updateDetail() {
            this.loadingUpdate = true;
            this.$http.put(`/${this.resource}/actualizar-detalle/${this.planillaId}/${this.selectedDetail.id}`, this.editForm)
                .then(res => {
                    if (res.data.success) {
                        this.$message.success(res.data.message);
                        this.showEditDialog = false;
                        this.getRecord();
                    } else {
                        this.$message.error(res.data.message);
                    }
                })
                .catch(err => {
                    this.$message.error('Error al actualizar');
                })
                .finally(() => this.loadingUpdate = false);
        },

        printBoleta(row) {
            window.open(`/${this.resource}/imprimir-boleta/${this.planillaId}/${row.id}`, '_blank');
        }

    }
}
</script>
